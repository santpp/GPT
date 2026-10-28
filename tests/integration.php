<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
$suffix=bin2hex(random_bytes(6));$tenants=[];$users=[];$checks=0;
function check(bool $ok,string $label):void { global $checks; if(!$ok)throw new Exception($label);$checks++;echo "PASS $label\n"; }
$cookie=tempnam(sys_get_temp_dir(),'agora-test-');
function request(string $path,string $method='GET',?array $body=null,array $headers=[]):array {
 global $cookie;
 $ch=curl_init('http://localhost:8080/api/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Origin: http://localhost:8080'],$headers)]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
 return [$code,json_decode($raw ?: '{}',true)];
}
try {
 for($i=0;$i<2;$i++) {query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Test','test-'.$suffix.'-'.$i]);$tenants[]=(int)db()->lastInsertId();}
 $password=bin2hex(random_bytes(14));$email='seller-'.$suffix.'@example.test';
 query('INSERT INTO users(tenant_id,email,password_hash,role,ghl_user_id) VALUES(?,?,?,?,?)',[$tenants[0],$email,password_hash($password,PASSWORD_DEFAULT),'seller','seller-1']);$users[]=(int)db()->lastInsertId();
 foreach([[$tenants[0],'own','seller-1'],[$tenants[0],'other','seller-2'],[$tenants[1],'foreign','seller-1']] as [$t,$id,$assigned]) query('INSERT INTO resources(tenant_id,kind,ghl_id,assigned_to,payload) VALUES(?,?,?,?,?)',[$t,'conversation',$id,$assigned,'{}']);
 check(request('conversations')[0]===401,'anonymous inbox denied');
 check(request('login','POST',['email'=>$email,'password'=>'wrong'])[0]===401,'wrong password denied');
 [$code,$login]=request('login','POST',['email'=>$email,'password'=>$password]);check($code===200 && isset($login['csrf']),'login and CSRF token');
 [$code,$inbox]=request('conversations');check($code===200 && count($inbox['items'])===1 && $inbox['items'][0]['id']==='own','seller assignment and tenant isolation');
 check(request('admin/integrations')[0]===403,'seller cannot read integration settings');
 query("UPDATE users SET role='administrator' WHERE id=?",[$users[0]]);
 $token='pit-test-'.bin2hex(random_bytes(16));
 $headers=['X-CSRF-Token: '.$login['csrf']];
 [$code,$saved]=request('admin/integrations','POST',['name'=>'Test connection','locationId'=>'loc-'.$suffix,'token'=>$token,'syncIntervalSeconds'=>120],$headers);
 check($code===201 && !str_contains(json_encode($saved),$token),'administrator saves integration without token response');
 $id=$saved['id'];
 $cipher=query('SELECT token_ciphertext FROM ghl_connections WHERE id=?',[$id])->fetchColumn();
 require_once __DIR__.'/../src/TokenVault.php';
 $vault=TokenVault::fromEnvironment();
 check(!str_contains($cipher,$token) && $vault->open($cipher,$tenants[0],'loc-'.$suffix)===$token,'token encrypted and recoverable server side');
 $rejected=false;try{$vault->open($cipher,$tenants[1],'loc-'.$suffix);}catch(RuntimeException $e){$rejected=true;}
 check($rejected,'ciphertext bound to tenant and location');
 [$code,$settings]=request('admin/integrations');
 check($code===200 && !str_contains(json_encode($settings),$token) && !str_contains(json_encode($settings),'token_ciphertext'),'settings expose metadata only');
 [$code,$edited]=request('admin/integrations/'.$id,'PUT',['name'=>'Renamed','token'=>'','syncIntervalSeconds'=>300],$headers);
 check($code===200 && query('SELECT token_ciphertext FROM ghl_connections WHERE id=?',[$id])->fetchColumn()===$cipher,'blank token preserves existing secret');
 $newToken='pit-rotated-'.bin2hex(random_bytes(12));
 [$code]=request('admin/integrations/'.$id,'PUT',['name'=>'Renamed','token'=>$newToken,'syncIntervalSeconds'=>120],$headers);
 $rotated=query('SELECT token_ciphertext FROM ghl_connections WHERE id=?',[$id])->fetchColumn();
 check($code===200 && $rotated!==$cipher && $vault->open($rotated,$tenants[0],'loc-'.$suffix)===$newToken,'administrator rotates encrypted token');
 query("UPDATE users SET role='supervisor' WHERE id=?",[$users[0]]);
 check(request('admin/integrations')[0]===403,'supervisor cannot read token settings');
 query("UPDATE users SET role='administrator' WHERE id=?",[$users[0]]);
 query('INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext) VALUES(?,?,?,?)',[$tenants[1],'Foreign','foreign-'.$suffix,$vault->seal('fake',$tenants[1],'foreign-'.$suffix)]);
 $foreign=db()->lastInsertId();
 check(request('admin/integrations/'.$foreign,'DELETE',null,$headers)[0]===404,'administrator cannot modify another tenant integration');
 check(request('admin/integrations','POST',['name'=>'No CSRF','locationId'=>'x','token'=>$token])[0]===403,'CSRF protects token configuration');
 check(request('webhooks/ghl','POST',[])[0]===404,'webhooks disabled by default');
 check(request('admin/integrations/'.$id,'DELETE',null,$headers)[0]===200,'administrator deletes connection');
 check(request('logout','POST')[0]===403,'CSRF required');
 check(request('logout','POST',null,['X-CSRF-Token: '.$login['csrf']])[0]===200,'authenticated logout');
 check(request('me')[0]===401,'session destroyed');
 $e=['type'=>'InboundMessage','locationId'=>'test-'.$suffix.'-0','messageId'=>'msg-'.$suffix,'conversationId'=>'conv-'.$suffix,'body'=>'Test'];
 for($i=0;$i<2;$i++)query('INSERT INTO webhook_events(tenant_id,webhook_id,event_type,payload) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE webhook_id=VALUES(webhook_id)',[$tenants[0],'event-'.$suffix,'InboundMessage',json_encode($e)]);
 check((int)query('SELECT COUNT(*) FROM webhook_events WHERE tenant_id=?',[$tenants[0]])->fetchColumn()===1,'webhook deduplication');
 passthru('php ' . escapeshellarg(__DIR__.'/../bin/worker.php').' --once',$exit);
 check($exit===0,'worker executes');
 check((int)query('SELECT COUNT(*) FROM resources WHERE tenant_id=? AND kind=?',[$tenants[0],'message'])->fetchColumn()===1,'event projected into message read model');
 check((int)query('SELECT COUNT(*) FROM notifications WHERE tenant_id=?',[$tenants[0]])->fetchColumn()===1,'notification persisted for later FCM delivery');
 echo "PASS $checks integration checks\n";
} finally {
 foreach($tenants as $t){foreach(['notifications','resources','webhook_events','ghl_connections','users','tenants'] as $table)query('DELETE FROM '.$table.' WHERE '.($table==='tenants'?'id':'tenant_id').'=?',[$t]);}
 query('DELETE FROM login_attempts WHERE key_hash=?',[hash('sha256','127.0.0.1|'.$email)]);
 unlink($cookie);
}
