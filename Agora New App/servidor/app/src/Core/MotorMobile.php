<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
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
 public const VERSION='motor-1.10.14-wa-flujos'; // 2.19.1: message_authors · 2.27.0: whatsapp_flows
 /** Tenant único del motor: la agencia. Cada subcuenta del ERP es una conexión con id = id de la subcuenta. */
 public const AGENCIA=1;
 /** Rutas en vivo (GET o el método indicado). */
 private const RUTAS=[
  'workspace/inbox'=>['GET'],'workspace/messages'=>['GET'],'workspace/send'=>['POST'],'workspace/read'=>['POST'],'workspace/assign'=>['POST'],
  'workspace/conversation'=>['GET'],'workspace/contact'=>['GET'],'workspace/record'=>['GET','POST'],'workspace/contacts'=>['GET'],
  'workspace/contact-conversation'=>['POST'],'workspace/contact-fields'=>['GET'],'workspace/tags'=>['GET'],'workspace/templates'=>['GET'],
  'workspace/board'=>['GET'],'workspace/pipelines'=>['GET'],'workspace/opportunities'=>['GET'],'workspace/won'=>['GET'],'workspace/won-alert'=>['GET'],
  'workspace/dashboard'=>['GET'],'workspace/pulse'=>['GET'],'workspace/sync'=>['POST'],
  'workspace/dia'=>['GET'],'workspace/eficacia'=>['GET'],'workspace/ads'=>['GET'],
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
  // 2.6.1 · la foto diaria que da «frente a ayer» en Operación del día.
  \db()->exec("CREATE TABLE IF NOT EXISTS daily_metrics (connection_id BIGINT UNSIGNED NOT NULL,day DATE NOT NULL,kind VARCHAR(20) CHARACTER SET ascii NOT NULL,payload JSON NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (connection_id,day,kind),FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE)");
  // 2.19.1 · Quién envió desde Ágora cada mensaje (la cuenta lo anota como enviado por integración, sin usuario).
  \db()->exec("CREATE TABLE IF NOT EXISTS message_authors (connection_id BIGINT UNSIGNED NOT NULL,message_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,author_name VARCHAR(120) NOT NULL,external_user_id VARCHAR(80) CHARACTER SET ascii NULL,erp_user_id BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(connection_id,message_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
 /**
  * 2.9.6 · Cifras del mes del Tablero con la subcuenta conectada: las calcula el motor (lo ganado en la cuenta, lo invertido en anuncios,
  * el equipo importado y «mis ventas»), con la configuración del mes que se guarda en Ágora. Así el Tablero no se contradice.
  */
 public static function mesTablero(DB $erp,array $config,Auth $a,string $period):never{
  $tenant=(int)$a->tenant;self::instalar($erp,$config);self::arrancar($config);
  if(!\query('SELECT 1 FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetchColumn())self::sincronizar($erp,$config,$tenant);
  require_once dirname(__DIR__,3).'/motor/src/Board.php';
  $s=$erp->setting($tenant,'board.'.$period,[]);if(!is_array($s))$s=[];
  // Los montos guardados por Ágora pueden venir como texto («40.5»): se pasan como números para que el motor no los lea como 405.
  foreach(['otherAds','fixedCosts','teamGoal'] as $k)if(isset($s[$k])&&is_string($s[$k]))$s[$k]=is_numeric($s[$k])?(float)$s[$k]:null;
  if(is_array($s['goals']??null))foreach($s['goals'] as $id=>$g)if(is_string($g))$s['goals'][$id]=is_numeric($g)?(float)$g:null;
  // La comisión es la que se fija en Ajustes del ERP (integración de la subcuenta), no otra guardada en el motor.
  $cfg=$erp->setting($tenant,'integracion',[]);$hist=is_array($cfg['comisiones']??null)?$cfg['comisiones']:[];
  try{\query('DELETE FROM commission_rules WHERE connection_id=?',[$tenant]);foreach($hist as $r)if(is_array($r)&&in_array($r['kind']??'',['percent','per_closing'],true)&&preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',(string)($r['since']??''))&&is_numeric($r['amount']??null))\query('INSERT INTO commission_rules(connection_id,effective_from,kind,amount) VALUES(?,?,?,?)',[$tenant,$r['since'],$r['kind'],round((float)$r['amount'],2)]);}catch(\Throwable){}
  \Board::save($tenant,$period,$s);
  $_GET['period']=$period;
  $edita=in_array($a->user['role']??'',['MASTER','SUPERADMIN','ADMIN'],true);
  // 2.10.3 · Los costos por concepto (Costos) los guarda Ágora; el motor no los conoce: se añaden a su respuesta.
  $items=is_array($s['costItems']??null)?$s['costItems']:null;
  self::atender($erp,$config,$a,'workspace/board-month','GET',static function(array $b)use($edita,$items):array{if($items!==null&&is_array($b['settings']??null))$b['settings']['costItems']=$items;return ['canEdit'=>$edita]+$b;});
 }

 public static function atender(DB $erp,array $config,Auth $a,string $p,string $m,?\Closure $transformar=null):never{
  $user=$a->requireUser();$tenant=$a->tenant;
  self::instalar($erp,$config);self::arrancar($config);
  if(!\query('SELECT 1 FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetchColumn())self::sincronizar($erp,$config,$tenant);
  $restringido=$a->assignedOnly();
  $puede=static function(string $k)use($a):bool{try{$a->permit($k);return true;}catch(HttpError){return false;}};
  $escribe=$puede('conversaciones.atender');$reasigna=$puede('conversaciones.reasignar');
  // 2.4.0 · una tarea creada o completada desde la ficha del contacto queda también en el módulo Tareas (misma tarea, mismo identificador).
  $alActuar=static function(string $accion,string $contactId,array $input,array $r,array $conv)use($erp,$a,$user,$tenant):void{
   $rec=new \Agora\Services\Records($erp,$a);
   if($accion==='/task'&&($r['id']??'')!==''){
    $uid=self::uidTarea((string)$r['id']);if($erp->one("SELECT 1 x FROM agora_records WHERE tenant_id=? AND kind='task' AND uid=?",[$tenant,$uid]))return;
    $due=(string)($input['dueDate']??'');$fecha=preg_match('/^\d{4}-\d{2}-\d{2}/',$due)?substr($due,0,10):gmdate('Y-m-d');$hora=preg_match('/^\d{4}-\d{2}-\d{2}[T ](\d{2}:\d{2})/',$due,$x)?$x[1]:'';
    $titulo=mb_substr(trim((string)($input['title']??'Tarea')),0,250);
    $rec->save('task',$uid,['id'=>$uid,'titulo'=>$titulo,'detalle'=>mb_substr((string)($input['body']??''),0,2000),'vence'=>$fecha,'hora'=>$hora,'estado'=>'pendiente','responsable'=>$user['name'],'responsableId'=>(int)$user['id'],'origen'=>'ficha','contactId'=>$contactId,'conversationId'=>$conv['id']??null,'contacto'=>(string)($conv['contactName']??$conv['fullName']??''),'cuentaTareaId'=>(string)$r['id']],$titulo,'pendiente',substr($fecha,0,7),(int)$user['id']);
   }
   if($accion==='/task-state'&&is_string($input['taskId']??null)){
    $uid=self::uidTarea($input['taskId']);$row=$erp->one("SELECT * FROM agora_records WHERE tenant_id=? AND kind='task' AND uid=?",[$tenant,$uid]);if(!$row)return;
    $d=json_decode((string)$row['data'],true)?:[];$d['estado']=($input['completed']??false)?'hecha':'pendiente';
    $erp->run("UPDATE agora_records SET data=?,status=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE seq=?",[DB::json($d),$d['estado'],$row['seq']]);
   }
  };
  // 2.9.0 · espejo: cada respuesta de contactos u oportunidades queda guardada (sobrescribe por id de la cuenta).
  $espejo=new \Agora\Services\Espejo($erp,(int)$tenant);
  $GLOBALS['motorPuente']=['autor'=>['nombre'=>(string)($user['name']??''),'id'=>(int)$user['id']],'connectionId'=>$tenant,'transformar'=>$transformar,'alActuar'=>$alActuar,'alResponder'=>static fn(array $body)=>$espejo->deRespuesta($p,$body),'membership'=>function(array $connection)use($erp,$user,$restringido,$escribe,$reasigna):array{
   $full=\PermissionPolicy::capabilities(['identity_type'=>'native','role'=>'administrator']);
   // 2.4.0 · primero el enlace que eligió quien administra; si no hay, el del mismo correo.
   $enlace=(new \Agora\Services\UserLinks($erp))->forUser((int)$connection['id'],['id'=>$user['id'],'email'=>$user['email']]);
   $eu=$enlace?(\query('SELECT * FROM external_users WHERE connection_id=? AND active=TRUE AND external_user_id=? LIMIT 1',[$connection['id'],$enlace['id']])->fetch()?:null):null;
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

 /** Identificador local de una tarea que vive en la cuenta: siempre el mismo para la misma tarea, así no hay dobles. */
 public static function uidTarea(string $cuentaTareaId):string{return 'c'.substr(hash('sha256',$cuentaTareaId),0,23);}
 /**
  * 2.4.0 · Escribe en la cuenta una tarea creada, corregida o completada en Ágora.
  * $accion: crear | estado | editar. Devuelve ['id'=>id de la tarea en la cuenta].
  * Quien solo ve «lo asignado» solo puede tocar contactos asignados a su usuario de la cuenta.
  */
 public static function tarea(DB $erp,array $config,Auth $a,string $accion,string $contactId,array $input):array{
  $tenant=(int)$a->tenant;self::instalar($erp,$config);self::arrancar($config);
  if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))Http::fail('Elige un contacto válido.');
  $connection=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch();
  if(!$connection){self::sincronizar($erp,$config,$tenant);$connection=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch();}
  if(!$connection)Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  require_once dirname(__DIR__,3).'/motor/src/ContactActions.php';
  $gateway=new \GhlGateway();
  try{
   if($a->assignedOnly()){
    $enlace=(new \Agora\Services\UserLinks($erp))->forUser($tenant,['id'=>$a->user['id'],'email'=>$a->user['email']]);
    $c=$gateway->execute($connection,fn(\GhlClient $cl):array=>$cl->contact($contactId),true);
    if(!$enlace||(($c['contact']['assignedTo']??null)!==$enlace['id']))Http::fail('Ese contacto no está asignado a ti.',403);
   }
   $m=['capabilities'=>['editContacts'=>true]];$actions=new \ContactActions($gateway);
   $input['requestId']=is_string($input['requestId']??null)&&preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$input['requestId'])?$input['requestId']:bin2hex(random_bytes(16));
   if($accion==='crear'){$r=$actions->task($connection,$contactId,$m,$input);return ['id'=>(string)($r['id']??'')];}
   if($accion==='estado'){$actions->completeTask($connection,$contactId,$m,$input);return ['id'=>(string)$input['taskId']];}
   if($accion==='editar'){
    $task=(string)($input['taskId']??'');if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$task))Http::fail('Tarea inválida.');
    try{$due=(new \DateTimeImmutable((string)$input['dueDate']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}catch(\Exception){Http::fail('Fecha inválida.');}
    $payload=['title'=>mb_substr((string)$input['title'],0,190),'dueDate'=>$due,'completed'=>(bool)$input['completed'],'body'=>mb_substr((string)($input['body']??''),0,2000)];
    $gateway->execute($connection,fn(\GhlClient $cl):array=>$cl->updateContactTask($contactId,$task,$payload),false);
    \ContactActivity::forget((int)$connection['id'],$contactId);return ['id'=>$task];
   }
  }catch(\ActionProblem $e){Http::fail($e->getMessage(),$e->httpStatus);}
  catch(\GhlCooldown $e){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. La tarea todavía no se guardó: inténtalo otra vez.',429);}
  catch(\GhlApiException $e){Http::fail(in_array($e->status,[401,403],true)?'La cuenta rechazó la tarea por permisos. Revisa el permiso de escritura de contactos en la conexión.':'La cuenta no aceptó la tarea (código '.$e->status.'). Revisa los datos e inténtalo otra vez.',424);}
  Http::fail('Acción de tarea no disponible.',400);
 }

 /**
  * 2.6.0 · «Nuevo contacto»: lo crea en la cuenta de la subcuenta. Devuelve ['id'=>id del contacto en la cuenta].
  * Quien solo ve «lo asignado» lo recibe asignado a su usuario de la cuenta (si no, no lo vería después).
  */
 public static function contactoNuevo(DB $erp,array $config,Auth $a,array $b):array{
  $tenant=(int)$a->tenant;self::instalar($erp,$config);self::arrancar($config);
  $nombre=Http::text($b,'nombre',90);$apellido=Http::text($b,'apellido',90,false);
  $tel=preg_replace('/[\s().-]/','',Http::text($b,'telefono',40,false));$correo=mb_strtolower(Http::text($b,'correo',190,false));
  if($tel===''&&$correo==='')Http::fail('Escribe al menos el teléfono o el correo del contacto.');
  if($tel!==''&&!preg_match('/^\+?\d{7,15}$/D',$tel))Http::fail('El teléfono no es válido. Escríbelo con el código del país, por ejemplo +584141234567.');
  if($correo!==''&&!filter_var($correo,FILTER_VALIDATE_EMAIL))Http::fail('El correo no es válido.');
  $connection=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch();
  if(!$connection){self::sincronizar($erp,$config,$tenant);$connection=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch();}
  if(!$connection)Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  $payload=['locationId'=>(string)$connection['location_id'],'firstName'=>$nombre,'name'=>trim($nombre.' '.$apellido),'source'=>'Ágora']+($apellido!==''?['lastName'=>$apellido]:[])+($tel!==''?['phone'=>$tel]:[])+($correo!==''?['email'=>$correo]:[]);
  $enlace=(new \Agora\Services\UserLinks($erp))->forUser($tenant,['id'=>$a->user['id'],'email'=>$a->user['email']]);
  if($a->assignedOnly()){if(!$enlace)Http::fail('Tu usuario no está enlazado con la cuenta. Pídele a quien administra tu subcuenta que te enlace.',403);$payload['assignedTo']=$enlace['id'];}
  elseif(($b['asignarme']??false)===true&&$enlace)$payload['assignedTo']=$enlace['id'];
  try{$r=(new \GhlGateway())->execute($connection,fn(\GhlClient $cl):array=>$cl->createContact($payload),false);}
  catch(\GhlCooldown $e){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. El contacto todavía no se creó: inténtalo otra vez.',429);}
  catch(\GhlApiException $e){Http::fail(match(true){in_array($e->status,[401,403],true)=>'La cuenta rechazó el contacto por permisos. Revisa el permiso de escritura de contactos en la conexión.',in_array($e->status,[400,409,422],true)=>'La cuenta no aceptó el contacto. Lo más probable es que ya exista uno con ese teléfono o ese correo: búscalo en Contactos.',default=>'La cuenta no respondió bien (código '.$e->status.'). Inténtalo otra vez en un momento.'},in_array($e->status,[400,409,422],true)?409:424);}
  $id=(string)($r['contact']['id']??'');if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))Http::fail('La cuenta no confirmó el contacto. Búscalo en Contactos antes de repetirlo.',424);
  // La lista de contactos se guarda un minuto: se suelta para que el nuevo aparezca enseguida.
  try{\query('DELETE FROM directory_cache WHERE connection_id=?',[$tenant]);}catch(\Throwable){}
  $a->audit('contacto.creado','contacto',$id,['conTelefono'=>$tel!=='','conCorreo'=>$correo!=='']);
  return ['ok'=>true,'id'=>$id,'nombre'=>$payload['name']];
 }

 /* ---------- 2.9.0 · Venta ganada con la cuenta: la oportunidad y el contacto viven en la cuenta; el espejo guarda la última lectura. ---------- */
 /**
  * 2.13.0 · Correo de Ágora: sale por la subcuenta que el dueño eligió (Agencia → Correo de Ágora), por el canal de
  * correo de esa cuenta. Sin SMTP. Devuelve false si no hay canal elegido; lanza RuntimeException con un mensaje claro si falla.
  */
 /**
  * 2.14.0 · Widgets: lee por dentro una ruta que con la subcuenta conectada atiende el motor (la misma cifra que ve la tarjeta).
  * Devuelve ['status','body'] o null si esa ruta no la atiende el motor (entonces se usa el despachador normal del ERP).
  */
 public static function capturar(DB $erp,array $config,Auth $a,string $path,array $get):?array{
  $tenant=(int)$a->tenant;$vivo=$path==='workspace/board-month'||self::atiende($path,'GET');
  if(!$vivo||!self::conectada($erp,$tenant))return null;
  $g=$_GET;$puente=$GLOBALS['motorPuente']??null;$_GET=$get;$GLOBALS['motorCaptura']=true;unset($GLOBALS['motorCapturado']);
  try{$a->permit('datos.consultar');if($path==='workspace/board-month')self::mesTablero($erp,$config,$a,Http::period((string)($get['period']??gmdate('Y-m'))));else self::atender($erp,$config,$a,$path,'GET');}
  catch(\MotorCaptura $c){return ['status'=>$c->status,'body'=>$c->body];}
  catch(HttpError $e){return ['status'=>$e->getCode()?:400,'body'=>['error'=>$e->getMessage()]];}
  finally{$_GET=$g;unset($GLOBALS['motorCaptura'],$GLOBALS['motorCapturado']);if($puente!==null)$GLOBALS['motorPuente']=$puente;else unset($GLOBALS['motorPuente']);}
  return null;
 }
 public static function canalCorreo(DB $erp):?int{$c=$erp->setting(0,'correo.canal',null);$id=is_array($c)?(int)($c['connectionId']??0):0;return $id>0?$id:null;}
 public static function correo(DB $erp,array $config,string $email,string $asunto,string $titulo,string $intro,?string $codigo,string $pie,string $texto,?array $boton=null):bool{
  $id=self::canalCorreo($erp);if(!$id)return false;
  self::conexion($erp,$config,$id);
  require_once dirname(__DIR__,3).'/motor/src/AccessChannel.php';
  \AccessChannel::select(self::AGENCIA,$id);
  $base=rtrim((string)($config['app_url']??''),'/');$logo=str_starts_with($base,'https://')?$base.'/assets/correo/agora-logo-correo.png':null; // 2.18.14: logo del correo (solo con dirección https pública)
  try{(new \AccessChannel())->sendMail(self::AGENCIA,$email,$asunto,$titulo,$intro,$codigo,$pie,$texto,$logo,$boton);}
  catch(\AccessDeliveryProblem $e){throw new \RuntimeException($e->getMessage(),0,$e);}
  return true;
 }
 /**
  * 2.20.0 · Correo a un cliente (convenio para firmar, factura). Sale por la propia subcuenta si está conectada (el cliente recibe
  * el correo de la empresa con la que trata); si no, por el Correo de Ágora. Nunca lanza: devuelve enviado, por dónde o el motivo.
  */
 public static function correoCliente(DB $erp,array $config,int $tenant,string $email,string $nombre,string $asunto,string $titulo,string $intro,array $boton,string $pie,string $texto):array{
  $motivos=['access_channel_unverified'=>'La subcuenta que envía no está comprobada.','contact_unresolvable'=>'No se pudo preparar el contacto del cliente en la cuenta.',
   'insufficient_permissions'=>'La conexión de la cuenta no tiene permiso para crear contactos o enviar correos.','email_channel_unavailable'=>'La cuenta no tiene el correo disponible para enviar en este momento.',
   'delivery_rejected'=>'La cuenta rechazó el envío del correo.','delivery_paused'=>'La cuenta pidió esperar unos segundos antes de enviar otro correo.','member_without_email'=>'El correo del cliente no es válido.'];
  try{
   self::instalar($erp,$config);self::arrancar($config);
   require_once dirname(__DIR__,3).'/motor/src/AccessChannel.php';
   $c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch()?:null;$via='subcuenta';
   if(!$c&&self::conectada($erp,$tenant)){self::sincronizar($erp,$config,$tenant);$c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch()?:null;}
   if(!$c||$c['status']!=='connected'){$id=self::canalCorreo($erp);$c=$id?(\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$id,self::AGENCIA])->fetch()?:null):null;$via='agora';}
   if(!$c||$c['status']!=='connected')return ['enviado'=>false,'motivo'=>'Ni esta subcuenta ni el Correo de Ágora están conectados para enviar correos.'];
   $base=rtrim((string)($config['app_url']??''),'/');$logo=str_starts_with($base,'https://')?$base.'/assets/correo/agora-logo-correo.png':null;
   $mid=(new \AccessChannel())->enviarDocumento($c,$email,$nombre,$asunto,$titulo,$intro,$boton,$pie,$texto,$logo);
   return ['enviado'=>true,'via'=>$via,'mensajeId'=>$mid];
  }catch(\AccessDeliveryProblem $e){return ['enviado'=>false,'motivo'=>$motivos[$e->reason]??'No se pudo enviar el correo.'];}
  catch(\Throwable $e){error_log('Agora correoCliente: '.get_class($e));return ['enviado'=>false,'motivo'=>'No se pudo enviar el correo.'];}
 }
 /**
  * 2.25.0 · Plantillas de WhatsApp de una subcuenta (Agencia → subcuenta y Ajustes): ver lo guardado, guardar el identificador del número
  * (y, si hace falta, el de la cuenta de WhatsApp Business) en la conexión del motor, y comprobar con la cuenta en ese momento.
  * Es lo mismo que usa la Bandeja para escribir pasadas las 24 horas. Sirve para verificar que la conexión funciona.
  */
 /** 2.27.0 · Plantillas de WhatsApp por flujos de trabajo (solo dueños, en Agencia): ver los elegidos, buscar en la cuenta y guardar. */
 public static function whatsapp(DB $erp,array $config,int $tenant,string $accion,array $b=[]):array{
  if(!self::conectada($erp,$tenant))Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  $c=self::conexion($erp,$config,$tenant);
  require_once dirname(__DIR__,3).'/motor/src/WhatsappFlows.php';
  $ver=fn():array=>['flujos'=>\WhatsappFlows::chosen((int)$c['id']),'resumen'=>\WhatsappFlows::summary((int)$c['id'],time()-30*86400,time()),'maximo'=>\WhatsappFlows::MAX];
  try{
   if($accion==='ver')return $ver();
   if($accion==='buscar'){$q=mb_substr(trim((string)($b['buscar']??'')),0,80);$lista=(new \WhatsappFlows())->account($c,$q);return ['cuenta'=>array_slice($lista,0,200),'total'=>count($lista),'buscar'=>$q];}
   if($accion==='guardar'){if(!is_array($b['flujos']??null))Http::fail('Falta la lista de flujos.',422);(new \WhatsappFlows())->save($c,$b['flujos']);return $ver();}
  }catch(\FlowProblem $e){if($e->retryAfter)header('Retry-After: '.$e->retryAfter);Http::fail($e->getMessage(),$e->httpStatus);}
  Http::fail('Acción desconocida.',400);
 }
 private static function conexion(DB $erp,array $config,int $tenant):array{
  self::instalar($erp,$config);self::arrancar($config);
  $c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch();
  if(!$c){self::sincronizar($erp,$config,$tenant);$c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$tenant,self::AGENCIA])->fetch();}
  if(!$c)Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  return $c;
 }
 private static function falla(\Throwable $e,string $que):never{
  if($e instanceof \GhlCooldown){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. '.$que.' todavía no se guardó: inténtalo otra vez.',429);}
  if($e instanceof \GhlApiException){if($e->status===404)Http::fail('La cuenta no encuentra esa oportunidad. Puede que la hayan borrado.',404);Http::fail(in_array($e->status,[401,403],true)?'La cuenta rechazó la operación por permisos. Revisa el permiso de oportunidades en la conexión.':'La cuenta no aceptó la operación (código '.$e->status.').',424);}
  throw $e;
 }
 /** La oportunidad y su contacto, leídos de la cuenta (y guardados en el espejo). */
 public static function oportunidadCuenta(DB $erp,array $config,int $tenant,string $id):array{
  if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))Http::fail('Oportunidad inválida.');
  $c=self::conexion($erp,$config,$tenant);$g=new \GhlGateway();$espejo=new \Agora\Services\Espejo($erp,$tenant);
  try{$op=$g->execute($c,fn(\GhlClient $cl):array=>$cl->opportunity($id),false,true)['opportunity']??null;}/* 2.12.15 · [10]: lectura suave, un 404 no pausa la subcuenta */catch(\GhlApiException $e){if($e->status===404)Http::fail('La cuenta no encuentra esa oportunidad.',404);self::falla($e,'La venta');}catch(\Throwable $e){self::falla($e,'La venta');}
  if(!is_array($op)||($op['id']??'')!==$id)Http::fail('La cuenta no encuentra esa oportunidad.',404);
  if(isset($op['locationId'])&&$op['locationId']!==$c['location_id'])Http::fail('Esa oportunidad no es de esta subcuenta.',403);
  $espejo->oportunidad($op);
  $ct=null;$cid=(string)($op['contactId']??($op['contact']['id']??''));
  if($cid!==''){try{$ct=$g->execute($c,fn(\GhlClient $cl):array=>$cl->contact($cid),false)['contact']??null;if(is_array($ct))$espejo->contacto($ct);}catch(\GhlCooldown $e){self::falla($e,'La venta');}catch(\Throwable){$ct=null;}}
  return ['op'=>$op,'contacto'=>is_array($ct)?$ct:null];
 }
 /** Marca la oportunidad como ganada en la cuenta, con el monto de la venta. */
 public static function ganarEnCuenta(DB $erp,array $config,int $tenant,string $id,float $monto):array{
  $c=self::conexion($erp,$config,$tenant);$g=new \GhlGateway();
  try{
   $g->execute($c,fn(\GhlClient $cl):array=>$cl->updateOpportunityStatus($id,['status'=>'won']),false);
   $r=$g->execute($c,fn(\GhlClient $cl):array=>$cl->updateOpportunity($id,['monetaryValue'=>$monto]),false);
  }catch(\Throwable $e){self::falla($e,'La venta');}
  $op=is_array($r['opportunity']??null)?$r['opportunity']:['id'=>$id];
  $op=['status'=>'won','monetaryValue'=>$monto,'wonAt'=>gmdate('c')]+$op;$op['status']='won';
  (new \Agora\Services\Espejo($erp,$tenant))->oportunidad($op);
  // Las ganadas del mes se vuelven a leer en la próxima consulta.
  try{\query("DELETE FROM directory_cache WHERE connection_id=? AND cache_key LIKE 'won:%'",[$tenant]);}catch(\Throwable){}
  return $op;
 }
 /** Corrige el monto de una oportunidad ganada en la cuenta (al editar la venta). No interrumpe si falla. */
 public static function montoEnCuenta(DB $erp,array $config,int $tenant,string $id,float $monto):void{
  try{$c=self::conexion($erp,$config,$tenant);$r=(new \GhlGateway())->execute($c,fn(\GhlClient $cl):array=>$cl->updateOpportunity($id,['monetaryValue'=>$monto]),false,true);if(is_array($r['opportunity']??null))(new \Agora\Services\Espejo($erp,$tenant))->oportunidad($r['opportunity']);}catch(\Throwable){}
 }
 /** Completa en la cuenta el correo o el teléfono del contacto si la cuenta no los tenía. No borra ni reemplaza nada. No interrumpe si falla. */
 public static function completarContacto(DB $erp,array $config,int $tenant,array $ct,array $cliente):void{
  $id=(string)($ct['id']??'');if($id==='')return;$p=[];
  if(empty($ct['email'])&&($cliente['correo']??'')!=='')$p['email']=$cliente['correo'];
  if(empty($ct['phone'])&&($cliente['telefono']??'')!=='')$p['phone']=$cliente['telefono'];
  if(!$p)return;
  try{$c=self::conexion($erp,$config,$tenant);$r=(new \GhlGateway())->execute($c,fn(\GhlClient $cl):array=>$cl->updateContact($id,$p),false,true);if(is_array($r['contact']??null))(new \Agora\Services\Espejo($erp,$tenant))->contacto($r['contact']);}catch(\Throwable){}
 }
 /** Ganadas del mes según la cuenta (lectura guardada unos minutos por el motor) y escritas en el espejo. */
 public static function ganadasMes(DB $erp,array $config,int $tenant,string $ym):array{
  try{$c=self::conexion($erp,$config,$tenant);require_once dirname(__DIR__,3).'/motor/src/WonSales.php';$m=(new \WonSales())->month($c,$ym);}
  catch(\Throwable){return (new \Agora\Services\Espejo($erp,$tenant))->ganadas($ym);}
  $espejo=new \Agora\Services\Espejo($erp,$tenant);
  foreach($m['records']??[] as $o)if(is_array($o))$espejo->oportunidad(['id'=>$o['id'],'name'=>$o['name']??'','contactName'=>$o['contactName']??null,'assignedTo'=>$o['assignedTo']??null,'monetaryValue'=>$o['value']??0,'status'=>'won','wonAt'=>$o['wonAt']??null]+(($prev=$espejo->op((string)$o['id']))?['contactId'=>$prev['contacto_id'],'pipelineId'=>$prev['embudo_id'],'pipelineStageId'=>$prev['etapa_id']]:[]));
  return $espejo->ganadas($ym);
 }
 /** La persona de Ágora enlazada con un usuario de la cuenta (o null). */
 public static function usuarioDe(DB $erp,int $tenant,?string $externo):?int{
  if(!$externo)return null;
  $users=$erp->all('SELECT id,email FROM agora_users WHERE local_tenant_id=? AND active=1',[$tenant]);
  foreach((new \Agora\Services\UserLinks($erp))->forUsers($tenant,$users) as $uid=>$l)if($l&&$l['id']===$externo)return (int)$uid;
  return null;
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
  if($p==='admin/consumption/reglas'&&in_array($m,['POST','DELETE'],true)){$rb=\body();$a->audit($m==='POST'?'consumos.regla':'consumos.regla.quitada','agencia','consumos',['dato'=>mb_substr((string)($rb['campo']??''),0,120),'subcuenta'=>(string)($rb['locationId']??'')]);}
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
