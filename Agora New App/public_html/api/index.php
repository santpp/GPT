<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
ini_set('display_errors','0');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
// 2.12.14 · Cabeceras de seguridad propias de la API (por si el servidor no aplica las del .htaccess).
header_remove('X-Powered-By');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");header('Cross-Origin-Resource-Policy: same-origin');header('Cross-Origin-Opener-Policy: same-origin');header('X-Permitted-Cross-Domain-Policies: none');header('Permissions-Policy: camera=(), geolocation=(), payment=()');
if((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
$requestId=bin2hex(random_bytes(8));header('X-Request-ID: '.$requestId);
/* 2.19.2 · Una sola puerta para los errores: pone el código del diccionario (AG-ÁREA-NNN) y los anota en la bitácora con su referencia.
   La usan el manejo de errores de abajo y el motor de la cuenta (su respond()), así ningún error sale sin código. Nunca rompe la respuesta. */
$GLOBALS['agoraError']=static function(int $status,string $message,?\Throwable $e=null)use(&$requestId):string{
 $codigo='AG-SIS-500';
 try{
  $ruta=(string)($GLOBALS['path']??'');$codigo=\Agora\Core\Errores::codigo($ruta,$status,$message)['codigo'];
  $api=$GLOBALS['api']??null;$db=$GLOBALS['db']??null;$quien=$api?($api->a->user??null):null;
  if($db&&($status>=500||($quien&&$status!==401))&&empty($GLOBALS['motorCaptura'])){
   $tec=$e&&$status>=500?mb_substr(preg_replace('/[A-Za-z0-9_\-]{32,}/','…',get_class($e).': '.$e->getMessage().' · '.basename($e->getFile()).':'.$e->getLine()),0,800):null;
   $db->run('INSERT INTO agora_error_log(referencia,codigo,status,metodo,ruta,user_id,tenant_id,mensaje,tecnico,ip) VALUES(?,?,?,?,?,?,?,?,?,?)',
    [$requestId,$codigo,$status,substr((string)($GLOBALS['method']??''),0,8),mb_substr($ruta,0,160),$quien?(int)$quien['id']:null,$api&&($api->a->tenant??null)?(int)$api->a->tenant:null,mb_substr($message,0,300),$tec,\Agora\Security\Auth::ipCliente()]);
   if(random_int(1,100)===1)$db->run('DELETE FROM agora_error_log WHERE created_at<UTC_TIMESTAMP()-INTERVAL 30 DAY');
  }
 }catch(\Throwable){}
 return $codigo;
};
try{
 /* 2.1.12 · Diagnóstico de arranque. Si algo del servidor falla, /api/status y /api/health dicen QUÉ falla
    (sin revelar claves), en lugar de mostrar solo la pantalla de mantenimiento. */
 $path=trim(substr((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),strlen('/api/')),'/');$method=$_SERVER['REQUEST_METHOD']??'GET';
 $probe=$method==='GET'&&in_array($path,['status','health'],true);
 $setup=static function(string $code,string $problema,string $paso)use($requestId){http_response_code(503);header('Content-Type: application/json; charset=utf-8');echo json_encode(['maintenance'=>false,'code'=>'setup','check'=>$code,'problema'=>$problema,'paso'=>$paso,'requestId'=>$requestId],JSON_UNESCAPED_UNICODE);exit;};
 if(PHP_VERSION_ID<80100)$setup('php','El servidor usa PHP '.PHP_VERSION.'.','Elige PHP 8.3 para el sitio en CyberPanel (Websites → Manage → PHP).');
 foreach(['pdo','mbstring','openssl','json'] as $ext)if(!extension_loaded($ext))$setup('ext','Falta la extensión PHP '.$ext.'.','Instálala en AlmaLinux: dnf install lsphp83-'.($ext==='pdo_mysql'?'mysqlnd':$ext).' y reinicia: systemctl restart lsws');
 $bootstrap=null;foreach(array_filter([getenv('AGORA_SERVER_DIR')?:null,dirname(__DIR__,2).'/servidor',dirname(__DIR__).'/servidor']) as $dir){if(is_file($dir.'/bootstrap.php')){$bootstrap=$dir.'/bootstrap.php';break;}}
 if(!$bootstrap)$setup('servidor','No se encontró la carpeta servidor/.','Sube servidor/ al lado de public_html/ (ej.: /home/tudominio.com/servidor), no adentro.');
 if(str_starts_with(realpath($bootstrap)?:'',(realpath(dirname(__DIR__))?:'/nada').DIRECTORY_SEPARATOR))$setup('expuesto','La carpeta servidor/ está dentro de public_html y quedaría pública.','Muévela un nivel arriba, al lado de public_html/.');
 try{require $bootstrap;}catch(\Agora\Core\HttpError $e){if($probe)$setup('config',$e->getMessage(),'Copia servidor/config/config.example.php a config.php y completa la base de datos y la app_key (64 caracteres hexadecimales).');throw $e;}
 $pruebas=!empty($config['modo_pruebas']);
 if($pruebas&&!extension_loaded('pdo_sqlite'))$setup('ext','Modo pruebas: falta la extensión PHP pdo_sqlite.','Instálala en AlmaLinux: dnf install lsphp83-pdo lsphp83-sqlite3 y reinicia: systemctl restart lsws');
 if(!$pruebas&&!extension_loaded('pdo_mysql'))$setup('ext','Falta la extensión PHP pdo_mysql.','Instálala en AlmaLinux: dnf install lsphp83-mysqlnd y reinicia: systemctl restart lsws');
 if($pruebas&&!is_writable(\Agora\Core\Pruebas::storage()))$setup('storage','Modo pruebas: PHP no puede escribir en servidor/storage/.','Dale la carpeta al usuario del sitio: chown -R USUARIO_DEL_SITIO: /home/TU-DOMINIO/servidor/storage');
 try{$db=new \Agora\Core\DB($config['db']);}catch(\PDOException $e){error_log('Agora '.$requestId.' DB '.$e->getMessage());$setup('db','No se pudo conectar a la base de datos MySQL/MariaDB.','Revisa host (localhost), nombre, usuario y clave en servidor/config/config.php, tal como los creaste en CyberPanel → Databases.');}
 if($pruebas){try{\Agora\Core\Pruebas::preparar($db,$config);}catch(\Throwable $e){error_log('Agora '.$requestId.' pruebas '.$e->getMessage());$setup('pruebas','Modo pruebas: no se pudo preparar la base SQLite.','Revisa el log de PHP del sitio (referencia '.$requestId.'). Para empezar de cero, borra servidor/storage/agora-pruebas.sqlite.');}}
 if($probe&&$method==='GET'&&$path==='status'&&$pruebas){header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['maintenance'=>false,'modo'=>'pruebas','usuario'=>'admin','clave'=>\Agora\Core\Pruebas::CLAVE],JSON_UNESCAPED_UNICODE);exit;}
 // Agora New App: las migraciones nuevas se aplican solas (idempotentes, con bloqueo y checksum) si la base ya está instalada.
 if(!$pruebas){try{$ultima=basename((string)(array_slice(glob(dirname(__DIR__,2).'/servidor/app/database/migrations/*.sql')?:[],-1)[0]??'005_enlaces.sql'));if(!$db->one('SELECT 1 ok FROM agora_schema WHERE version=?',[$ultima]))\Agora\Core\Installer::migrate($db);}catch(\PDOException $e){}} // 2.9.0: la última migración que exista, no un nombre fijo
 if($probe){try{$db->one('SELECT 1 FROM agora_tenants LIMIT 1');}catch(\PDOException $e){$setup('migraciones','La base de datos está vacía o incompleta.','Abre /install.php (primera vez) o ejecuta: php servidor/bin/migrate.php');}}
 $api=new \Agora\Controllers\Api($db,$config);
 if(!in_array($method,['GET','POST','PUT','DELETE'],true))\Agora\Core\Http::fail('Método no permitido.',405);
 $body=$method==='GET'?[]:\Agora\Core\Http::body();
 if($method!=='GET')$api->a->assertCsrf();
 // 2.19.0 · Cifras de la cuenta más rápidas: una copia de 30 s por persona, rol y subcuenta de las consultas pesadas del Dashboard.
 // Al recargar o volver al módulo salen al instante. Cualquier cambio guardado en la subcuenta borra las copias (nunca se ve un dato
 // viejo después de editar). Se guardan en servidor/storage/cache-vivo (fuera de la parte pública) y se limpian solas.
 $vivoDir=dirname($bootstrap).'/storage/cache-vivo';
 if($api->a->user&&$method!=='GET'){$tw=(int)($_SERVER['HTTP_X_WORKSPACE_ID']??0);if($tw>0&&is_dir($vivoDir))@file_put_contents("$vivoDir/gen-$tw",bin2hex(random_bytes(6)));}
 if($api->a->user&&$method==='GET'&&in_array($path,['workspace/dashboard','workspace/board-month','workspace/dia','workspace/ads','workspace/eficacia','workspace/campanas','workspace/indicadores'],true)){
  try{
   $tc=(int)$api->a->workspace();
   if($tc>0&&(is_dir($vivoDir)||@mkdir($vivoDir,0770,true))){
    $gen=@file_get_contents("$vivoDir/gen-$tc")?:'0';$q=array_filter($_GET,fn($v,$k)=>$v!==''&&!($k==='connectionId'&&(int)$v===$tc),ARRAY_FILTER_USE_BOTH);ksort($q);$u=$api->a->user;
    $vivoF=$vivoDir.'/'.hash('sha256',implode('|',[$path,http_build_query($q),$u['id'],$u['role'],$u['auth_version']??'',$tc,$gen])).'.json';
    if(is_file($vivoF)&&time()-filemtime($vivoF)<30){header('Content-Type: application/json; charset=utf-8');header('X-Agora-Copia: 1');readfile($vivoF);exit;}
    if(random_int(1,200)===1)foreach(glob("$vivoDir/*.json")?:[] as $viejo)if(time()-@filemtime($viejo)>300)@unlink($viejo);
    ob_start(static function(string $buf)use($vivoF):string{if(http_response_code()===200&&$buf!==''&&strlen($buf)<3000000&&($buf[0]==='{'||$buf[0]==='[')){@file_put_contents($vivoF.'.tmp',$buf);@rename($vivoF.'.tmp',$vivoF);}return $buf;});
   }
  }catch(\Agora\Core\HttpError){}
 }
 // Agora New App: lo que habla en vivo con la cuenta lo atiende el motor de Ágora Mobile (si la subcuenta está conectada).
 if(\Agora\Core\MotorMobile::consumos($path)){if($method!=='GET')$api->a->rate('writes:'.($api->a->user['id']??0),120,60);\Agora\Core\MotorMobile::atenderConsumos($db,$config,$api->a,$path,$method);}
 if($api->a->user&&\Agora\Core\MotorMobile::atiende($path,$method)){
  $tenantVivo=$api->a->workspace();
  if(\Agora\Core\MotorMobile::conectada($db,$tenantVivo)){
   $api->a->permit($method==='GET'?'datos.consultar':($path==='workspace/assign'?'conversaciones.reasignar':'conversaciones.atender'));
   if($method!=='GET')$api->a->rate('writes:'.$api->a->user['id'],120,60);
   \Agora\Core\MotorMobile::atender($db,$config,$api->a,$path,$method);
  }
 }
 // 2.9.6 · Cifras del mes del Tablero con la subcuenta conectada: las calcula el motor con la configuración del mes de Ágora.
 if($api->a->user&&$path==='workspace/board-month'&&$method==='GET'){$tenantMes=$api->a->workspace();if(\Agora\Core\MotorMobile::conectada($db,$tenantMes)){$api->a->permit('datos.consultar');\Agora\Core\MotorMobile::mesTablero($db,$config,$api->a,\Agora\Core\Http::period($_GET['period']??gmdate('Y-m')));}}
 if($path==='sign')$api->a->rate('sign:'.($_SERVER['REMOTE_ADDR']??''),100,300);
 if($api->a->user&&$method!=='GET')$api->a->rate('writes:'.$api->a->user['id'],120,60);
 $call=fn()=>$api->dispatch($path,$method,$body);
 // 2.18.15 · Los pasos de acceso van fuera de la transacción: si fallan (código equivocado), sus intentos, límites y el registro de accesos
 // tienen que quedar guardados. Antes se deshacían con el error y el tope de 5 intentos y los límites por IP no contaban.
 // 2.26.0 · «Activar protección» de facturación crea disparadores (DDL): eso confirma la transacción por su cuenta y al cerrarla daba
 // 500 aunque la protección quedaba instalada. Va fuera de la transacción, como el acceso.
 if($method!=='GET'&&!in_array($path,['login','login/codigo','login/reenviar','clave/olvido','clave/restablecer','workspace/fiscal/proteccion'],true)){
  $result=$db->transaction(function()use($api,$db,$path,$method,$body,$call){
   $key=$_SERVER['HTTP_IDEMPOTENCY_KEY']??$body['requestId']??null;$t=(int)($_SERVER['HTTP_X_WORKSPACE_ID']??0);$u=(int)($api->a->user['id']??0);
   if($key!==null&&$u&&$t){if(!is_string($key)||!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key))\Agora\Core\Http::fail('Clave de operación inválida.');$api->a->authorizeTenant($t);$hash=hash('sha256',$method.$path.\Agora\Core\DB::json($body));$old=$db->one('SELECT * FROM agora_idempotency WHERE tenant_id=? AND user_id=? AND request_key=? FOR UPDATE',[$t,$u,$key]);if($old){if(!hash_equals($old['request_hash'],$hash))\Agora\Core\Http::fail('Clave de operación reutilizada con otros datos.',409);return json_decode($old['response'],true,512,JSON_THROW_ON_ERROR);}$db->run('INSERT INTO agora_idempotency(tenant_id,user_id,request_key,request_hash) VALUES(?,?,?,?)',[$t,$u,$key,$hash]);$out=$call();$db->run('UPDATE agora_idempotency SET response=? WHERE tenant_id=? AND user_id=? AND request_key=?',[\Agora\Core\DB::json($out),$t,$u,$key]);return $out;}
   return $call();
  });
 }else $result=$call();
 \Agora\Core\Http::response($result);
}catch(\Throwable $e){
 if(isset($db)&&$db->pdo->inTransaction())$db->pdo->rollBack();
 $status=$e instanceof \Agora\Core\HttpError?$e->getCode():500;$message=$status===500?'No se pudo completar la operación. Referencia: '.$requestId:$e->getMessage();
 if($e instanceof \PDOException&&$e->getCode()==='23000'){$status=409;$message='El registro ya existe o está relacionado con otros datos.';}
 if($status===500)error_log('Agora '.$requestId.' '.get_class($e).' '.$e->getMessage());
 // 2.19.0 · Diccionario de errores y bitácora (2.19.2: con la función común de arriba).
 $codigo=($GLOBALS['agoraError'])($status,$message,$e);
 http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message,'requestId'=>$requestId,'codigo'=>$codigo],JSON_UNESCAPED_UNICODE);
}
