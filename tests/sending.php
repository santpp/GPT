<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/MessageSender.php';
$tenant=null;$calls=0;$suffix=bin2hex(random_bytes(6));
function verifySend(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function issue(Closure $f):string{try{$f();return '';}catch(SendProblem $e){return $e->reason;}}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Send test','send-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;$vault=TokenVault::fromEnvironment();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Send test',$location,$vault->seal('fake',$tenant,$location)]);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $conversation=['id'=>'conv','contactId'=>'contact','locationId'=>$location,'lastMessageType'=>'TYPE_WHATSAPP'];
 $input=['requestId'=>str_repeat('a',32),'message'=>'Hola','channel'=>'WhatsApp','contactId'=>'attacker'];
 $response=['status'=>200,'body'=>json_encode(['messageId'=>'msg1','conversationId'=>'conv','status'=>'pending'])];
 $gateway=new GhlGateway(function($row)use(&$response,&$calls){return new GhlClient('fake',function($path,$version,$method,$body)use(&$response,&$calls){$calls++;verifySend($path==='/conversations/messages'&&$method==='POST'&&$version==='2021-04-15','documented send endpoint and version');verifySend($body['contactId']==='contact'&&!array_key_exists('status',$body)&&!array_key_exists('subType',$body),'server chooses destination without fabricated delivery fields');return $response;});});
 $sender=new MessageSender($gateway);
 $result=$sender->send($connection,$conversation,$input);verifySend($result['state']==='accepted'&&$result['deliveryStatus']==='pending','acknowledgement is pending, not delivered');
 $before=$calls;$sender->send($connection,$conversation,$input);verifySend($calls===$before,'same request replay never sends twice');
 verifySend(issue(fn()=>$sender->send($connection,$conversation,array_replace($input,['message'=>'Different'])))==='request_conflict','request key cannot change content');
 verifySend(issue(fn()=>$sender->send($connection,array_replace($conversation,['locationId'=>'foreign']),$input))==='invalid_contact','foreign destination refused');

 // ---- the channel selector is computed by the server, not offered by the browser
 $cold=MessageSender::availableChannels($connection,$conversation);
 verifySend(count($cold)===5&&array_column($cold,'type')===['WhatsApp','SMS','IG','FB','Email'],'every channel is published so the row can show them all');
 verifySend(array_column($cold,'available')===[true,false,false,false,false],'without a known contact record only the thread channel can be used');
 verifySend($cold[1]['reason']==='needs_phone'&&$cold[4]['reason']==='needs_email'&&$cold[2]['reason']==='needs_thread','each unusable channel says what it is missing');
 verifySend(issue(fn()=>$sender->send($connection,$conversation,array_replace($input,['requestId'=>str_repeat('s',32),'channel'=>'SMS'])))==='unsupported_channel','a channel the contact cannot receive is refused');
 verifySend(issue(fn()=>$sender->send($connection,$conversation,array_replace($input,['requestId'=>str_repeat('s',32),'channel'=>'Telepatia'])))==='unsupported_channel','an invented channel is refused');

 // A cached record with a phone and an address opens the other channels.
 query('INSERT INTO contact_profile_cache(connection_id,contact_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',
  [$id,'contact',json_encode(['id'=>'contact','name'=>'Richard','phone'=>'+584142736522','email'=>'richard@example.test'])]);
 $offered=MessageSender::availableChannels($connection,$conversation);
 $usable=array_values(array_filter($offered,fn($c)=>$c['available']));
 verifySend(array_column($usable,'type')===['WhatsApp','SMS','Email'],'a phone opens SMS and WhatsApp, an address opens Email, thread channel first');
 verifySend(array_column($offered,'type')===['WhatsApp','SMS','Email','IG','FB'],'usable channels lead the row and the rest follow');
 $byType=array_column($offered,null,'type');
 verifySend($byType['Email']['needsSubject']===true&&$byType['WhatsApp']['needsSubject']===false&&$byType['SMS']['label']==='SMS','the panel is told which channel needs a subject');
 query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $switched=$sender->send($connection,$conversation,['requestId'=>str_repeat('z',32),'message'=>'Te escribo por aquí','channel'=>'SMS']);
 verifySend($switched['state']==='accepted','a thread opened on one channel can be answered on another the contact has');

 // Instagram and Messenger cannot be started: they stay out unless they are the thread.
 query('INSERT INTO contact_profile_cache(connection_id,contact_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)',
  [$id,'solo-ig',json_encode(['id'=>'solo-ig','name'=>'Richard','phone'=>null,'email'=>null])]);
 $instagram=MessageSender::availableChannels($connection,['id'=>'c2','contactId'=>'solo-ig','locationId'=>$location,'lastMessageType'=>'TYPE_INSTAGRAM']);
 $usableIg=array_values(array_filter($instagram,fn($c)=>$c['available']));
 verifySend(count($usableIg)===1&&$usableIg[0]['type']==='IG'&&$usableIg[0]['label']==='Instagram','a social thread without phone or address can use only itself, with its real name');
 $noReply=MessageSender::availableChannels($connection,['id'=>'c3','contactId'=>'solo-ig','locationId'=>$location,'lastMessageType'=>'TYPE_CALL']);
 verifySend(array_filter($noReply,fn($c)=>$c['available'])===[],'a channel that cannot be answered leaves every option disabled');
 query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $input['requestId']=str_repeat('b',32);$input['message']='Second';
 // An exhausted burst window must stop the send before it reaches the channel.
 $before=$calls;
 query('UPDATE ghl_sync_state SET rate_window_start=UNIX_TIMESTAMP(),rate_window_count=1000 WHERE connection_id=?',[$id]);
 verifySend(issue(fn()=>$sender->send($connection,$conversation,$input))==='send_paused'&&$calls===$before,'an exhausted burst window blocks before sending');
 query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 // Consecutive reads no longer wait a second between them.
 $windowBefore=$calls;$sender->send($connection,$conversation,array_replace($input,['requestId'=>str_repeat('w',32),'message'=>'Burst one']));
 $sender->send($connection,$conversation,array_replace($input,['requestId'=>str_repeat('x',32),'message'=>'Burst two']));
 verifySend($calls===$windowBefore+2,'two sends in the same second are both delivered');
 query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $response=['status'=>0,'body'=>false];
 verifySend(issue(fn()=>$sender->send($connection,$conversation,$input))==='send_unconfirmed','transport uncertainty recorded without retry');
 $before=$calls;$senderError=issue(fn()=>$sender->send($connection,$conversation,$input));verifySend($senderError==='send_unconfirmed'&&$calls===$before,'uncertain request blocked on replay');
 $input['requestId']=str_repeat('c',32);verifySend(issue(fn()=>$sender->send($connection,$conversation,$input))==='send_unconfirmed'&&$calls===$before,'new browser key cannot resend uncertain identical content');
 $input['message']='Third';query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$id]);
 $response=['status'=>403,'body'=>'{"secret":"do-not-expose"}'];
 verifySend(issue(fn()=>$sender->send($connection,$conversation,$input))==='send_rejected','permission error is a rejection');
 $before=$calls;verifySend(issue(fn()=>$sender->send($connection,$conversation,$input))==='send_rejected'&&$calls===$before,'rejected key cannot issue automatic retry');
 $input['requestId']=str_repeat('d',32);$input['message']='Fourth';query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$id]);
 $response=['status'=>200,'body'=>json_encode(['messageId'=>'msg2','conversationId'=>'foreign'])];
 verifySend(issue(fn()=>$sender->send($connection,$conversation,$input))==='send_unconfirmed','mismatched acknowledgement is not success');
 $input['requestId']=str_repeat('e',32);$input['message']='Fifth';
 verifySend(issue(fn()=>$sender->send($connection,array_replace($conversation,['lastMessageType'=>'TYPE_EMAIL']),array_replace($input,['channel'=>'Email'])))==='invalid_subject','email requires subject');
 foreach(['TYPE_SMS'=>'SMS','TYPE_INSTAGRAM'=>'IG','TYPE_FACEBOOK'=>'FB','TYPE_EMAIL'=>'Email'] as $type=>$mapped){
  query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$id]);
  $response=['status'=>200,'body'=>json_encode(['messageId'=>'msg-'.$mapped,'conversationId'=>'conv','status'=>'pending'])];
  $result=$sender->send($connection,array_replace($conversation,['lastMessageType'=>$type]),['requestId'=>bin2hex(random_bytes(16)),'message'=>'Channel test '.$mapped,'channel'=>$mapped,'subject'=>'Test subject']);
  verifySend($result['state']==='accepted','text adapter accepts documented channel '.$mapped);
 }
 require_once __DIR__.'/../src/Installation.php';
 $attempts=query('SELECT * FROM message_send_attempts WHERE connection_id=? ORDER BY id',[$id])->fetchAll();Installation::applyMigrations();
 verifySend(query('SELECT * FROM message_send_attempts WHERE connection_id=? ORDER BY id',[$id])->fetchAll()===$attempts,'repeated migration preserves send identities');
 verifySend(!str_contains(json_encode(query('SELECT * FROM message_send_attempts WHERE connection_id=?',[$id])->fetchAll()),'do-not-expose'),'upstream errors never persisted');
}finally{if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}}
