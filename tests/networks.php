<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/MessageSender.php';
require __DIR__.'/../src/ConversationNetworks.php';
$n=0;function nk(bool $ok,string $l):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
$s=bin2hex(random_bytes(4));
query("INSERT INTO tenants(name,location_id) VALUES(?,?)",['Redes '.$s,'net-'.$s]);$t=(int)db()->lastInsertId();
try{
  query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$t,'C','loc-'.$s,'x']);$c=(int)db()->lastInsertId();
  $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$c])->fetch();
  $conv=['id'=>'conv-n','contactId'=>'k1','locationId'=>'loc-'.$s,'lastMessageType'=>'TYPE_WHATSAPP'];
  $state=function()use($connection,$conv){$m=[];foreach(MessageSender::availableChannels($connection,$conv) as $o)$m[$o['type']]=$o['available'];return $m;};
  nk($state()['IG']===false&&$state()['FB']===false,'with the last message on WhatsApp, Instagram and Messenger are not offered by themselves');
  ConversationNetworks::remember($c,'conv-n',[['id'=>'m1','direction'=>'outbound','messageType'=>'TYPE_INSTAGRAM'],['id'=>'m2','direction'=>'inbound','messageType'=>'TYPE_WHATSAPP']]);
  nk($state()['IG']===false,'a message we sent on Instagram, or one from another channel, does not unlock it');
  ConversationNetworks::remember($c,'conv-n',[['id'=>'m3','direction'=>'inbound','messageType'=>'TYPE_INSTAGRAM_COMMENT']]);
  $st=$state();
  nk($st['IG']===true&&$st['FB']===false&&$st['WhatsApp']===true,'once the contact has written from Instagram (even a comment), it is offered, next to the thread\'s own channel');
  ConversationNetworks::fromListing($c,['id'=>'conv-n','lastMessageDirection'=>'inbound','lastMessageType'=>'TYPE_FACEBOOK']);
  nk($state()['FB']===true,'the listing also remembers the network of the contact\'s last message');
  nk(ConversationNetworks::seen($c,'otra')===[],'another conversation is not affected');
  ConversationNetworks::remember($c,'conv-n',[]);
  nk($state()['IG']===true,'and what was seen is never taken away');
}finally{
  query('DELETE FROM conversation_networks WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$t]);
  query('DELETE FROM ghl_connections WHERE tenant_id=?',[$t]);query('DELETE FROM tenants WHERE id=?',[$t]);
}
echo "PASS $n conversation network checks\n";
