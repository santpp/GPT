<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;use Agora\Services\{Records,Pdf};
final class Contracts {
 public Records $r;public function __construct(public DB $db,public Auth $auth){$this->r=new Records($db,$auth);}
 public static function cents(mixed $v):int{if(!is_scalar($v)||!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',(string)$v))Http::fail('Monto inválido. Usa punto decimal.');$p=explode('.',(string)$v);return (int)$p[0]*100+(int)str_pad($p[1]??'',2,'0');}
 public static function money(int $c):string{return intdiv($c,100).'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT);}
 public static function calculate(array $b):array{
  $monthly=self::cents($b['cuota']??0);if($monthly===0)Http::fail('La cuota debe ser mayor que cero.');$months=Http::integer($b['meses']??0,1,120);$year=Http::integer($b['anio']??0,2000,2100);$month=Http::integer($b['mes']??0,1,12);$per=Http::integer($b['cuotas_por_mes']??1,1,4);if(!in_array($per,[1,2,4],true))Http::fail('Frecuencia no válida.');$discount=self::cents($b['descuento']??0);if($discount>10000)Http::fail('Descuento fuera de rango.');
  $charges=$b['cargos']??[];if(!is_array($charges)||count($charges)>30)Http::fail('Cargos inválidos.');$totalCharges=0;$clean=[];foreach($charges as $c){$amount=self::cents($c['monto']??0);$totalCharges+=$amount;$clean[]=['concepto'=>Http::text($c,'concepto',150),'monto'=>self::money($amount)];}
  $installments=[];$sum=0;$n=0;$days=[1=>[30],2=>[15,30],4=>[7,14,21,28]][$per];
  for($i=0;$i<$months;$i++){$base=(new \DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month)))->modify("+$i months");$monthAmount=$monthly-($i===0?(int)round($monthly*$discount/10000):0);$part=intdiv($monthAmount,$per);foreach($days as $j=>$day){$value=$part+($j===$per-1?$monthAmount-$part*$per:0);$sum+=$value;$installments[]=['numero'=>++$n,'mes'=>$i+1,'vencimiento'=>$base->format('Y-m-').str_pad((string)min($day,(int)$base->format('t')),2,'0',STR_PAD_LEFT),'monto'=>self::money($value)];}}
  return ['precio_curso'=>self::money($monthly*$months),'cargos'=>$clean,'total_a_pagar'=>self::money($sum+$totalCharges),'cuotas'=>$installments,'moneda'=>'USD'];
 }
 public function listing():array{$p=$this->r->page('contract');$rows=array_map(fn($d)=>['uid'=>$d['uid'],'cliente'=>$d['cliente']['nombre'],'correo'=>$d['cliente']['correo'],'estado'=>$d['estado'],'version'=>$d['contractVersion'],'creado_en'=>$d['creado_en']],$p['items']);return ['contratos'=>$rows]+$p;}
 public function detail(string $id):array{$d=$this->r->payload($this->r->get('contract',$id));$d['version']=$d['contractVersion'];$d['pdfs']=['enviado'=>$d['estado']!=='BORRADOR','firmado'=>$d['estado']==='FIRMADO'];$d['enlace']=null;$d['enlace_guardable']=true;return $d;}
 public function create(array $b):array{$this->auth->write();$client=$b['cliente']??[];if(!is_array($client))Http::fail('Cliente inválido.');$client=['nombre'=>Http::text($client,'nombre',190),'correo'=>Http::email($client),'cedula'=>Http::text($client,'cedula',40),'telefono'=>Http::text($client,'telefono',40,false)];$course=$b['curso']??[];$start=Http::period($course['mes_inicio']??'');$calc=self::calculate(['cuota'=>$course['cuota_mensual']??0,'meses'=>$course['meses']??0,'anio'=>substr($start,0,4),'mes'=>(int)substr($start,5,2),'cuotas_por_mes'=>$course['cuotas_por_mes']??1,'cargos'=>$course['cargos']??[]]);$uid=bin2hex(random_bytes(12));$d=['uid'=>$uid,'contractVersion'=>1,'estado'=>'BORRADOR','cliente'=>$client,'calculo'=>$calc,'descuento'=>'0','creado_en'=>gmdate('Y-m-d H:i:s')];$this->r->save('contract',$uid,$d,$client['nombre'].' '.$client['correo'],'BORRADOR');return ['uid'=>$uid];}
 public function mutate(string $id,string $action,array $b):array{
  $this->auth->write();$row=$this->r->get('contract',$id,true);$d=$this->r->payload($row);unset($d['version']);
  if($action==='cliente'){if($d['estado']!=='BORRADOR')Http::fail('Solo puedes editar un borrador.',409);$d['cliente']=['nombre'=>Http::text($b,'nombre',190),'correo'=>Http::email($b),'cedula'=>Http::text($b,'cedula',40),'telefono'=>Http::text($b,'telefono',40,false)];}
  elseif($action==='editar'){if($d['estado']==='FIRMADO')Http::fail('Un convenio firmado es inmutable; crea otro convenio.',409);if($d['estado']!=='BORRADOR')$d['contractVersion']++;$d['calculo']=self::calculate($b);$d['descuento']=(string)($b['descuento']??0);$d['estado']='BORRADOR';$this->revoke($id);}
  elseif($action==='revocar'){if($d['estado']==='FIRMADO')Http::fail('El convenio ya fue firmado.',409);$this->revoke($id);$d['estado']='REVOCADA';}
  elseif($action==='delete'){if($d['estado']!=='BORRADOR')Http::fail('Solo puedes eliminar borradores.',409);$this->r->delete('contract',$id);return ['ok'=>true];}
  elseif(in_array($action,['enviar','enlace'],true)){
   if(!($this->auth->config['signing_enabled']??false))Http::fail('La firma por enlace no está habilitada en la configuración del servidor.',503);
   if(!in_array($d['estado'],['BORRADOR','ENVIADO']))Http::fail('Estado incompatible para generar enlace.',409);
   $d['estado']='ENVIADO';$this->revoke($id);$token=bin2hex(random_bytes(32));$expires=gmdate('Y-m-d H:i:s',time()+7*86400);$doc=self::document($d);
   $this->db->run('INSERT INTO agora_signatures(token_hash,tenant_id,contract_uid,contract_version,document_hash,expires_at) VALUES(?,?,?,?,?,?)',[hash('sha256',$token),$this->auth->tenant,$id,$d['contractVersion'],hash('sha256',$doc),$expires]);$this->r->save('contract',$id,$d,$d['cliente']['nombre'].' '.$d['cliente']['correo'],$d['estado']);
   return ['enlace'=>$this->auth->config['app_url'].'/firmar.html#'.$token,'vence'=>$expires,'delivery'=>'manual','message'=>'Comparte este enlace con el cliente. No se ha enviado correo.'];
  }else Http::fail('Acción desconocida.',404);
  $this->r->save('contract',$id,$d,$d['cliente']['nombre'].' '.$d['cliente']['correo'],$d['estado']);return ['ok'=>true,'version'=>$d['contractVersion']];
 }
 private function revoke(string $id):void{$this->db->run('UPDATE agora_signatures SET revoked_at=UTC_TIMESTAMP() WHERE tenant_id=? AND contract_uid=? AND revoked_at IS NULL',[$this->auth->tenant,$id]);}
 public static function document(array $d):string{$lines=['AGORA DIGITAL | Convenio de pago','Referencia: '.$d['uid'].' | Version: '.$d['contractVersion'],'Cliente: '.$d['cliente']['nombre'],'Identificacion: '.$d['cliente']['cedula'],'Correo: '.$d['cliente']['correo'],'Total USD: '.$d['calculo']['total_a_pagar'],'Calendario de cuotas:'];foreach($d['calculo']['cuotas'] as $q)$lines[]=$q['numero'].' | '.$q['vencimiento'].' | USD '.$q['monto'];foreach($d['calculo']['cargos'] as $c)$lines[]=$c['concepto'].' | USD '.$c['monto'];$lines[]='El cliente confirma los datos y acepta el calendario de pagos mostrado.';if(isset($d['signature'])){$lines[]='Aceptado por: '.$d['signature']['name'];$lines[]='Fecha UTC: '.$d['signature']['at'];$lines[]='Documento previo SHA-256: '.$d['signature']['documentHash'];}return Pdf::make($lines);}
 public function publicSign(string $method,array $b):array{
  $token=(string)($b['token']??$_GET['token']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$token))Http::fail('Enlace inválido.',404);
  $s=$this->db->one('SELECT * FROM agora_signatures WHERE token_hash=? AND expires_at>UTC_TIMESTAMP() AND revoked_at IS NULL AND used_at IS NULL'.($method==='POST'?' FOR UPDATE':''),[hash('sha256',$token)]);if(!$s)Http::fail('Enlace vencido, usado o revocado.',410);
  $r=$this->db->one("SELECT * FROM agora_records WHERE tenant_id=? AND kind='contract' AND uid=?".($method==='POST'?' FOR UPDATE':''),[$s['tenant_id'],$s['contract_uid']]);$d=json_decode($r['data'],true);if($d['contractVersion']!=(int)$s['contract_version']||$d['estado']!=='ENVIADO')Http::fail('Versión no disponible.',409);
  if(!hash_equals($s['document_hash'],hash('sha256',self::document($d))))Http::fail('El documento cambió. Solicita un enlace nuevo.',409);
  if($method==='GET')return ['cliente'=>$d['cliente']['nombre'],'uid'=>$d['uid'],'calculo'=>$d['calculo'],'documentHash'=>$s['document_hash']];
  if(($b['accept']??false)!==true)Http::fail('Debes aceptar el calendario.');$name=Http::text($b,'name',190);$d['signature']=['name'=>$name,'at'=>gmdate('c'),'documentHash'=>$s['document_hash'],'ip'=>$_SERVER['REMOTE_ADDR']??''];$d['estado']='FIRMADO';$this->db->run("UPDATE agora_records SET data=?,status='FIRMADO',version=version+1,updated_at=UTC_TIMESTAMP() WHERE seq=?",[DB::json($d),$r['seq']]);$this->db->run('UPDATE agora_signatures SET used_at=UTC_TIMESTAMP() WHERE token_hash=?',[$s['token_hash']]);$this->auth->tenant=(int)$s['tenant_id'];$this->auth->audit('firmo','contract',$d['uid'],['documentHash'=>$s['document_hash']]);return ['ok'=>true,'uid'=>$d['uid']];
 }
}
