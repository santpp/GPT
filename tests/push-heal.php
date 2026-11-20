<?php
declare(strict_types=1);
// 1.10.34 · «¿Sigue este navegador registrado?» se contesta a cualquiera (antes el diagnóstico de la propietaria la tapaba:
// a una vendedora le daba 403 y la app rehacía sus avisos en cada visita, hasta perderlos en Android).
require __DIR__.'/../src/bootstrap.php';
$n=0;function ck(bool $v,string $l):void{global $n;if(!$v){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
$suffix=bin2hex(random_bytes(5));$tenant=null;$cookie=tempnam(sys_get_temp_dir(),'agora-push-');$csrf='';
function hc(string $path,string $method='GET',?array $body=null):array{global $cookie,$csrf;
 $ch=curl_init('http://localhost:8080/api/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Origin: http://localhost:8080','X-CSRF-Token: '.$csrf]]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return [$code,json_decode((string)$raw,true)??[]];}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Push '.$suffix,'push-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $pw=bin2hex(random_bytes(10));
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,'o-'.$suffix.'@example.test',password_hash($pw,PASSWORD_DEFAULT)]);
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'seller')",[$tenant,'s-'.$suffix.'@example.test',password_hash($pw,PASSWORD_DEFAULT)]);
 $ep='https://fcm.googleapis.com/fcm/send/'.$suffix.'-'.bin2hex(random_bytes(40));
 $keys=['p256dh'=>rtrim(strtr(base64_encode(random_bytes(65)),'+/','-_'),'='),'auth'=>rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=')];
 foreach(['s-'=>'vendedora','o-'=>'propietaria'] as $p=>$who){
  [$c,$d]=hc('login','POST',['email'=>$p.$suffix.'@example.test','password'=>$pw]);$csrf=$d['csrf']??'';ck($c===200,"la $who entra");
  [$c,$d]=hc('devices?token='.rawurlencode($ep.$p));ck($c===200&&($d['registered']??null)===false,"a la $who se le contesta «no registrado» (antes: ".($p==='s-'?'403':'la lista del diagnóstico').')');
  [$c,$d]=hc('devices','POST',['token'=>$ep.$p,'platform'=>'web','label'=>'Android','keys'=>$keys]);ck($c===200&&$d['registered'],"la $who registra su teléfono");
  [$c,$d]=hc('devices?token='.rawurlencode($ep.$p));ck($c===200&&$d['registered']===true,"y la pregunta confirma que sigue registrado");
  hc('logout','POST',[]);
 }
 [$c,$d]=hc('login','POST',['email'=>'o-'.$suffix.'@example.test','password'=>$pw]);$csrf=$d['csrf']??'';
 [$c,$d]=hc('devices');ck($c===200&&isset($d['items'])&&count($d['items'])===2,'el diagnóstico de la propietaria sigue funcionando (sin token)');
 hc('logout','POST',[]);
 [$c,$d]=hc('login','POST',['email'=>'s-'.$suffix.'@example.test','password'=>$pw]);$csrf=$d['csrf']??'';
 ck(hc('devices')[0]===403,'y la vendedora sigue sin ver el diagnóstico');
 echo "$n comprobaciones\n";
}finally{
 if($tenant){query('DELETE FROM push_devices WHERE tenant_id=?',[$tenant]);query('DELETE FROM device_sessions WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
 foreach(['o-','s-'] as $p)query('DELETE FROM login_attempts WHERE key_hash IN (?,?)',[hash('sha256','127.0.0.1|'.$p.$suffix.'@example.test'),hash('sha256','::1|'.$p.$suffix.'@example.test')]);@unlink($cookie);
}
