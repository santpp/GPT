<?php
declare(strict_types=1);
// Run only in the disposable HTTP test container described in installation docs.
require __DIR__.'/../src/bootstrap.php';
if(getenv('APP_ENV')!=='development'||getenv('APP_KEY_FILE')!==sys_get_temp_dir().'/agora-http-setup-test.key')throw new RuntimeException("Refusing to modify a non-test key path");
if((int)query('SELECT COUNT(*) FROM ghl_connections')->fetchColumn()!==0)throw new RuntimeException("Requires an empty integration test database");
$suffix=bin2hex(random_bytes(6));$tenant=null;$cookie=tempnam(sys_get_temp_dir(),'setup-cookie-');$count=0;
function callSetup(string $path,string $method='GET',?array $body=null,string $csrf=''):array{
 global $cookie;$ch=curl_init('http://localhost:8080/api/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_HTTPHEADER=>['Origin: http://localhost:8080','Content-Type: application/json','X-CSRF-Token: '.$csrf]]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return[$code,json_decode($raw?:'{}',true)];
}
function verifySetup(bool $ok,string $label):void{global $count;if(!$ok)throw new Exception($label);$count++;echo 'PASS '.$label.PHP_EOL;}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Setup test','setup-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $password=bin2hex(random_bytes(16));$email='setup-'.$suffix.'@example.test';
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,$email,password_hash($password,PASSWORD_DEFAULT)]);
 [$code,$login]=callSetup('login','POST',['email'=>$email,'password'=>$password]);verifySetup($code===200,'test administrator login');$csrf=$login['csrf'];
 [$code,$diagnostics]=callSetup('admin/diagnostics');verifySetup($code===200&&!$diagnostics['ready']&&in_array('encryption_key_missing',array_column($diagnostics['checks'],'code'),true),'web diagnostics identify missing key');
 $data=['name'=>'Test connection','locationId'=>'setup-'.$suffix,'token'=>'test-token-not-real','syncIntervalSeconds'=>120];
 [$code,$failure]=callSetup('admin/integrations','POST',$data,$csrf);
 verifySetup($code===503&&$failure['code']==='encryption_key_missing'&&!str_contains(json_encode($failure),$data['token']),'save returns precise setup error without exposing submitted credential');
 verifySetup((int)query('SELECT COUNT(*) FROM ghl_connections WHERE tenant_id=?',[$tenant])->fetchColumn()===0,'failed save creates no incomplete connection');
 verifySetup(callSetup('admin/installation/encryption','POST',null,$csrf)[0]===200,'administrator prepares encryption through the app');
 verifySetup(callSetup('admin/diagnostics')[1]['ready']===true,'web readiness becomes healthy after preparation');
 verifySetup(callSetup('admin/integrations','POST',$data,$csrf)[0]===201,'credential save succeeds after preparation');
 unlink(getenv('APP_KEY_FILE'));
 [$code,$blocked]=callSetup('admin/installation/encryption','POST',null,$csrf);
 verifySetup($code===503&&$blocked['code']==='encryption_restore_required'&&!is_file(getenv('APP_KEY_FILE')),'web recovery cannot replace key for stored credentials');
 echo 'PASS '.$count.' initial setup HTTP checks'.PHP_EOL;
}finally{
 if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
 if(is_file(getenv('APP_KEY_FILE')))unlink(getenv('APP_KEY_FILE'));
 unlink($cookie);query('DELETE FROM login_attempts WHERE key_hash=?',[hash('sha256','127.0.0.1|'.($email??''))]);
}
