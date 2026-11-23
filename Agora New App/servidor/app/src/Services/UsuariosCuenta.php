<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http,MotorMobile};
/**
 * 2.22.0 · Personas de Ágora en la cuenta (pedido del usuario). Al invitar a alguien desde Usuarios y roles, Ágora la crea también
 * en la cuenta de la subcuenta con el rol y los permisos que corresponden a su rol de Ágora, y la deja enlazada. Si su correo ya
 * está en la cuenta, no se duplica: se enlaza y se le ajustan los permisos.
 * Al cambiar el rol se ajustan sus permisos; al suspenderla o borrarla de Ágora se le QUITAN los permisos en la cuenta pero no se
 * borra (decisión del usuario: «opción B», reversible y sin conversaciones huérfanas). Al reactivarla se le devuelven.
 * La clave de la cuenta es al azar y no se guarda ni se muestra: si la persona necesita entrar a la cuenta, usa «olvidé mi clave» allí.
 * Nunca lanza por un fallo de la cuenta: devuelve ['ok'=>false,'mensaje'=>…] para que Ágora siga y la pantalla lo diga.
 * Necesita el permiso users.write en la integración de la subcuenta.
 */
final class UsuariosCuenta{
 /** Interruptores de la cuenta que maneja Ágora. Se mandan todos, encendidos o apagados, para que bajar de rol también quite. */
 public const CLAVES=['campaignsEnabled','campaignsReadOnly','contactsEnabled','workflowsEnabled','workflowsReadOnly','triggersEnabled','funnelsEnabled','websitesEnabled',
  'opportunitiesEnabled','dashboardStatsEnabled','bulkRequestsEnabled','appointmentsEnabled','reviewsEnabled','onlineListingsEnabled','phoneCallEnabled','conversationsEnabled',
  'assignedDataOnly','adwordsReportingEnabled','membershipEnabled','facebookAdsReportingEnabled','attributionsReportingEnabled','settingsEnabled','tagsEnabled','leadValueEnabled',
  'marketingEnabled','agentReportingEnabled','botService','socialPlanner','bloggingEnabled','invoiceEnabled','affiliateManagerEnabled','contentAiEnabled','refundsEnabled',
  'recordPaymentEnabled','cancelSubscriptionEnabled','paymentsEnabled','communitiesEnabled','exportPaymentsEnabled'];
 private const PAGOS=['settingsEnabled','paymentsEnabled','refundsEnabled','recordPaymentEnabled','cancelSubscriptionEnabled','exportPaymentsEnabled','invoiceEnabled','affiliateManagerEnabled','workflowsEnabled','triggersEnabled','bulkRequestsEnabled'];
 /** Reparto aprobado por el usuario (2.22.0). Devuelve [rol de la cuenta, permisos]. $activo=false: sin permisos (opción B). */
 public static function reparto(string $rol,bool $activo=true):array{
  $todo=array_fill_keys(self::CLAVES,false);
  if(!$activo)return ['user',['assignedDataOnly'=>true]+$todo];
  return match($rol){
   'ADMIN'=>['admin',array_fill_keys(self::CLAVES,true)+['assignedDataOnly'=>false,'campaignsReadOnly'=>false,'workflowsReadOnly'=>false]],
   'SUPERVISOR'=>['user',(function()use($todo){$p=array_fill_keys(self::CLAVES,true);foreach(self::PAGOS as $k)$p[$k]=false;$p['assignedDataOnly']=false;$p['campaignsReadOnly']=false;$p['workflowsReadOnly']=false;return $p;})()],
   'EJECUTIVO','REMITENTE'=>['user',['conversationsEnabled'=>true,'contactsEnabled'=>true,'opportunitiesEnabled'=>true,'appointmentsEnabled'=>true,'phoneCallEnabled'=>true,'assignedDataOnly'=>true]+$todo],
   default=>['user',['contactsEnabled'=>true,'opportunitiesEnabled'=>true,'assignedDataOnly'=>true]+$todo], // Consulta: lo mínimo, sin conversaciones (la cuenta no tiene «solo mirar»)
  };
 }
 public function __construct(private DB $db,private array $config){}
 private function conexion(int $tenant):?array{
  if(!MotorMobile::conectada($this->db,$tenant))return null;
  MotorMobile::instalar($this->db,$this->config);MotorMobile::arrancar($this->config);
  $c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,MotorMobile::AGENCIA])->fetch();
  if(!$c){MotorMobile::sincronizar($this->db,$this->config,$tenant);$c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,MotorMobile::AGENCIA])->fetch();}
  return $c?:null;
 }
 private function llamar(array $c,\Closure $f):array{
  // Suave: un rechazo (correo en uso, permisos) no pausa la conexión. Si la cuenta pide esperar pocos segundos (tope de llamadas), se espera y se reintenta una vez.
  try{return (new \GhlGateway())->execute($c,$f,false,true);}
  catch(\GhlCooldown $e){if($e->retryAfter>10)throw $e;sleep(max(1,$e->retryAfter));return (new \GhlGateway())->execute($c,$f,false,true);}
 }
 private function motivo(\Throwable $e,string $que):string{
  if($e instanceof \GhlCooldown)return "La cuenta pidió esperar un momento. $que no se hizo en la cuenta: vuelve a intentarlo.";
  if($e instanceof \GhlApiException)return match(true){
   in_array($e->status,[401,403],true)=>"La cuenta no dio permiso. Revisa que la conexión de la subcuenta tenga el permiso de escribir usuarios. $que no se hizo en la cuenta.",
   in_array($e->status,[400,409,422],true)=>"La cuenta no lo aceptó".($e->detail!==''?': '.$e->detail:'').". $que no se hizo en la cuenta.",
   default=>"La cuenta no respondió bien. $que no se hizo en la cuenta: vuelve a intentarlo."};
  return "No se pudo hablar con la cuenta. $que no se hizo en la cuenta.";
 }
 /** Company ID: el de la conexión; si no está, se pregunta a la cuenta y se guarda. */
 private function empresa(array $c,int $tenant):string{
  $id=(string)($this->db->one('SELECT company_id FROM agora_ghl_connections WHERE tenant_id=?',[$tenant])['company_id']??'');
  if($id!=='')return $id;
  $r=$this->llamar($c,fn(\GhlClient $cl):array=>$cl->location((string)$c['location_id']));
  $id=(string)($r['location']['companyId']??'');
  if(preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$id))$this->db->run('UPDATE agora_ghl_connections SET company_id=? WHERE tenant_id=?',[$id,$tenant]);
  return $id;
 }
 private function enlazar(int $tenant,int $userId,string $externo):void{
  $this->db->run('DELETE FROM agora_user_links WHERE tenant_id=? AND (user_id=? OR external_user_id=?)',[$tenant,$userId,$externo]);
  $this->db->run('INSERT INTO agora_user_links(tenant_id,user_id,external_user_id) VALUES(?,?,?)',[$tenant,$userId,$externo]);
 }
 /** Vuelve a leer el equipo de la cuenta. Al hacerlo el motor vuelve a cifrar el token: hay que releer la conexión después (releer). */
 private function equipoAlDia(int $tenant):void{try{MotorMobile::sincronizar($this->db,$this->config,$tenant);}catch(\Throwable){}}
 private function releer(int $tenant):array{return \query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,MotorMobile::AGENCIA])->fetch()?:[];}
 /**
  * Crea (o enlaza si su correo ya está) a una persona de Ágora en la cuenta. $externo: la persona de la cuenta elegida a mano.
  * Devuelve ['ok','accion'=>'creada'|'enlazada','mensaje'].
  */
 public function crear(int $tenant,array $u,?string $externo=null):array{
  $c=$this->conexion($tenant);if(!$c)return ['ok'=>false,'accion'=>null,'mensaje'=>'Esta subcuenta no está conectada con su cuenta: la persona se creó solo en Ágora.'];
  $links=new UserLinks($this->db);
  $buscar=function()use($links,$tenant,$u):?string{foreach($links->team($tenant) as $t)if($t['correo']!==''&&mb_strtolower($t['correo'])===mb_strtolower((string)$u['email']))return $t['id'];return null;};
  if(!$externo){$this->equipoAlDia($tenant);$c=$this->releer($tenant);$externo=$buscar();} // el equipo al día: así no se intenta crear a quien ya está en la cuenta
  [$rol,$permisos]=self::reparto((string)$u['role'],(bool)$u['active']);
  try{
   if($externo){
    try{$this->llamar($c,fn(\GhlClient $cl):array=>$cl->updateUser($externo,['role'=>$rol,'type'=>'account','permissions'=>$permisos]));
     $this->enlazar($tenant,(int)$u['id'],$externo);return ['ok'=>true,'accion'=>'enlazada','mensaje'=>'Ya estaba en la cuenta: quedó enlazada y con los permisos de su rol.'];}
    catch(\GhlApiException $e){if(!in_array($e->status,[400,404],true)||!preg_match('/not found|no existe/i',$e->detail))throw $e;} // el equipo guardado estaba viejo: se crea
   }
   $partes=preg_split('/\s+/u',trim((string)$u['name']));$nombre=array_shift($partes);$apellido=implode(' ',$partes);
   if($apellido==='')return ['ok'=>false,'accion'=>null,'mensaje'=>'La cuenta pide nombre y apellido. Cambia el nombre (por ejemplo «Ana Pérez») y usa «Crear en la cuenta».'];
   $empresa=$this->empresa($c,$tenant);if($empresa==='')return ['ok'=>false,'accion'=>null,'mensaje'=>'No se pudo saber a qué agencia pertenece la subcuenta. Revisa la conexión en Ajustes.'];
   $clave=self::claveAzar();
   try{$r=$this->llamar($c,fn(\GhlClient $cl):array=>$cl->createUser(['companyId'=>$empresa,'firstName'=>$nombre,'lastName'=>$apellido,'email'=>(string)$u['email'],'password'=>$clave,
    'type'=>'account','role'=>$rol,'locationIds'=>[(string)$c['location_id']],'permissions'=>$permisos,'platformLanguage'=>'es']+(!empty($u['phone'])?['phone'=>(string)$u['phone']]:[])));}
   catch(\GhlApiException $e){
    // La cuenta dice que el correo ya existe (otra subcuenta de la agencia o el equipo aún no se leyó): se enlaza en vez de fallar.
    if(!in_array($e->status,[400,409,422],true)||!preg_match('/email|correo/i',$e->detail))throw $e;
    $this->equipoAlDia($tenant);$c=$this->releer($tenant);$otro=$buscar();if(!$otro)return ['ok'=>false,'accion'=>null,'mensaje'=>'Ese correo ya tiene usuario en la cuenta, pero no en esta subcuenta. Agrégalo a la subcuenta desde la cuenta y pulsa «Actualizar equipo».'];
    $this->llamar($c,fn(\GhlClient $cl):array=>$cl->updateUser($otro,['role'=>$rol,'type'=>'account','permissions'=>$permisos]));
    $this->enlazar($tenant,(int)$u['id'],$otro);return ['ok'=>true,'accion'=>'enlazada','mensaje'=>'Ya estaba en la cuenta: quedó enlazada y con los permisos de su rol.'];
   }
   $id=(string)($r['id']??($r['user']['id']??''));
   if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))return ['ok'=>false,'accion'=>null,'mensaje'=>'La cuenta no confirmó la persona. Pulsa «Actualizar equipo» antes de repetirlo.'];
   $this->equipoAlDia($tenant);$this->enlazar($tenant,(int)$u['id'],$id);
   return ['ok'=>true,'accion'=>'creada','mensaje'=>'Creada en la cuenta con los permisos de su rol y enlazada.'];
  }catch(\Throwable $e){return ['ok'=>false,'accion'=>null,'mensaje'=>$this->motivo($e,'El alta')];}
 }
 /** Ajusta en la cuenta los permisos de alguien ya enlazado (cambio de rol, suspensión, reactivación o baja). Sin enlace, no hace nada. */
 public function ajustar(int $tenant,array $u,?bool $activo=null,string $que='El cambio'):?array{
  $e=(new UserLinks($this->db))->forUser($tenant,$u);if(!$e)return null;
  $c=$this->conexion($tenant);if(!$c)return null;
  [$rol,$permisos]=self::reparto((string)$u['role'],$activo??(bool)$u['active']);
  try{$this->llamar($c,fn(\GhlClient $cl):array=>$cl->updateUser((string)$e['id'],['role'=>$rol,'type'=>'account','permissions'=>$permisos]));
   return ['ok'=>true,'mensaje'=>($activo??(bool)$u['active'])?'Permisos actualizados también en la cuenta.':'En la cuenta se le quitaron los permisos (sigue creada).'];}
  catch(\Throwable $x){return ['ok'=>false,'mensaje'=>$this->motivo($x,$que)];}
 }
 /** Clave fuerte al azar para la cuenta (nunca se guarda ni se muestra). */
 private static function claveAzar():string{$a='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';$s='';for($i=0;$i<20;$i++)$s.=$a[random_int(0,strlen($a)-1)];return $s.'#'.random_int(10,99).'Aa';}
}
