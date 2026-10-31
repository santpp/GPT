<?php
declare(strict_types=1);
// Escribir desde la ficha: se usa la conversación que ya tiene el contacto o se
// abre una con el endpoint publicado, y queda registrada para poder responder.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/InboxFeed.php';
$tenant=null;$suffix=bin2hex(random_bytes(6));$checks=0;
function ok(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Contact conversation','cc-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'x']);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $calls=[];$existing=[];
 $gateway=new GhlGateway(function()use(&$calls,&$existing,$location){return new GhlClient('fake',function($path,$version,$method,$body)use(&$calls,&$existing,$location){
  $calls[]=[$method,$path,$version,$body];
  if(str_starts_with($path,'/conversations/search'))return ['status'=>200,'body'=>json_encode(['conversations'=>$existing,'total'=>count($existing)])];
  if($path==='/conversations/'&&$method==='POST')return ['status'=>201,'body'=>json_encode(['success'=>true,'conversation'=>['id'=>'conv-created','contactId'=>$body['contactId'],'locationId'=>$location]])];
  throw new GhlApiException(404);
 });});
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $feed=new InboxFeed($gateway);
 query('INSERT INTO contact_profile_cache(connection_id,contact_id,payload) VALUES(?,?,?)',[$id,'k1',json_encode(['id'=>'k1','name'=>'Andreina Del Nogal','phone'=>'+584142736522'])]);

 $existing=[['id'=>'conv-old','contactId'=>'k1','locationId'=>$location,'unreadCount'=>2,'lastMessageType'=>'TYPE_WHATSAPP','contactName'=>'Andreina']];
 $free();$found=$feed->forContact($connection,'k1');
 ok($found['id']==='conv-old'&&count($calls)===1&&str_contains($calls[0][1],'contactId=k1')&&str_contains($calls[0][1],'locationId='.$location),'a contact who already has a conversation is taken to it, found through the published search');
 $row=query('SELECT payload,content_hash FROM conversation_index WHERE connection_id=? AND conversation_id=?',[$id,'conv-old'])->fetch();
 ok($row&&$row['content_hash']===null&&InboxFeed::known($id,'conv-old')['id']==='conv-old','and it is registered so the chat can read and answer it');
 ok((int)query('SELECT COUNT(*) FROM push_queue WHERE connection_id=?',[$id])->fetchColumn()===0,'opening it does not count as a new message: no notice is queued');

 $calls=[];$existing=[['id'=>'conv-x','contactId'=>'other','locationId'=>$location],['id'=>'conv-y','contactId'=>'k1','locationId'=>'foreign']];
 $free();$created=$feed->forContact($connection,'k1');
 ok($created['id']==='conv-created'&&$calls[1][0]==='POST'&&$calls[1][1]==='/conversations/'&&$calls[1][2]==='2021-04-15'&&$calls[1][3]===['locationId'=>$location,'contactId'=>'k1'],'without one of their own, a conversation is opened with the published endpoint');
 ok($created['contactName']==='Andreina Del Nogal'&&$created['phone']==='+584142736522','and it carries the name and phone of the record, so the chat shows who it is');
 ok(InboxFeed::known($id,'conv-created')!==null,'the new conversation is registered too');

 $failed=false;try{$feed->forContact($connection,'bad id!');}catch(InvalidArgumentException $e){$failed=true;}
 ok($failed,'a malformed contact id is refused before asking the account');
 echo "PASS $checks contact conversation checks (simulated account, real MariaDB)\n";
} finally {
 if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
}
