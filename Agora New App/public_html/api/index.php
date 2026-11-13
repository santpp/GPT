<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
$requestId=bin2hex(random_bytes(8));header('X-Request-ID: '.$requestId);
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
 if(!$pruebas){try{if(!$db->one('SELECT 1 ok FROM agora_schema WHERE version=?',['004_ghl_conexion.sql']))\Agora\Core\Installer::migrate($db);}catch(\PDOException $e){}}
 if($probe){try{$db->one('SELECT 1 FROM agora_tenants LIMIT 1');}catch(\PDOException $e){$setup('migraciones','La base de datos está vacía o incompleta.','Abre /install.php (primera vez) o ejecuta: php servidor/bin/migrate.php');}}
 $api=new \Agora\Controllers\Api($db,$config);
 if(!in_array($method,['GET','POST','PUT','DELETE'],true))\Agora\Core\Http::fail('Método no permitido.',405);
 $body=$method==='GET'?[]:\Agora\Core\Http::body();
 if($method!=='GET')$api->a->assertCsrf();
 if($path==='sign')$api->a->rate('sign:'.($_SERVER['REMOTE_ADDR']??''),100,300);
 if($api->a->user&&$method!=='GET')$api->a->rate('writes:'.$api->a->user['id'],120,60);
 $call=fn()=>$api->dispatch($path,$method,$body);
 if($method!=='GET'&&$path!=='login'){
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
 http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message,'requestId'=>$requestId],JSON_UNESCAPED_UNICODE);
}
