<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
final class Agency {
 public function __construct(public DB $db,public Auth $auth){}
 public function ghl():\Agora\Services\GhlConnection{return new \Agora\Services\GhlConnection($this->db,$this->auth->config);}
 /** Agora New App · «Probar conexión» de una subcuenta (agencia). */
 public function ghlTest(array $b):array{$this->auth->agency();$id=Http::integer($b['id']??0,1,PHP_INT_MAX);$this->auth->authorizeTenant($id,true);$r=$this->ghl()->test($id);if($r['estado']==='conectada'){try{$r['motor']=\Agora\Core\MotorMobile::sincronizar($this->db,$this->auth->config,$id);}catch(\Throwable $e){error_log('Agora motor: '.get_class($e).' '.$e->getMessage());}}$this->auth->audit('subcuenta.conexion.probada','agencia',(string)$id,['estado'=>$r['estado']]);return $r;}
 public function dto(array $r):array{return ['id'=>(int)$r['id'],'nombre'=>$r['name'],'subcuenta'=>$r['subaccount'],'name'=>$r['subaccount'],'codigo'=>$r['code'],'estado'=>$r['status'],'moneda'=>$r['currency'],'currency'=>$r['currency'],'rubro'=>$r['industry'],'notas'=>$r['notes'],'logo'=>$r['logo'],'plan'=>($r['plan']??'')!==''?$r['plan']:null,'vence'=>$r['license_expires_at']??null,'venceDias'=>self::daysUntil($r['license_expires_at']??null),'fechaCreacion'=>substr((string)$r['created_at'],0,10),'bloqueado'=>$r['status']!=='activa'];}
 private static function daysUntil(?string $date):?int{if(!$date)return null;$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC'));if(!$d)return null;return (int)floor(($d->getTimestamp()-strtotime(gmdate('Y-m-d').' UTC'))/86400);}
 private static function date(array $b,string $k):?string{$v=$b[$k]??null;if($v===null||$v==='')return null;if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)||!checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4)))Http::fail('Fecha de vencimiento inválida.');return $v;}
 public function accounts(string $method,array $b):array{
  $this->auth->agency();if($method==='GET'){$tenants=$this->auth->tenants();$counts=[];$won=[];$sent=[];if($tenants){$ids=array_column($tenants,'id');$marks=implode(',',array_fill(0,count($ids),'?'));$stats=[];foreach($this->db->all("SELECT local_tenant_id,COUNT(*) n,SUM(active) activos,MAX(last_login_at) ultimo FROM agora_users WHERE local_tenant_id IN ($marks) GROUP BY local_tenant_id",$ids) as $r)$stats[$r['local_tenant_id']]=$r;$counts=$stats;foreach($this->db->all("SELECT tenant_id,COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.monetaryValue')) AS DECIMAL(18,2))),0) v FROM agora_records WHERE tenant_id IN ($marks) AND kind='opportunity' AND status='won' AND updated_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) GROUP BY tenant_id",$ids) as $r)$won[$r['tenant_id']]=(float)$r['v'];foreach($this->db->all("SELECT tenant_id,COUNT(*) n FROM agora_records WHERE tenant_id IN ($marks) AND kind='contract' AND status='ENVIADO' GROUP BY tenant_id",$ids) as $r)$sent[$r['tenant_id']]=(int)$r['n'];}return ['items'=>array_map(fn($t)=>$this->dto($t)+self::billingOut($t['billing']??null)+['paleta'=>$this->paleta((int)$t['id']),'perfil'=>$this->perfil((int)$t['id'])]+['usuarios'=>(int)($counts[$t['id']]['n']??0),'usuariosActivos'=>(int)($counts[$t['id']]['activos']??0),'ultimoAcceso'=>isset($counts[$t['id']]['ultimo'])?substr((string)$counts[$t['id']]['ultimo'],0,10):null,'ingresos30d'=>$won[$t['id']]??0.0,'conveniosPorFirmar'=>$sent[$t['id']]??0,'conexion'=>$this->ghl()->status((int)$t['id'])],$tenants)];}
  $old=null;if($method==='POST')$this->auth->master();else{$id=Http::integer($b['id']??0,1,PHP_INT_MAX);$old=$this->auth->authorizeTenant($id,true);}
  $name=Http::text($b,'nombre');$sub=Http::text($b,'subcuenta');$code=strtoupper(Http::text($b,'codigo',40));if(!preg_match('/^[A-Z0-9_-]{2,40}$/D',$code))Http::fail('Código inválido.');$status=$b['estado']??'activa';if(!in_array($status,['activa','inactiva','pendiente'],true))Http::fail('Estado inválido.');$currency=$b['moneda']??'USD';if(!in_array($currency,['USD','VES','EUR'],true))Http::fail('Moneda inválida.');
  $logo=array_key_exists('logo',$b)?Users::logo($b['logo']):($old['logo']??null);$pal=array_key_exists('paleta',$b)?Users::palette($b['paleta']):false;$perfil=array_key_exists('perfil',$b)?Personalizar::perfil($b['perfil']):false;$plan=array_key_exists('plan',$b)?Http::text($b,'plan',80,false):($old['plan']??'');$expires=array_key_exists('vence',$b)?self::date($b,'vence'):($old['license_expires_at']??null);$args=[$name,$sub,$code,$status,$currency,Http::text($b,'rubro',100,false),Http::text($b,'notas',2000,false),$logo,$plan,$expires];
  if($old){$args[]=$id;$this->db->run('UPDATE agora_tenants SET name=?,subaccount=?,code=?,status=?,currency=?,industry=?,notes=?,logo=?,plan=?,license_expires_at=?,revision=revision+1 WHERE id=?',$args);}else{$this->db->run('INSERT INTO agora_tenants(name,subaccount,code,status,currency,industry,notes,logo,plan,license_expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)',$args);$id=(int)$this->db->pdo->lastInsertId();}
  if($pal!==false)$this->db->saveSetting($id,'ui.paleta',$pal);
  // Agora New App: conexión con la cuenta (token cifrado, Location ID). Se audita qué cambió, nunca el token.
  if(isset($b['conexion'])){if(!is_array($b['conexion']))Http::fail('Datos de conexión inválidos.');$cambio=$this->ghl()->save($id,$b['conexion']);if($cambio){$this->auth->audit('subcuenta.conexion','agencia',(string)$id,$cambio);try{\Agora\Core\MotorMobile::sincronizar($this->db,$this->auth->config,$id);}catch(\Agora\Core\HttpError $e){throw $e;}catch(\Throwable $e){error_log('Agora motor: '.get_class($e).' '.$e->getMessage());}}}
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
  if($method!=='GET'){$this->auth->manage();$pal=array_key_exists('paleta',$b)?Users::palette($b['paleta']):false;$this->db->run('UPDATE agora_tenants SET name=?,subaccount=?,logo=?,revision=revision+1 WHERE id=?',[isset($b['nombre'])?Http::text($b,'nombre'):$t['name'],isset($b['subcuenta'])?Http::text($b,'subcuenta'):$t['subaccount'],array_key_exists('logo',$b)?Users::logo($b['logo']):$t['logo'],$t['id']]);if($pal!==false)$this->db->saveSetting((int)$t['id'],'ui.paleta',$pal);$this->auth->audit('identidad.actualizada','ajuste',(string)$t['id']);$t=$this->auth->authorizeTenant((int)$t['id'],true);}return $this->dto($t)+['paleta'=>$this->paleta((int)$t['id']),'perfil'=>$this->perfil((int)$t['id']),'personal'=>$this->db->setting((int)$t['id'],'ui.personalizar',null),'permisos'=>$this->auth->effective(),'hayReporte'=>(bool)$this->db->one("SELECT 1 x FROM agora_records WHERE tenant_id=? AND kind='report' LIMIT 1",[(int)$t['id']]),'conectada'=>$con=(bool)$this->db->one('SELECT 1 x FROM agora_ghl_connections WHERE tenant_id=?',[(int)$t['id']]),'enlace'=>$con?(new \Agora\Services\UserLinks($this->db))->forUser((int)$t['id'],$this->auth->user):null];
 }
 /** Paleta guardada de una subcuenta (null = colores de Ágora). */
 /** Tipo de cuenta (2.1.16): servicios, producto, cobro recurrente… null = sin definir. */
 private function perfil(int $id):?array{$p=$this->db->setting($id,'cuenta.perfil',null);return is_array($p)?$p:null;}
 private function paleta(int $id):?array{$p=$this->db->setting($id,'ui.paleta',null);return is_array($p)&&isset($p['principal'],$p['acento'])?$p:null;}
 /** $modulo: solo los movimientos de ese módulo (2.8.5: «Registro» muestra solo convenios, no toda la bitácora). */
 /** 2.13.0 · Correo de Ágora: el dueño elige la subcuenta conectada por la que salen los correos (códigos de acceso,
  * cambio de clave). Sin SMTP: usa el canal de correo de esa cuenta. Se puede apagar el código al entrar. */
 public function correo(string $m,array $b):array{
  $this->auth->master();
  $actual=$this->db->setting(0,'correo.canal',null);$actual=is_array($actual)?$actual:[];
  if($m==='PUT'){
   $id=($b['connectionId']??null)===null||$b['connectionId']===''?null:Http::integer($b['connectionId'],1,PHP_INT_MAX);
   // 2.18.0: una conexión guardada pero sin probar se prueba aquí mismo (antes no aparecía en la lista).
   if($id){$g=$this->db->one('SELECT g.status FROM agora_tenants t JOIN agora_ghl_connections g ON g.tenant_id=t.id WHERE t.id=?',[$id]);
    if(!$g)Http::fail('Esa subcuenta no tiene conexión con su cuenta.',422);
    if($g['status']!=='conectada'){try{$r=$this->ghl()->test($id);}catch(\Agora\Core\HttpError $e){throw $e;}catch(\Throwable){Http::fail('La conexión de esa subcuenta no responde: vuelve a escribir su token en la ficha de la subcuenta.',422);}if(($r['estado']??'')!=='conectada')Http::fail('La conexión de esa subcuenta no responde: '.($r['error']??'revisa el token en la ficha de la subcuenta.'),422);}}
   $actual=['connectionId'=>$id,'codigoAlEntrar'=>($b['codigoAlEntrar']??true)!==false];
   $this->db->saveSetting(0,'correo.canal',$actual);$this->auth->audit('correo.canal','agencia',(string)($id??''));
  }
  $opciones=array_map(fn($r)=>['id'=>(int)$r['id'],'nombre'=>$r['name'].($r['status']==='conectada'?'':' (se prueba al guardar)')],$this->db->all("SELECT t.id,t.name,g.status FROM agora_tenants t JOIN agora_ghl_connections g ON g.tenant_id=t.id ORDER BY g.status='conectada' DESC,t.name LIMIT 500"));
  return ['connectionId'=>isset($actual['connectionId'])?(int)$actual['connectionId']?:null:null,'codigoAlEntrar'=>($actual['codigoAlEntrar']??true)!==false,'opciones'=>$opciones];
 }
 public function correoProbar():array{
  $this->auth->master();$u=$this->auth->requireUser();$this->auth->rate('correo-probar:'.$u['id'],5,600);
  try{$ok=\Agora\Core\MotorMobile::correo($this->db,$this->auth->config,$u['email'],'Prueba del correo de Ágora','El correo de Ágora funciona','Este mensaje confirma que Ágora puede enviar correos por la subcuenta elegida.',null,'Desde ahora, los códigos de acceso y los cambios de clave salen por aquí.',"El correo de Ágora funciona.\n");}
  catch(\Throwable $e){Http::fail('No se pudo enviar la prueba: '.$e->getMessage(),502);}
  if(!$ok)Http::fail('Primero elige la subcuenta por la que saldrán los correos.',409);
  $this->auth->audit('correo.prueba','agencia');return ['ok'=>true,'destino'=>$u['email']];
 }
 /** 2.12.15 · [9]: el detalle del registro se cuenta en palabras; nunca se muestran identificadores ni el JSON. */
 private static function detalleLegible(mixed $d):string{
  if(!is_array($d))return '';$t=[];
  if(isset($d['detalle'])&&is_string($d['detalle'])&&!preg_match('/^[\[{]/',$d['detalle']))$t[]=$d['detalle'];
  if(!empty($d['tokenCambiado']))$t[]='Se cambió el token de la cuenta';
  if(array_key_exists('locationId',$d))$t[]='Se revisó la subcuenta conectada';
  if(array_key_exists('companyId',$d)&&$d['companyId'])$t[]='Se registró la agencia en la cuenta';
  return implode('. ',array_unique($t));
 }
 /** 2.18.15 · Accesos con código (solo dueños): quién pidió o usó un código, de qué subcuenta, desde qué IP y navegador. Nunca el código. */
 public function accesos():array{
  $this->auth->agency();
  $dias=(int)($_GET['dias']??7);if(!in_array($dias,[1,7,30,90,180],true))$dias=7;
  $where=['l.created_at>=UTC_TIMESTAMP()-INTERVAL '.$dias.' DAY'];$args=[];
  $ev=(string)($_GET['evento']??'');if($ev!==''){if(!in_array($ev,['enviado','reenviado','correcto','incorrecto','vencido','invalido','no_enviado','sin_cuenta','dueno_bloqueado'],true))\Agora\Core\Http::fail('Filtro no válido.',422);$where[]='l.evento=?';$args[]=$ev;}
  $q=trim(mb_substr((string)($_GET['q']??''),0,80));if($q!==''){$where[]='(l.correo LIKE ? OR l.ip LIKE ? OR u.name LIKE ? OR t.name LIKE ?)';$like='%'.addcslashes($q,'%_\\').'%';array_push($args,$like,$like,$like,$like);}
  $rows=$this->db->all('SELECT l.id,l.created_at,l.evento,l.uso,l.correo,l.ip,l.navegador,l.user_id,u.name nombre,u.role rol,t.name subcuenta FROM agora_access_log l LEFT JOIN agora_users u ON u.id=l.user_id LEFT JOIN agora_tenants t ON t.id=l.tenant_id WHERE '.implode(' AND ',$where).' ORDER BY l.id DESC LIMIT 301',$args);
  $mas=count($rows)>300;if($mas)array_pop($rows);
  $res=$this->db->all('SELECT l.ip,COUNT(*) n,SUM(l.evento=\'incorrecto\') malos,COUNT(DISTINCT l.correo) cuentas FROM agora_access_log l WHERE l.created_at>=UTC_TIMESTAMP()-INTERVAL '.$dias.' DAY AND l.ip<>\'\' GROUP BY l.ip ORDER BY n DESC LIMIT 5',[]);
  return ['items'=>array_map(fn($r)=>['id'=>(int)$r['id'],'fecha'=>gmdate('c',strtotime($r['created_at'].' UTC')),'evento'=>$r['evento'],'uso'=>$r['uso'],'correo'=>$r['correo'],'nombre'=>$r['nombre'],'rol'=>$r['rol'],'subcuenta'=>$r['subcuenta']??($r['rol']&&in_array($r['rol'],['MASTER','SUPERADMIN'],true)?'Agencia':null),'ip'=>$r['ip'],'navegador'=>$r['navegador']],$rows),'mas'=>$mas,'dias'=>$dias,
   'ips'=>array_map(fn($r)=>['ip'=>$r['ip'],'n'=>(int)$r['n'],'malos'=>(int)$r['malos'],'cuentas'=>(int)$r['cuentas']],$res)];
 }
 public function audit(bool $agency,?string $modulo=null):array{
  $agency?$this->auth->agency():$this->auth->financial();$args=[];$where=$agency?'tenant_id IS NULL':'tenant_id=?';if(!$agency)$args[]=$this->auth->tenant;
  if($modulo!==null){$where.=' AND module=?';$args[]=$modulo;}
  if($agency&&$this->auth->user['role']!=='MASTER'){$where.=' AND actor_id=?';$args[]=$this->auth->user['id'];}
  if(isset($_GET['period'])){$period=Http::period($_GET['period']);$where.=' AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 MONTH)';$args[]=$period.'-01';$args[]=$period.'-01';}
  $cursor=max(0,(int)($_GET['cursor']??0));if($cursor){$where.=' AND id<?';$args[]=$cursor;}
  $rows=$this->db->all("SELECT * FROM agora_audit WHERE $where ORDER BY id DESC LIMIT 101",$args);$more=count($rows)>100;if($more)array_pop($rows);
  $items=array_map(function($r){$d=json_decode($r['details'],true);return ['id'=>(int)$r['id'],'at'=>$r['created_at'].'Z','fecha'=>$r['created_at'],'actor'=>$r['actor_name'],'admin'=>$r['actor_name'],'accion'=>$r['action'],'accionTexto'=>$r['action'],'tipo'=>$r['module'],'modulo'=>$r['module'],'objeto'=>$r['target'],'entidad'=>$r['target'],'codigo'=>$r['target'],'detalle'=>self::detalleLegible($d),'antes'=>$d['antes']??null,'despues'=>$d['despues']??null,'ip'=>$r['ip'],'origen'=>'sistema','tipoEvento'=>$r['action'],'creado_en'=>$r['created_at'],'uid'=>$r['target']];},$rows);
  return ['items'=>$items,'eventos'=>array_map(fn($r)=>$r+['tipo'=>$r['accion']],$items),'resumen'=>['total'=>count($items)],'hasMore'=>$more,'nextCursor'=>$more?(string)end($rows)['id']:null];
 }
 public function texts(string $method,array $b):array{
  if($method==='GET'){$t=(int)($_SERVER['HTTP_X_WORKSPACE_ID']??0);if($t)$this->auth->authorizeTenant($t,true);return ['global'=>(object)$this->db->setting(0,'ui.texts',[]),'tenant'=>(object)($t?$this->db->setting($t,'ui.texts',[]):[])];}
  $this->auth->master();$scope=$b['scope']??'global';$t=$scope==='global'?0:Http::integer($scope,1,PHP_INT_MAX);if($t)$this->auth->authorizeTenant($t,true);$original=Http::text($b,'original',500);$all=$this->db->setting($t,'ui.texts',[]);if($method==='DELETE')unset($all[$original]);else $all[$original]=Http::text($b,'replacement',2000,false);if(count($all)>1000)Http::fail('Límite de textos alcanzado.');$this->db->saveSetting($t,'ui.texts',$all);$this->auth->audit('textos.actualizados','ajuste',(string)$t);return ['ok'=>true];
 }
}
