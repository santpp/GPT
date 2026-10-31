<?php
declare(strict_types=1);
// Citar un mensaje: se envía con el campo publicado, se recuerda para enseñarlo y se rechaza lo que no vale.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/MessageSender.php';
require __DIR__.'/../src/MessageReplies.php';
$tenant=null;$suffix=bin2hex(random_bytes(5));$checks=0;
function rep(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function why(Closure $f):string{try{$f();return '';}catch(SendProblem $e){return $e->reason;}}
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Reply','rp-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'x']);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $conversation=['id'=>'conv','contactId'=>'contact','locationId'=>$location,'lastMessageType'=>'TYPE_WHATSAPP'];
 query('INSERT INTO conversation_message_cache(connection_id,conversation_id,payload,last_message_id,has_more) VALUES(?,?,?,?,0)',[$id,'conv',json_encode([
  ['id'=>'m-in','direction'=>'inbound','body'=>"<p>¿Cuánto   cuesta\nel curso?</p>",'dateAdded'=>'2026-10-31T10:00:00Z'],
  ['id'=>'m-file','direction'=>'outbound','body'=>'','attachments'=>['https://x.test/a.pdf'],'dateAdded'=>'2026-10-31T10:01:00Z']]),'m-in']);
 $sent=[];
 $gateway=new GhlGateway(function()use(&$sent){return new GhlClient('fake',function($path,$version,$method,$body)use(&$sent){
  $sent[]=$body;return ['status'=>200,'body'=>json_encode(['messageId'=>'new-'.count($sent),'conversationId'=>'conv','status'=>'pending'])];});});
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $sender=new MessageSender($gateway);
 $input=fn(string $k,array $more=[])=>['requestId'=>str_pad($k,20,'x'),'message'=>'Cuesta 120','channel'=>'WhatsApp']+$more;

 $free();$sender->send($connection,$conversation,$input('a',['replyTo'=>'m-in']));
 rep(($sent[0]['replyMessageId']??null)==='m-in'&&$sent[0]['message']==='Cuesta 120','la respuesta se envía con el campo publicado para citar un mensaje');
 $free();$sender->send($connection,$conversation,$input('b'));
 rep(!array_key_exists('replyMessageId',$sent[1]),'sin cita, no se manda el campo');
 $row=query('SELECT reply_to_id,snippet,quoted_direction FROM message_replies WHERE connection_id=? AND message_id=?',[$id,'new-1'])->fetch();
 rep($row&&$row['reply_to_id']==='m-in'&&$row['snippet']==='¿Cuánto cuesta el curso?'&&$row['quoted_direction']==='inbound','se recuerda qué mensaje se citó, con su texto limpio, para enseñarlo después');
 $free();$sender->send($connection,$conversation,$input('c',['replyTo'=>'m-file']));
 rep(query('SELECT snippet FROM message_replies WHERE connection_id=? AND message_id=?',[$id,'new-3'])->fetchColumn()==='Archivo adjunto','citar un mensaje sin texto lo muestra como archivo adjunto');
 foreach(['m in','m<x>',str_repeat('a',101),['x']] as $bad)
  rep(why(fn()=>$sender->send($connection,$conversation,$input('d',['replyTo'=>$bad])))==='invalid_reply','un identificador que no es de mensaje se rechaza: '.json_encode($bad));
 $free();$before=count($sent);$sender->send($connection,$conversation,$input('a',['replyTo'=>'m-in']));
 rep(count($sent)===$before,'repetir la misma petición no envía dos veces la misma respuesta');
 rep(why(fn()=>$sender->send($connection,array_replace($conversation,['lastMessageType'=>'TYPE_EMAIL']),['requestId'=>str_repeat('f',20),'message'=>'x','channel'=>'Email','subject'=>'S','replyTo'=>'m-in']))==='reply_unsupported','en correo no se cita');

 $page=MessageReplies::annotate($id,'conv',['items'=>[['id'=>'new-1','body'=>'Cuesta 120'],['id'=>'m-in','body'=>'x'],['id'=>'new-2']],'hasMore'=>false]);
 rep(($page['items'][0]['replyTo']['id']??null)==='m-in'&&$page['items'][0]['replyTo']['snippet']==='¿Cuánto cuesta el curso?'&&!isset($page['items'][1]['replyTo'])&&!isset($page['items'][2]['replyTo']),'al leer el historial, sólo los mensajes que citaban llevan su cita');
 rep(MessageReplies::annotate($id,'otra-conv',['items'=>[['id'=>'new-1']]])['items'][0]==['id'=>'new-1'],'y la cita de una conversación no aparece en otra');
 echo "PASS $checks reply checks (simulated account, real MariaDB)\n";
} finally {if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}}
