<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
final class Agency {
 public function __construct(public DB $db,public Auth $auth){}
 public function dto(array $r):array{return ['id'=>(int)$r['id'],'nombre'=>$r['name'],'subcuenta'=>$r['subaccount'],'name'=>$r['subaccount'],'codigo'=>$r['code'],'estado'=>$r['status'],'moneda'=>$r['currency'],'currency'=>$r['currency'],'rubro'=>$r['industry'],'notas'=>$r['notes'],'logo'=>$r['logo'],'plan'=>($r['plan']??'')!==''?$r['plan']:null,'vence'=>$r['license_expires_at']??null,'venceDias'=>self::daysUntil($r['license_expires_at']??null),'fechaCreacion'=>substr((string)$r['created_at'],0,10),'bloqueado'=>$r['status']!=='activa'];}
 private static function daysUntil(?string $date):?int{if(!$date)return null;$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC'));if(!$d)return null;return (int)floor(($d->getTimestamp()-strtotime(gmdate('Y-m-d').' UTC'))/86400);}
 private static function date(array $b,string $k):?string{$v=$b[$k]??null;if($v===null||$v==='')return null;if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)||!checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4)))Http::fail('Fecha de vencimiento inválida.');return $v;}
 public function accounts(string $method,array $b):array{
  $this->auth->agency();if($method==='GET'){$tenants=$this->auth->tenants();$counts=[];$won=[];$sent=[];if($tenants){$ids=array_column($tenants,'id');$marks=implode(',',array_fill(0,count($ids),'?'));$stats=[];foreach($this->db->all("SELECT local_tenant_id,COUNT(*) n,SUM(active) activos,MAX(last_login_at) ultimo FROM agora_users WHERE local_tenant_id IN ($marks) GROUP BY local_tenant_id",$ids) as $r)$stats[$r['local_tenant_id']]=$r;$counts=$stats;foreach($this->db->all("SELECT tenant_id,COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.monetaryValue')) AS DECIMAL(18,2))),0) v FROM agora_records WHERE tenant_id IN ($marks) AND kind='opportunity' AND status='won' AND updated_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) GROUP BY tenant_id",$ids) as $r)$won[$r['tenant_id']]=(float)$r['v'];foreach($this->db->all("SELECT tenant_id,COUNT(*) n FROM agora_records WHERE tenant_id IN ($marks) AND kind='contract' AND status='ENVIADO' GROUP BY tenant_id",$ids) as $r)$sent[$r['tenant_id']]=(int)$r['n'];}return ['items'=>array_map(fn($t)=>$this->dto($t)+self::billingOut($t['billing']??null)+['paleta'=>$this->paleta((int)$t['id']),'perfil'=>$this->perfil((int)$t['id'])]+['usuarios'=>(int)($counts[$t['id']]['n']??0),'usuariosActivos'=>(int)($counts[$t['id']]['activos']??0),'ultimoAcceso'=>isset($counts[$t['id']]['ultimo'])?substr((string)$counts[$t['id']]['ultimo'],0,10):null,'ingresos30d'=>$won[$t['id']]??0.0,'conveniosPorFirmar'=>$sent[$t['id']]??0],$tenants)];}
  $old=null;if($method==='POST')$this->auth->master();else{$id=Http::integer($b['id']??0,1,PHP_INT_MAX);$old=$this->auth->authorizeTenant($id,true);}
  $name=Http::text($b,'nombre');$sub=Http::text($b,'subcuenta');$code=strtoupper(Http::text($b,'codigo',40));if(!preg_match('/^[A-Z0-9_-]{2,40}$/D',$code))Http::fail('Código inválido.');$status=$b['estado']??'activa';if(!in_array($status,['activa','inactiva','pendiente'],true))Http::fail('Estado inválido.');$currency=$b['moneda']??'USD';if(!in_array($currency,['USD','VES','EUR'],true))Http::fail('Moneda inválida.');
  $logo=array_key_exists('logo',$b)?Users::logo($b['logo']):($old['logo']??null);$pal=array_key_exists('paleta',$b)?Users::palette($b['paleta']):false;$perfil=array_key_exists('perfil',$b)?Personalizar::perfil($b['perfil']):false;$plan=array_key_exists('plan',$b)?Http::text($b,'plan',80,false):($old['plan']??'');$expires=array_key_exists('vence',$b)?self::date($b,'vence'):($old['license_expires_at']??null);$args=[$name,$sub,$code,$status,$currency,Http::text($b,'rubro',100,false),Http::text($b,'notas',2000,false),$logo,$plan,$expires];
  if($old){$args[]=$id;$this->db->run('UPDATE agora_tenants SET name=?,subaccount=?,code=?,status=?,currency=?,industry=?,notes=?,logo=?,plan=?,license_expires_at=?,revision=revision+1 WHERE id=?',$args);}else{$this->db->run('INSERT INTO agora_tenants(name,subaccount,code,status,currency,industry,notes,logo,plan,license_expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)',$args);$id=(int)$this->db->pdo->lastInsertId();}
  if($pal!==false)$this->db->saveSetting($id,'ui.paleta',$pal);
  if($perfil!==false){$perfilAntes=$this->perfil($id);$this->db->saveSetting($id,'cuenta.perfil',$perfil);if($old&&$perfilAntes!==$perfil)$this->auth->audit('subcuenta.tipo','agencia',(string)$id,['antes'=>$perfilAntes,'despues'=>$perfil]);}
  $this->auth->audit($old?($old['status']!==$status?'subcuenta.estado':'subcuenta.actualizada'):'subcuenta.creada','agencia',(string)$id,$old&&$old['status']!==$status?['antes'=>$old['status'],'despues'=>$status]:[]);return ['ok'=>true,'id'=>$id];
 }
 /* Cobros de la subcuenta a la agencia: plan + servicios, montos en centavos dentro del JSON. */
 public const FRECUENCIAS=['mensual'=>1,'trimestral'=>3,'semestral'=>6,'anual'=>12,'unico'=>0];
 private static function cents(mixed $v):int{$v=is_int($v)||is_float($v)?number_format((float)$v,2,'.',''):trim((string)$v);if(!preg_match('/^\d{1,9}(\.\d{1,2})?$/D',$v))Http::fail('Monto inválido. Usa números con hasta dos decimales.');[$i,$d]=array_pad(explode('.',$v),2,'');return (int)$i*100+(int)str_pad($d,2,'0');}
 private static function freq(mixed $v):string{$v=is_string($v)?$v:'';if(!isset(self::FRECUENCIAS[$v]))Http::fail('Frecuencia de cobro inválida.');return $v;}
 private static function monthly(int $cents,string $f):float{$m=self::FRECUENCIAS[$f]??0;return $m?$cents/100/$m:0.0;}
 public static function billingOut(?string $json):array{
  $b=$json?json_decode($json,true):null;if(!is_array($b))return ['cobros'=>null,'cobroMensual'=>null];
  $plan=['monto'=>($b['plan']['monto']??0)/100,'frecuencia'=>$b['plan']['frecuencia']??'mensual'];$total=self::monthly((int)($b['plan']['monto']??0),$plan['frecuencia']);$serv=[];
  foreach($b['servicios']??[] as $x){$serv[]=['id'=>$x['id'],'nombre'=>$x['nombre'],'monto'=>$x['monto']/100,'frecuencia'=>$x['frecuencia'],'activo'=>(bool)$x['activo']];if($x['activo'])$total+=self::monthly((int)$x['monto'],$x['frecuencia']);}
  return ['cobros'=>['plan'=>$plan,'servicios'=>$serv,'actualizado'=>$b['actualizado']??null],'cobroMensual'=>round($total,2)];
 }
 public function billing(array $b):array{
  $this->auth->master();$id=Http::integer($b['id']??0,1,PHP_INT_MAX);$old=$this->auth->authorizeTenant($id,true);
  $plan=$b['plan']??[];if(!is_array($plan))Http::fail('Plan inválido.');$items=$b['servicios']??[];if(!is_array($items)||!array_is_list($items)||count($items)>30)Http::fail('Registra hasta 30 servicios.');
  $serv=[];foreach($items as $x){if(!is_array($x))Http::fail('Servicio inválido.');$sid=isset($x['id'])&&is_string($x['id'])&&preg_match('/^[a-z0-9]{6,24}$/D',$x['id'])?$x['id']:bin2hex(random_bytes(6));$serv[]=['id'=>$sid,'nombre'=>Http::text($x,'nombre',120),'monto'=>self::cents($x['monto']??''),'frecuencia'=>self::freq($x['frecuencia']??''),'activo'=>($x['activo']??true)!==false];}
  $new=['plan'=>['monto'=>self::cents($plan['monto']??'0'),'frecuencia'=>self::freq($plan['frecuencia']??'mensual')],'servicios'=>$serv,'actualizado'=>gmdate('Y-m-d H:i:s')];
  $planName=array_key_exists('nombrePlan',$b)?Http::text($b,'nombrePlan',80,false):($old['plan']??'');
  // 2.1.9: fecha de corte (vencimiento de la licencia) junto con los cobros. Si no viene, se conserva.
  $corteAntes=$old['license_expires_at']??null;$corte=array_key_exists('corte',$b)?self::date($b,'corte'):$corteAntes;
  $this->db->run('UPDATE agora_tenants SET billing=?,plan=?,license_expires_at=?,revision=revision+1 WHERE id=?',[DB::json($new),$planName,$corte,$id]);
  $antes=self::billingOut($old['billing']??null)['cobroMensual'];$despues=self::billingOut(DB::json($new))['cobroMensual'];
  $this->auth->audit('subcuenta.cobros','agencia',(string)$id,['antes'=>$antes===null?'sin cobros':number_format($antes,2,'.','').' '.$old['currency'].'/mes','despues'=>number_format($despues,2,'.','').' '.$old['currency'].'/mes','plan'=>$planName,'servicios'=>count($serv),'corte'=>['antes'=>$corteAntes,'despues'=>$corte]]);
  return ['ok'=>true,'id'=>$id,'cobroMensual'=>$despues,'vence'=>$corte,'venceDias'=>self::daysUntil($corte)];
 }
 public function branding(string $method,array $b):array{
  $t=$this->auth->authorizeTenant($this->auth->tenant,true);
  if($method!=='GET'){$this->auth->manage();$pal=array_key_exists('paleta',$b)?Users::palette($b['paleta']):false;$this->db->run('UPDATE agora_tenants SET name=?,subaccount=?,logo=?,revision=revision+1 WHERE id=?',[isset($b['nombre'])?Http::text($b,'nombre'):$t['name'],isset($b['subcuenta'])?Http::text($b,'subcuenta'):$t['subaccount'],array_key_exists('logo',$b)?Users::logo($b['logo']):$t['logo'],$t['id']]);if($pal!==false)$this->db->saveSetting((int)$t['id'],'ui.paleta',$pal);$this->auth->audit('identidad.actualizada','ajuste',(string)$t['id']);$t=$this->auth->authorizeTenant((int)$t['id'],true);}return $this->dto($t)+['paleta'=>$this->paleta((int)$t['id']),'perfil'=>$this->perfil((int)$t['id']),'personal'=>$this->db->setting((int)$t['id'],'ui.personalizar',null)];
 }
 /** Paleta guardada de una subcuenta (null = colores de Ágora). */
 /** Tipo de cuenta (2.1.16): servicios, producto, cobro recurrente… null = sin definir. */
 private function perfil(int $id):?array{$p=$this->db->setting($id,'cuenta.perfil',null);return is_array($p)?$p:null;}
 private function paleta(int $id):?array{$p=$this->db->setting($id,'ui.paleta',null);return is_array($p)&&isset($p['principal'],$p['acento'])?$p:null;}
 public function audit(bool $agency):array{
  $agency?$this->auth->agency():$this->auth->financial();$args=[];$where=$agency?'tenant_id IS NULL':'tenant_id=?';if(!$agency)$args[]=$this->auth->tenant;
  if($agency&&$this->auth->user['role']!=='MASTER'){$where.=' AND actor_id=?';$args[]=$this->auth->user['id'];}
  if(isset($_GET['period'])){$period=Http::period($_GET['period']);$where.=' AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 MONTH)';$args[]=$period.'-01';$args[]=$period.'-01';}
  $cursor=max(0,(int)($_GET['cursor']??0));if($cursor){$where.=' AND id<?';$args[]=$cursor;}
  $rows=$this->db->all("SELECT * FROM agora_audit WHERE $where ORDER BY id DESC LIMIT 101",$args);$more=count($rows)>100;if($more)array_pop($rows);
  $items=array_map(function($r){$d=json_decode($r['details'],true);return ['id'=>(int)$r['id'],'at'=>$r['created_at'].'Z','fecha'=>$r['created_at'],'actor'=>$r['actor_name'],'admin'=>$r['actor_name'],'accion'=>$r['action'],'accionTexto'=>$r['action'],'tipo'=>$r['module'],'modulo'=>$r['module'],'objeto'=>$r['target'],'entidad'=>$r['target'],'codigo'=>$r['target'],'detalle'=>DB::json($d),'antes'=>$d['antes']??null,'despues'=>$d['despues']??null,'ip'=>$r['ip'],'origen'=>'sistema','tipoEvento'=>$r['action'],'creado_en'=>$r['created_at'],'uid'=>$r['target']];},$rows);
  return ['items'=>$items,'eventos'=>array_map(fn($r)=>$r+['tipo'=>$r['accion']],$items),'resumen'=>['total'=>count($items)],'hasMore'=>$more,'nextCursor'=>$more?(string)end($rows)['id']:null];
 }
 public function texts(string $method,array $b):array{
  if($method==='GET'){$t=(int)($_SERVER['HTTP_X_WORKSPACE_ID']??0);if($t)$this->auth->authorizeTenant($t,true);return ['global'=>(object)$this->db->setting(0,'ui.texts',[]),'tenant'=>(object)($t?$this->db->setting($t,'ui.texts',[]):[])];}
  $this->auth->master();$scope=$b['scope']??'global';$t=$scope==='global'?0:Http::integer($scope,1,PHP_INT_MAX);if($t)$this->auth->authorizeTenant($t,true);$original=Http::text($b,'original',500);$all=$this->db->setting($t,'ui.texts',[]);if($method==='DELETE')unset($all[$original]);else $all[$original]=Http::text($b,'replacement',2000,false);if(count($all)>1000)Http::fail('Límite de textos alcanzado.');$this->db->saveSetting($t,'ui.texts',$all);$this->auth->audit('textos.actualizados','ajuste',(string)$t);return ['ok'=>true];
 }
}
