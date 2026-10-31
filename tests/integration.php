<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
$suffix=bin2hex(random_bytes(6));$tenants=[];$users=[];$checks=0;
final class PushDeliveryCheck { public static function created():?string { $v=query("SELECT value FROM app_state WHERE name='cron_secret'")->fetchColumn(); return is_string($v)?$v:null; } }
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
 check(request('admin/diagnostics')[0]===401,'installation diagnostics require authentication');
 check(request('team-login/request','POST',['email'=>'unknown-'.$suffix.'@example.test'])[0]===202,'team login request does not reveal whether an email exists');
 check(request('login','POST',['email'=>$email,'password'=>'wrong'])[0]===401,'wrong password denied');
 [$code,$login]=request('login','POST',['email'=>$email,'password'=>$password]);check($code===200 && isset($login['csrf']),'login and CSRF token');
 [$code,$inbox]=request('conversations');check($code===200 && count($inbox['items'])===1 && $inbox['items'][0]['id']==='own','seller assignment and tenant isolation');
 check(request('admin/integrations')[0]===403,'seller cannot read integration settings');
 check(request('admin/diagnostics')[0]===403,'seller cannot inspect installation');
 check(request('admin/installation/encryption','POST',null,['X-CSRF-Token: '.$login['csrf']])[0]===403,'seller cannot prepare server encryption');
 check(request('admin/installation/migrations','POST',null,['X-CSRF-Token: '.$login['csrf']])[0]===403,'seller cannot apply migrations');
 check(request('workspace/send?connectionId=1&conversationId=conv','POST',['requestId'=>str_repeat('x',32),'message'=>'test','channel'=>'SMS'],['X-CSRF-Token: '.$login['csrf']])[0]===403,'seller cannot send without account mapping');
 check(request('ghl/connections')[0]===403,'live inbox denies seller without per-location mapping');
 check(request('workspace/opportunities?connectionId=1&conversationId=conv')[0]===403,'opportunity context denies a seller without account mapping');
 check(request('workspace/activity?connectionId=1&conversationId=conv')[0]===403,'contact activity denies a seller without account mapping');
 check(request('workspace/contact?connectionId=1&conversationId=conv')[0]===403,'the contact sheet denies a seller without account mapping');
 check(request('workspace/activity/note?connectionId=1&conversationId=conv','POST',['body'=>'x','requestId'=>str_repeat('k',32)],['X-CSRF-Token: '.$login['csrf']])[0]===403,'quick actions deny a seller without account mapping');
 query("UPDATE users SET role='administrator' WHERE id=?",[$users[0]]);
 $token='pit-test-'.bin2hex(random_bytes(16));
 $headers=['X-CSRF-Token: '.$login['csrf']];
 query('DROP TABLE IF EXISTS directory_cache');
 check(!in_array('directory_cache',array_column(query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM),0),true),'fixture removed a migrated table');
 [$code,$recovered]=request('admin/diagnostics');
 check($code===200&&$recovered['ready']===true,'a pending database update is applied on its own, with no button to press');
 check(in_array('directory_cache',array_column(query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM),0),true),'the missing table is back after that single request');
 [$code,$diagnostics]=request('admin/diagnostics');
 check($code===200 && $diagnostics['ready'] && $diagnostics['version']==='0.22.2','administrator sees installation readiness and version');
 check(!str_contains(json_encode($diagnostics),'APP_KEY_FILE')&&!str_contains(json_encode($diagnostics),'integration.key'),'diagnostics expose no configuration values or key paths');
 check(request('admin/installation/encryption','POST')[0]===403,'preparation requires CSRF');
 [$code,$profile]=request('me');check($code===200&&!array_key_exists('ghl_user_id',$profile['user'])&&$profile['user']['capabilities']['configureConnections'],'public profile exposes capabilities without provider-specific fields');
 [$code,$channel]=request('admin/access-channel','GET',null,$headers);check($code===200&&$channel['connectionId']===null&&$channel['ready']===false,'access channel starts unassigned');
 [$code,$probe]=request('admin/access-channel/test','POST',null,$headers);check($code===424&&($probe['code']??'')==='access_channel_unconfigured'&&!str_contains(strtolower(json_encode($probe)),'highlevel'),'access channel probe fails safely and names no provider');
 [$code,$rejected]=request('admin/access-channel','PUT',['connectionId'=>'not-a-number'],$headers);check($code===422,'a malformed access channel selection is rejected');

 [$code,$saved]=request('admin/integrations','POST',['name'=>'Test connection','locationId'=>'loc-'.$suffix,'token'=>$token,'syncIntervalSeconds'=>120],$headers);
 check($code===201 && !str_contains(json_encode($saved),$token),'administrator saves integration without token response');
 $id=$saved['id'];
 check(request('admin/integrations/'.$id.'/schedule','POST',['enabled'=>true],$headers)[0]===422,'unverified connection cannot enable cron');
 check(request('ghl/inbox?connectionId='.$id)[0]===200,'administrator can read own empty inbox snapshot');
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',[$id,'external-test','external@example.test','External fixture',json_encode(['conversationsEnabled'=>true,'assignedDataOnly'=>true]),json_encode(['conversations.readonly','conversations/message.readonly']),1]);
 [$code,$team]=request('admin/integrations/'.$id.'/users');check($code===200&&count($team['items'])===1&&$team['items'][0]['capabilities']['assignedDataOnly']&&!$team['items'][0]['capabilities']['configureConnections']&&!isset($team['items'][0]['permissions'],$team['items'][0]['scopes']),'admin sees projected capabilities without raw permission payload');
 check((int)query('SELECT COUNT(*) FROM users WHERE tenant_id=?',[$tenants[0]])->fetchColumn()===1,'imported team does not create native login users');
 query('INSERT INTO ghl_inbox_snapshots(connection_id,payload,total) VALUES(?,?,?)',[$id,'[]',0]);
 check(request('ghl/messages?connectionId='.$id.'&conversationId=unknown')[0]===404,'unknown conversation cannot trigger arbitrary GHL lookup');
 check(request('workspace/send?connectionId='.$id.'&conversationId=unknown','POST',[], $headers)[0]===404,'unknown conversation cannot trigger send');
 check(request('workspace/send?connectionId='.$id.'&conversationId=unknown','POST',[])[0]===403,'send requires CSRF');
 $cachedProfile=['id'=>'contact-test','name'=>'Contacto real','email'=>'contact@example.test','phone'=>'+34000000000','tags'=>['Cliente'],'customFields'=>[]];
 // Authorization reads the index of conversations the account has shown.
 query('INSERT INTO conversation_index(connection_id,conversation_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)',[$id,'conv-contact',json_encode(['id'=>'conv-contact','contactId'=>'contact-test','locationId'=>'loc-'.$suffix])]);
 query('INSERT INTO contact_profile_cache(connection_id,contact_id,payload) VALUES(?,?,?)',[$id,'contact-test',json_encode($cachedProfile)]);
 // Reads go to the account first, so the stored profile is served only while
 // the budget is paused. That is the one case this check can exercise here.
 query('INSERT IGNORE INTO ghl_sync_state(connection_id) VALUES(?)',[$id]);
 query('UPDATE ghl_sync_state SET rate_window_start=UNIX_TIMESTAMP(),rate_window_count=1000 WHERE connection_id=?',[$id]);
 [$code,$contactResponse]=request('workspace/contact?connectionId='.$id.'&conversationId=conv-contact');check($code===200&&$contactResponse['contact']['name']==='Contacto real','an authorized conversation falls back to the stored contact profile while paused');
 check(request('workspace/contact?connectionId='.$id.'&conversationId=desconocida')[0]===404,'a conversation the account never showed is not authorized');
 query('UPDATE ghl_sync_state SET rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 check(request('admin/integrations/'.$id.'/sync?limit=999','POST',null,$headers)[0]===422,'HTTP rejects unsupported inbox limit');
 query('SELECT GET_LOCK(?,0)',['agora-sync:'.$id]);
 check(request('admin/integrations/'.$id,'PUT',['name'=>'Busy','token'=>'','syncIntervalSeconds'=>120],$headers)[0]===409,'credential update waits for in-flight synchronization');
 query('SELECT RELEASE_LOCK(?)',['agora-sync:'.$id]);
 query("UPDATE ghl_connections SET status='connected' WHERE id=?",[$id]);
 check(request('admin/integrations/'.$id.'/schedule','POST',['enabled'=>true],$headers)[0]===200,'verified administrator can enable scheduling');
 check(request('admin/integrations/'.$id.'/schedule','POST',['enabled'=>false],$headers)[0]===200,'administrator can pause scheduling');


 $cipher=query('SELECT token_ciphertext FROM ghl_connections WHERE id=?',[$id])->fetchColumn();
 check(request('admin/installation/migrations','POST')[0]===403,'browser migrations require CSRF');
 check(request('admin/installation/migrations','POST',null,$headers)[0]===200 && request('admin/installation/migrations','POST',null,$headers)[0]===200 && query('SELECT token_ciphertext FROM ghl_connections WHERE id=?',[$id])->fetchColumn()===$cipher,'admin migrations repeat safely without modifying stored credentials');
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
 check(request('ghl/inbox?connectionId='.$foreign)[0]===404,'live inbox isolates tenant connections');
 check(request('workspace/send?connectionId='.$foreign.'&conversationId=conv','POST',[],$headers)[0]===404,'send cannot access foreign tenant connection');
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
 // ---- a phone registering itself for notices --------------------------------
 // A browser hands out an address with slashes, not an identifier: it has to be
 // accepted as it comes, and anything else has to be refused.
 [$code,$fresh]=request('login','POST',['email'=>$email,'password'=>$password]);
 $headers=['X-CSRF-Token: '.($fresh['csrf'] ?? '')];
 $endpoint='https://fcm.googleapis.com/fcm/send/dGVzdDpBUEE5MWJF-x_0123456789abcdef';
 $subscription=['token'=>$endpoint,'platform'=>'web','label'=>'Android (instalada)','keys'=>['p256dh'=>str_repeat('B',87),'auth'=>'bXktYXV0aC1zZWNyZXQ']];
 [$code,$saved]=request('devices','POST',$subscription,$headers);
 check($code===200&&($saved['registered']??false)===true,'a browser subscription address is accepted as the browser gives it');
 [$code,$again]=request('devices','POST',$subscription,$headers);
 check($code===200&&(int)query('SELECT COUNT(*) FROM push_devices WHERE token=?',[$endpoint])->fetchColumn()===1,'registering the same device twice keeps one entry');
 [$code,$listed]=request('devices','GET',null,$headers);
 check($code===200&&count(array_filter($listed['items']??[],fn($d)=>($d['label']??'')==='Android (instalada)'))===1,'and it appears in the device list the owner sees');
 check(request('devices','POST',['token'=>'http://inseguro.example/x','platform'=>'web','keys'=>['p256dh'=>str_repeat('B',87),'auth'=>'x']],$headers)[0]===422,'an address that is not https is refused');
 check(request('devices','POST',['token'=>'corto','platform'=>'android'],$headers)[0]===422,'and so is an identifier that is not one');
 [$code,$removed]=request('devices','DELETE',['token'=>$endpoint],$headers);
 check($code===200&&(int)query('SELECT COUNT(*) FROM push_devices WHERE token=?',[$endpoint])->fetchColumn()===0,'signing out of a device stops it being notified');
 // The test notice answers per device, whatever happens to the delivery.
 request('devices','POST',$subscription,$headers);
 [$code,$tested]=request('devices/test','POST',null,$headers);
 check($code===200&&isset($tested['results'][0]['result']),'the test notice reports a result for each device of the person asking');
 [$code,$chain]=request('devices','GET',null,$headers);
 check($code===200&&array_key_exists('queue',$chain)&&array_key_exists('delivery',$chain)&&array_key_exists('discovery',$chain),'the device list says when messages were last checked and notices last delivered');
 request('devices','DELETE',['token'=>$endpoint],$headers);
 // ---- the check from outside, for when the server cron does not run --------
 check(request('cron/'.str_repeat('a',64))[0]===404,'with no address created, the outside check does not exist');
 [$code,$made]=request('admin/cron-url','POST',null,$headers);
 check($code===200&&preg_match('~/api/cron/([a-f0-9]{64})$~',$made['url']??'',$cronMatch)===1,'the owner creates an address for the check from outside');
 $cronSecret=$cronMatch[1];
 check(!str_contains(json_encode(query("SELECT value FROM app_state WHERE name='cron_secret'")->fetchColumn()),$cronSecret),'only a fingerprint of that address is kept, never the address');
 query("DELETE FROM app_state WHERE name='sweep_last_run'");
 // A cron service calls it with no session and no browser.
 $outside=curl_init('http://localhost:8080/api/cron/'.$cronSecret);curl_setopt($outside,CURLOPT_RETURNTRANSFER,true);
 $body=json_decode((string)curl_exec($outside),true);$status=curl_getinfo($outside,CURLINFO_RESPONSE_CODE);curl_close($outside);
 check($status===200&&($body['ok']??false)===true&&!isset($body['skipped']),'calling it, with no session at all, runs the minute check');
 $trace=json_decode((string)query("SELECT value FROM app_state WHERE name='sweep_last_run'")->fetchColumn(),true);
 check(($trace['via']??null)==='url','and the owner can see the check came from outside');
 $again=curl_init('http://localhost:8080/api/cron/'.$cronSecret);curl_setopt($again,CURLOPT_RETURNTRANSFER,true);
 $body=json_decode((string)curl_exec($again),true);curl_close($again);
 check(($body['skipped']??null)==='too_soon','a second call seconds later does not repeat the work');
 $wrong=curl_init('http://localhost:8080/api/cron/'.str_repeat('b',64));curl_setopt($wrong,CURLOPT_RETURNTRANSFER,true);
 curl_exec($wrong);$status=curl_getinfo($wrong,CURLINFO_RESPONSE_CODE);curl_close($wrong);
 check($status===404,'any other address answers as if it did not exist');
 [$code,$remade]=request('admin/cron-url','POST',null,$headers);
 $old=curl_init('http://localhost:8080/api/cron/'.$cronSecret);curl_setopt($old,CURLOPT_RETURNTRANSFER,true);
 curl_exec($old);$status=curl_getinfo($old,CURLINFO_RESPONSE_CODE);curl_close($old);
 check($code===200&&$status===404,'changing the address retires the previous one');
 request('admin/cron-url','DELETE',null,$headers);
 check(PushDeliveryCheck::created()===null,'and the owner can turn it off');
 [$code,$pushKey]=request('push-key','GET',null,$headers);
 check($code===200&&isset($pushKey['ready']),'the subscription key route answers whether notices are ready, never an unexplained failure');

 echo "PASS $checks integration checks\n";
} finally {
 foreach($tenants as $t){foreach(['notifications','resources','webhook_events','ghl_connections','users','tenants'] as $table)query('DELETE FROM '.$table.' WHERE '.($table==='tenants'?'id':'tenant_id').'=?',[$t]);}
 query('DELETE FROM login_attempts WHERE key_hash=?',[hash('sha256','127.0.0.1|'.$email)]);
 query('DELETE FROM login_attempts WHERE key_hash=?',[hash('sha256','127.0.0.1|external-request|unknown-'.$suffix.'@example.test')]);
 unlink($cookie);
}
