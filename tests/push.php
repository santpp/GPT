<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/PushDelivery.php';
require __DIR__.'/../src/WebPush.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;$keyFile=null;
function pushCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

/** A simulated Firebase: records every request and answers what the test wants. */
final class FakeFcm {
    public array $calls=[];
    public array $answers=[];
    public function transport():Closure {
        return function(string $url,array $headers,array $payload):array {
            $this->calls[]=['url'=>$url,'headers'=>$headers,'payload'=>$payload];
            if(str_contains($url,'oauth2.googleapis.com'))return ['status'=>200,'body'=>json_encode(['access_token'=>'simulated-access-token','expires_in'=>3600])];
            $token=$payload['message']['token']??'';
            $answer=$this->answers[$token]??['status'=>200,'body'=>'{}'];
            return $answer;
        };
    }
    public function sends():array {return array_values(array_filter($this->calls,fn($c)=>str_contains($c['url'],'fcm.googleapis.com')));}
    public function reset():void {$this->calls=[];}
}

try {
 // A service account that exists only for this test, with a key generated here.
 $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
 openssl_pkey_export($key,$pem);
 $keyFile=tempnam(sys_get_temp_dir(),'agora-fcm-test-');
 file_put_contents($keyFile,json_encode(['type'=>'service_account','project_id'=>'agora-test','client_email'=>'push@agora-test.iam.gserviceaccount.com','private_key'=>$pem]));

 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Push test','push-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,'owner-'.$suffix.'@example.test','x']);
 $ownerId=(int)db()->lastInsertId();
 foreach([['seller-1','Ana',1],['seller-2','Luis',0]] as [$external,$name,$restricted]) {
  query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',
   [$connectionId,$external,$external.'-'.$suffix.'@example.test',$name,'{}','[]',$restricted]);
  $row=(int)db()->lastInsertId();
  query('INSERT INTO external_identities(tenant_id,email) VALUES(?,?)',[$tenant,$external.'-'.$suffix.'@example.test']);
  $identity=(int)db()->lastInsertId();
  query('INSERT INTO external_identity_memberships(identity_id,external_user_row_id) VALUES(?,?)',[$identity,$row]);
  query('INSERT INTO push_devices(tenant_id,identity_type,actor_id,token,platform) VALUES(?,?,?,?,?)',[$tenant,'external',$identity,'token-'.$external.'-'.$suffix,'android']);
 }
 query('INSERT INTO push_devices(tenant_id,identity_type,actor_id,token,platform) VALUES(?,?,?,?,?)',[$tenant,'native',$ownerId,'token-owner-'.$suffix,'android']);

 $queue=function(string $conversation,?string $assigned)use($connectionId):int {
  query('INSERT INTO push_queue(connection_id,conversation_id,fingerprint,title,body,assigned_to) VALUES(?,?,?,?,?,?)',
   [$connectionId,$conversation,md5($conversation),'Raissa Moreno','Hola, quiero información',$assigned]);
  return (int)db()->lastInsertId();
 };

 $fcm=new FakeFcm();
 $delivery=fn():PushDelivery=>new PushDelivery($fcm->transport(),$keyFile);

 // ---- who is told ------------------------------------------------------------
 $sorted=function()use($fcm):array{$tokens=array_map(fn($c)=>$c['payload']['message']['token'],$fcm->sends());sort($tokens);return $tokens;};
 $queue('conv-1','seller-1');
 $result=$delivery()->run();
 pushCheck($sorted()===['token-owner-'.$suffix,'token-seller-1-'.$suffix,'token-seller-2-'.$suffix],'a conversation reaches its assignee, whoever sees the whole account, and the owner');
 pushCheck($result['sent']===3&&$result['retried']===0,'and the pass reports what it sent');
 pushCheck(query('SELECT state FROM push_queue WHERE conversation_id=?',['conv-1'])->fetchColumn()==='sent','the notice is marked as delivered');

 $fcm->reset();
 $queue('conv-2','seller-2');
 $delivery()->run();
 pushCheck($sorted()===['token-owner-'.$suffix,'token-seller-2-'.$suffix],'a member limited to their own data is not told about a conversation that is not theirs');

 $fcm->reset();
 $queue('conv-unassigned',null);
 $delivery()->run();
 pushCheck($sorted()===['token-owner-'.$suffix,'token-seller-2-'.$suffix],'and an unassigned conversation reaches only those who can see it');

 // ---- the request is the one the service documents --------------------------
 $fcm->reset();
 $queue('conv-3','seller-1');
 $delivery()->run();
 $send=$fcm->sends()[0];
 pushCheck($send['url']==='https://fcm.googleapis.com/v1/projects/agora-test/messages:send','the documented HTTP v1 endpoint of that project is used');
 pushCheck(in_array('Authorization: Bearer simulated-access-token',$send['headers'],true),'with the access token minted from the service account');
 $message=$send['payload']['message'];
 pushCheck($message['notification']===['title'=>'Raissa Moreno','body'=>'Hola, quiero información'],'the notice carries who wrote and what they wrote');
 pushCheck($message['data']['path']==='/conversations/conv-3'&&$message['data']['conversationId']==='conv-3','and where tapping it should go');
 pushCheck(($message['android']['priority']??null)==='high','as a high-priority Android message, so it arrives while the phone sleeps');
 $assertion=null;
 foreach($fcm->calls as $call)if(str_contains($call['url'],'oauth2'))$assertion=$call['payload']['assertion']??null;
 pushCheck(is_string($assertion)&&count(explode('.',$assertion))===3,'the token request is a signed assertion, not the key itself');
 pushCheck(!str_contains(json_encode($fcm->calls),'BEGIN PRIVATE KEY'),'the private key never leaves the server');

 // ---- a phone that no longer exists -----------------------------------------
 $fcm->reset();
 $fcm->answers['token-seller-1-'.$suffix]=['status'=>404,'body'=>json_encode(['error'=>['status'=>'UNREGISTERED']])];
 $queue('conv-4','seller-1');
 $dropped=$delivery()->run();
 pushCheck($dropped['dropped']===1&&(int)query('SELECT COUNT(*) FROM push_devices WHERE token=?',['token-seller-1-'.$suffix])->fetchColumn()===0,'a device the service no longer knows is forgotten');
 pushCheck(query('SELECT state FROM push_queue WHERE conversation_id=?',['conv-4'])->fetchColumn()==='sent','and that does not leave the notice pending for ever');
 unset($fcm->answers['token-seller-1-'.$suffix]);

 // ---- a service that is down -------------------------------------------------
 $fcm->reset();
 $fcm->answers['token-owner-'.$suffix]=['status'=>503,'body'=>'{}'];
 $queue('conv-5',null);
 $retried=$delivery()->run();
 $notice=query('SELECT * FROM push_queue WHERE conversation_id=?',['conv-5'])->fetch();
 pushCheck($retried['retried']===1&&$notice['state']==='pending'&&(int)$notice['attempts']===1,'a service that is down leaves the notice pending, not failed');
 pushCheck((int)$notice['not_before']>time(),'and waits before trying again');
 query('UPDATE push_queue SET not_before=0,attempts=4 WHERE conversation_id=?',['conv-5']);
 $delivery()->run();
 pushCheck(query('SELECT state FROM push_queue WHERE conversation_id=?',['conv-5'])->fetchColumn()==='failed','after enough attempts it gives up instead of retrying for ever');
 unset($fcm->answers['token-owner-'.$suffix]);

 // ---- nobody to tell ---------------------------------------------------------
 $fcm->reset();
 query('DELETE FROM push_devices WHERE tenant_id=?',[$tenant]);
 $queue('conv-6',null);
 $none=$delivery()->run();
 pushCheck($fcm->sends()===[]&&$none['sent']===0,'with no device registered nothing is sent');
 pushCheck(query('SELECT state FROM push_queue WHERE conversation_id=?',['conv-6'])->fetchColumn()==='sent','and the notice is closed rather than piling up');

 // ---- a browser subscription travels by its own road ---------------------------
 $fcm->reset();
 $webKey=tempnam(sys_get_temp_dir(),'agora-webpush-');@unlink($webKey);
 WebPush::ensureKeys($webKey,'mailto:pruebas@example.test');
 $browserCalls=[];
 $browser=new WebPush(function(string $url,array $headers,string $body)use(&$browserCalls):array{
  $browserCalls[]=['url'=>$url,'headers'=>$headers];return ['status'=>201,'body'=>''];
 },$webKey);
 query('INSERT INTO push_devices(tenant_id,identity_type,actor_id,token,platform,p256dh,auth,label) VALUES(?,?,?,?,?,?,?,?)',
  [$tenant,'native',$ownerId,'https://push.example.test/suscripcion-'.$suffix,'web',
   WebPush::encode(WebPush::point(openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]))),
   WebPush::encode(random_bytes(16)),'iPhone (instalada)']);
 query('INSERT INTO push_devices(tenant_id,identity_type,actor_id,token,platform) VALUES(?,?,?,?,?)',[$tenant,'native',$ownerId,'token-owner-again-'.$suffix,'android']);
 $queue('conv-7',null);
 $mixed=(new PushDelivery($fcm->transport(),$keyFile,$browser))->run();
 pushCheck(count($browserCalls)===1&&str_starts_with($browserCalls[0]['url'],'https://push.example.test/'),'a browser is notified through its own subscription address');
 pushCheck(count($fcm->sends())===1,'and an Android phone through the other road, in the same pass');
 pushCheck($mixed['sent']===2,'both count as delivered');
 pushCheck((int)query('SELECT COUNT(*) FROM push_devices WHERE tenant_id=? AND last_sent_at IS NOT NULL',[$tenant])->fetchColumn()===2,'and each device records when it was last told, for the diagnostics');
 @unlink($webKey);

 // ---- not configured ----------------------------------------------------------
 $missing=new PushDelivery($fcm->transport(),'/does/not/exist.json');
 $problem=null;
 try{$missing->send('token-x',['title'=>'T','body'=>'B','conversation_id'=>'c','connection_id'=>1]);}catch(PushProblem $e){$problem=$e->reason;}
 pushCheck($problem==='unconfigured','without credentials nothing is attempted and the reason is named');
 pushCheck(PushDelivery::configured()===false||getenv('FCM_CREDENTIALS_FILE')!==false,'the app can tell whether notifications are configured at all');

 echo 'PASS '.$checks.' push delivery checks (simulated notification service, real MariaDB)'.PHP_EOL;
} finally {
 if($keyFile!==null)@unlink($keyFile);
 foreach($tenants as $id){query('DELETE FROM push_devices WHERE tenant_id=?',[$id]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM external_identities WHERE tenant_id=?',[$id]);query('DELETE FROM users WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
