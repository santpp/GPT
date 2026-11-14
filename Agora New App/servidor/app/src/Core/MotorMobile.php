<?php
declare(strict_types=1);
namespace Agora\Core;
use Agora\Security\Auth;
use Agora\Services\GhlConnection;
/**
 * Agora New App · puente con el motor de Ágora Mobile (servidor/motor/).
 *
 * Cuando una subcuenta tiene su conexión guardada, las rutas que hablan en vivo con la cuenta
 * (bandeja, mensajes, envío, asignar, contactos, oportunidades, embudos, ganadas, tablero) las atiende
 * el mismo código que en Ágora Mobile. El ERP decide antes quién es la persona, a qué subcuenta
 * puede entrar y qué rol tiene; el motor recibe eso hecho.
 *
 * Sin conexión guardada, el ERP sigue respondiendo con sus datos importados, como hasta ahora.
 */
final class MotorMobile {
 public const VERSION='motor-1.10.14';
 /** Tenant único del motor: la agencia. Cada subcuenta del ERP es una conexión con id = id de la subcuenta. */
 public const AGENCIA=1;
 /** Rutas en vivo (GET o el método indicado). */
 private const RUTAS=[
  'workspace/inbox'=>['GET'],'workspace/messages'=>['GET'],'workspace/send'=>['POST'],'workspace/read'=>['POST'],'workspace/assign'=>['POST'],
  'workspace/conversation'=>['GET'],'workspace/contact'=>['GET'],'workspace/record'=>['GET','POST'],'workspace/contacts'=>['GET'],
  'workspace/contact-conversation'=>['POST'],'workspace/contact-fields'=>['GET'],'workspace/tags'=>['GET'],'workspace/templates'=>['GET'],
  'workspace/board'=>['GET'],'workspace/pipelines'=>['GET'],'workspace/opportunities'=>['GET'],'workspace/won'=>['GET'],'workspace/won-alert'=>['GET'],
  'workspace/dashboard'=>['GET'],'workspace/pulse'=>['GET'],'workspace/sync'=>['POST'],
 ];
 private static bool $arrancado=false;

 public static function atiende(string $p,string $m):bool{
  if(isset(self::RUTAS[$p]))return in_array($m,self::RUTAS[$p],true);
  if(preg_match('~^workspace/activity(/note|/task|/task-state|/contact)?$~D',$p))return in_array($m,['GET','POST'],true);
  return (bool)preg_match('~^workspace/opportunity/(new|edit|status)$~D',$p)&&$m==='POST';
 }
 public static function consumos(string $p):bool{return $p==='admin/consumption'||str_starts_with($p,'admin/consumption/');}

 /** Carga el motor con la base del ERP y su propia clave de cifrado (servidor/storage/motor-vault.key). */
 public static function arrancar(array $config):void{
  if(self::$arrancado)return;
  $d=$config['db'];
  putenv('DB_DSN=mysql:host='.$d['host'].';port='.($d['port']??3306).';dbname='.$d['name'].';charset=utf8mb4');
  putenv('DB_USER='.$d['user']);putenv('DB_PASSWORD='.(string)($d['password']??''));
  putenv('APP_ENV='.(($config['env']??'production')==='development'?'development':'production'));
  putenv('APP_ORIGIN='.rtrim((string)($config['app_url']??''),'/'));
  putenv('APP_KEY_FILE='.self::claveMotor());
  require_once dirname(__DIR__,3).'/motor/src/bootstrap.php';
  foreach(['Installation','Actor','PermissionPolicy','GhlClient','TokenVault','GhlGateway','ExternalUserDirectory'] as $c)require_once dirname(__DIR__,3).'/motor/src/'.$c.'.php';
  self::$arrancado=true;
 }
 /** La clave del motor: se crea una vez, fuera de la web. Si se pierde, los tokens se vuelven a cifrar desde el ERP. */
 private static function claveMotor():string{
  $dir=dirname(__DIR__,3).'/storage';$f=$dir.'/motor-vault.key';
  if(!is_file($f)){if(!is_dir($dir))@mkdir($dir,0750,true);@file_put_contents($f,base64_encode(random_bytes(32)),LOCK_EX);@chmod($f,0600);}
  if(!is_readable($f))throw new HttpError('No se pudo preparar la clave del motor en servidor/storage/. Dale permisos de escritura al usuario del sitio.',503);
  return $f;
 }

 /** Crea las tablas del motor en la base del ERP (una vez; idempotente). */
 public static function instalar(DB $erp,array $config):void{
  try{if($erp->one('SELECT 1 ok FROM agora_schema WHERE version=?',[self::VERSION]))return;}catch(\PDOException){}
  self::arrancar($config);
  $dir=dirname(__DIR__,3).'/motor/database';
  $schema=preg_replace('/CREATE TABLE (?!IF NOT EXISTS)/i','CREATE TABLE IF NOT EXISTS ',(string)file_get_contents($dir.'/schema.sql'));
  \db()->exec($schema);
  \Installation::applyMigrations();
  \query('INSERT IGNORE INTO tenants(id,name,location_id) VALUES(?,?,?)',[self::AGENCIA,'Agencia','agora-new-app']);
  $erp->run('INSERT INTO agora_schema(version,checksum) VALUES(?,?)',[self::VERSION,hash('sha256',self::VERSION)]);
 }

 /** Copia la conexión de una subcuenta del ERP al motor (token cifrado con la clave del motor) y trae su equipo. */
 public static function sincronizar(DB $erp,array $config,int $tenant):array{
  self::instalar($erp,$config);self::arrancar($config);
  $c=(new GhlConnection($erp,$config))->credentials($tenant);
  if(!$c){\query("UPDATE ghl_connections SET status='error' WHERE id=?",[$tenant]);return ['sincronizada'=>false];}
  $t=$erp->one('SELECT subaccount,currency FROM agora_tenants WHERE id=?',[$tenant]);
  $otra=\query('SELECT id FROM ghl_connections WHERE tenant_id=? AND location_id=? AND id<>?',[self::AGENCIA,$c['location_id'],$tenant])->fetchColumn();
  if($otra)Http::fail('Ese Location ID ya está conectado a otra subcuenta.',409);
  $cipher=\TokenVault::fromEnvironment()->seal($c['token'],self::AGENCIA,$c['location_id']);
  \query("INSERT INTO ghl_connections(id,tenant_id,name,location_id,token_ciphertext,sync_interval_seconds,status,currency) VALUES(?,?,?,?,?,120,'connected',?)
   ON DUPLICATE KEY UPDATE name=VALUES(name),location_id=VALUES(location_id),token_ciphertext=VALUES(token_ciphertext),status='connected',currency=VALUES(currency)",
   [$tenant,self::AGENCIA,mb_substr((string)($t['subaccount']??'Subcuenta'),0,160),$c['location_id'],$cipher,(string)($t['currency']??'USD')]);
  \query('INSERT INTO ghl_sync_state(connection_id,enabled) VALUES(?,1) ON DUPLICATE KEY UPDATE enabled=1',[$tenant]);
  $equipo=null;
  try{$row=\query('SELECT * FROM ghl_connections WHERE id=?',[$tenant])->fetch();$r=(new \ExternalUserDirectory())->sync($row);$equipo=$r['count']??($r['total']??null);}
  catch(\Throwable $e){error_log('Agora motor: equipo no sincronizado ('.get_class($e).')');}
  return ['sincronizada'=>true,'equipo'=>$equipo];
 }

 public static function conectada(DB $erp,int $tenant):bool{
  try{return (bool)$erp->one('SELECT 1 ok FROM agora_ghl_connections WHERE tenant_id=?',[$tenant]);}catch(\PDOException){return false;}
 }

 /** Atiende una ruta en vivo con el motor. Responde y termina. */
 public static function atender(DB $erp,array $config,Auth $a,string $p,string $m):never{
  $user=$a->requireUser();$tenant=$a->tenant;
  self::instalar($erp,$config);self::arrancar($config);
  if(!\query('SELECT 1 FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetchColumn())self::sincronizar($erp,$config,$tenant);
  $restringido=$a->assignedOnly();
  $puede=static function(string $k)use($a):bool{try{$a->permit($k);return true;}catch(HttpError){return false;}};
  $escribe=$puede('conversaciones.atender');$reasigna=$puede('conversaciones.reasignar');
  $GLOBALS['motorPuente']=['connectionId'=>$tenant,'membership'=>function(array $connection)use($user,$restringido,$escribe,$reasigna):array{
   $full=\PermissionPolicy::capabilities(['identity_type'=>'native','role'=>'administrator']);
   $eu=\query('SELECT * FROM external_users WHERE connection_id=? AND active=TRUE AND LOWER(email)=LOWER(?) LIMIT 1',[$connection['id'],(string)$user['email']])->fetch()?:null;
   if(!$restringido){$caps=$full;}
   else{$caps=$eu?\PermissionPolicy::capabilities($eu+['identity_type'=>'external']):array_map(fn($v)=>false,$full);$caps['assignedDataOnly']=true;}
   foreach(['sendMessages','editContacts','editOpportunities'] as $k)$caps[$k]=($caps[$k]??false)&&$escribe;
   return ['external_user_id'=>$eu['external_user_id']??null,'capabilities'=>$caps,'assigned_data_only'=>$caps['assignedDataOnly'],'can_assign'=>$reasigna&&!$restringido,'role_name'=>$eu['role_name']??null,'role_type'=>$eu['role_type']??null];
  }];
  $u=['id'=>(int)$user['id'],'tenant_id'=>self::AGENCIA,'email'=>(string)$user['email'],'role'=>$restringido?'team':'administrator','identity_type'=>$restringido?'external':'native','ghl_user_id'=>null];
  $u['capabilities']=\PermissionPolicy::capabilities($u);
  $_GET['connectionId']=(string)$tenant;
  $path='/api/'.$p;$method=$m;
  require dirname(__DIR__,3).'/motor/src/live-inbox.php';
  \respond(['error'=>'Ruta no disponible.'],404);
 }

 /** Consumos de la agencia: solo dueños (MASTER y SUPERADMIN). Token de agencia y Company ID cifrados en el motor. */
 public static function atenderConsumos(DB $erp,array $config,Auth $a,string $p,string $m):never{
  $user=$a->requireUser();
  if(!in_array($user['role'],['MASTER','SUPERADMIN'],true))Http::fail('Consumos es solo para los dueños de la agencia.',403);
  self::instalar($erp,$config);self::arrancar($config);
  $u=['id'=>(int)$user['id'],'tenant_id'=>self::AGENCIA,'email'=>(string)$user['email'],'role'=>'administrator','identity_type'=>'native'];
  $path='/api/'.$p;$method=$m;
  require_once dirname(__DIR__,3).'/motor/src/AgencyWallet.php';
  // Agora New App: guardar, quitar y probar la conexión de la agencia quedan en el registro de actividad.
  if($p==='admin/consumption/settings'&&$m==='POST'){
   $b=\body();$w=new \AgencyWallet(self::AGENCIA,$GLOBALS['agencyTransport']??null);
   try{$s=$w->save(array_key_exists('companyId',$b)?(string)$b['companyId']:null,isset($b['token'])?(string)$b['token']:null);}
   catch(\InvalidArgumentException $e){\respond(['error'=>$e->getMessage()],422);}
   $a->audit('agencia.conexion','agencia','consumos',['companyId'=>$s['companyId'],'tokenCambiado'=>isset($b['token'])&&trim((string)$b['token'])!=='']);
   \respond($s);
  }
  if($p==='admin/consumption/token'&&$m==='DELETE'){$a->audit('agencia.conexion.quitada','agencia','consumos');}
  if($p==='admin/consumption/probar'&&$m==='POST'){
   $w=new \AgencyWallet(self::AGENCIA,$GLOBALS['agencyTransport']??null);$s=$w->settings();
   if($s['companyId']===''||!$s['tokenSaved'])\respond(['estado'=>'sin_conexion','error'=>'Falta el Company ID o el token de agencia.']+$s);
   try{$r=$w->transactions(gmdate('Y-m'),null,0,1);}catch(\Throwable $e){$r=['status'=>0,'error'=>'local'];}
   $st=(int)$r['status'];
   $error=match(true){
    \AgencyWallet::ok($r)=>null,
    $st===0=>'No hubo respuesta de la cuenta. Revisa que el servidor tenga salida a internet y vuelve a probar.',
    $st===401=>'El token de agencia no es válido o fue revocado. Crea uno nuevo y pégalo aquí.',
    $st===403=>'El token no tiene permiso para leer la billetera de la agencia (saas/company.read), o el Company ID es de otra agencia.',
    in_array($st,[400,404,422],true)=>'La cuenta no reconoce ese Company ID. Cópialo de nuevo desde la vista de agencia.',
    $st===429=>'La cuenta pidió esperar un momento. Prueba otra vez en un minuto.',
    default=>'La cuenta respondió con un error (HTTP '.$st.'). Prueba otra vez en unos minutos.',
   };
   $a->audit('agencia.conexion.probada','agencia','consumos',['estado'=>$error===null?'conectada':'error','http'=>$st]);
   \respond(['estado'=>$error===null?'conectada':'error','error'=>$error]+$s);
  }
  require dirname(__DIR__,3).'/motor/src/consumption-routes.php';
  \respond(['error'=>'Ruta no disponible.'],404);
 }
}
