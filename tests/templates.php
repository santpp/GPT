<?php
declare(strict_types=1);
// Plantillas de WhatsApp: lectura defensiva de la lista, variables y envío con la
// misma forma que usa la cuenta. La cuenta se simula; la base de datos es real.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/MessageSender.php';
require __DIR__.'/../src/WhatsappTemplates.php';
$tenant=null;$suffix=bin2hex(random_bytes(6));$checks=0;
function ok(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function reason(Closure $f):string{try{$f();return '';}catch(SendProblem|TemplateProblem $e){return $e->reason;}}

// Tres formas posibles de la misma plantilla: con componentes de Meta, plana como
// la guarda la cuenta, y una variable numerada sin campo asociado.
$meta=['id'=>'tpl-1','name'=>'bienvenida','language'=>'es','category'=>'MARKETING','status'=>'APPROVED','components'=>[
  ['type'=>'HEADER','format'=>'TEXT','text'=>'Hola {{1}}'],
  ['type'=>'BODY','text'=>"¡Hola {{contact.name}}! Bienvenido a ASOCIM, en breve serás atendido por uno de nuestros asesores.\n\nConquista el mundo con idiomas 🌎"],
  ['type'=>'FOOTER','text'=>'ASOCIM'],['type'=>'BUTTONS','buttons'=>[['type'=>'QUICK_REPLY','text'=>'Quiero info']]]],
  'placeholders'=>['header'=>['{{contact.first_name}}'],'body'=>[]]];
$flat=['_id'=>'tpl-2','template'=>['name'=>'cita_recordatorio','lang'=>'es_ES','category'=>'UTILITY'],'body'=>'Tu cita es el {{1}} a las {{2}}.','status'=>'approved'];
$pending=['id'=>'tpl-3','name'=>'promo','language'=>'es','status'=>'PENDING','body'=>'x'];
$image=['id'=>'tpl-4','name'=>'catalogo','language'=>'es','status'=>'APPROVED','components'=>[['type'=>'HEADER','format'=>'IMAGE'],['type'=>'BODY','text'=>'Mira el catálogo']]];
$broken=['id'=>'tpl-5','name'=>'Nombre Con Espacios','language'=>'es','body'=>'x'];

$a=WhatsappTemplates::normalize($meta);
ok($a['name']==='bienvenida'&&$a['language']==='es'&&$a['category']==='MARKETING'&&$a['footer']==='ASOCIM'&&$a['buttons']===['Quiero info'],'a template in Meta\'s own shape is read with its parts');
ok($a['variables']['body']===[['token'=>'contact.name','fixed'=>'{{contact.name}}','label'=>'nombre del contacto']],'a field of the contact sheet is left for the account to fill');
ok($a['variables']['header'][0]['fixed']==='{{contact.first_name}}'&&$a['variables']['header'][0]['label']==='nombre','a numbered variable the account already maps is not asked for');
$b=WhatsappTemplates::normalize($flat);
ok($b['name']==='cita_recordatorio'&&$b['language']==='es_ES'&&$b['category']==='UTILITY'&&$b['id']==='tpl-2','the flat shape the account may use is read too');
ok(array_column($b['variables']['body'],'fixed')===[null,null]&&array_column($b['variables']['body'],'label')===['Variable 1','Variable 2'],'numbered variables without a mapping are asked of whoever sends');
ok(WhatsappTemplates::normalize($pending)===null,'a template Meta has not approved is not offered');
ok(WhatsappTemplates::normalize($image)['unsupported']==='media_header','a template with an image header is shown but marked as not sendable from here');
ok(WhatsappTemplates::normalize(['name'=>'Nombre Con Espacios','language'=>'es'])===null,'something that cannot be a template name is left out rather than guessed');
$why=null;WhatsappTemplates::normalize($pending,$why);
ok($why==='status_pending','and every template left out says why, for the administrator');
$bare=WhatsappTemplates::normalize(['id'=>'tpl-9','templateName'=>'aviso_pago','languageCode'=>'es-ES','status'=>'approved']);
ok($bare!==null&&$bare['preview']===false&&$bare['language']==='es_ES'&&$bare['variables']['body']===[],'a template the account sends without its text is still offered, without a preview');
ok(WhatsappTemplates::payload($bare,[],'c','1','+1')['message']==='Plantilla: aviso_pago','and the history keeps its name instead of an empty message');

$p=WhatsappTemplates::payload($a,[],'contact','920050454518509','+34642795214');
ok($p['type']==='WhatsApp'&&$p['contactId']==='contact'&&$p['whatsapp']['type']==='template','the send carries the template marker');
ok($p['whatsapp']['template']===['name'=>'bienvenida','lang'=>'es','category'=>'MARKETING']&&$p['whatsapp']['fromNumberId']==='920050454518509'&&$p['whatsapp']['toNumber']==='+34642795214','with name, language, category, the account number and the contact\'s');
ok($p['whatsapp']['placeholders']===['header'=>['{{contact.first_name}}'],'body'=>['{{contact.name}}'],'buttons'=>[]],'and the variables in the shape the account itself sends');
ok(str_starts_with($p['message'],"Hola {{contact.first_name}}\n\n¡Hola {{contact.name}}! Bienvenido a ASOCIM")&&str_ends_with($p['message'],"\n\nASOCIM"),'the text that stays in the history is the template, fields included');
ok(reason(fn()=>WhatsappTemplates::payload($b,['body'=>['1'=>'jueves']],'c','1','+1'))==='template_values','a variable left empty is refused');
$q=WhatsappTemplates::payload($b,['body'=>['1'=>"jueves\n 5",'2'=>'17:00']],'c','1','+1');
ok($q['whatsapp']['placeholders']['body']===['jueves 5','17:00']&&$q['message']==='Tu cita es el jueves 5 a las 17:00.','what is written is cleaned of line breaks, as Meta requires, and placed in the text');
ok(reason(fn()=>WhatsappTemplates::payload(WhatsappTemplates::normalize($image),[],'c','1','+1'))==='template_unsupported','an image template is not sent half-built');
ok(WhatsappTemplates::phone('+34 642 79 52 14')==='+34642795214'&&WhatsappTemplates::phone('584142736522')==='+584142736522'&&WhatsappTemplates::phone('12')===null,'the contact\'s phone is put in international form, or refused');
ok(reason(fn()=>WhatsappTemplates::numberId('abc'))==='invalid_number'&&WhatsappTemplates::numberId(' 920050454518509 ')==='920050454518509'&&WhatsappTemplates::numberId('')===null,'the account number is digits only, and can be cleared');

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Template test','tpl-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;$vault=TokenVault::fromEnvironment();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,$vault->seal('fake',$tenant,$location)]);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $conversation=['id'=>'conv','contactId'=>'contact','locationId'=>$location,'lastMessageType'=>'TYPE_WHATSAPP'];
 $calls=[];$sent=[];
 $gateway=new GhlGateway(function()use(&$calls,&$sent,$location){return new GhlClient('fake',function($path,$version,$method,$body)use(&$calls,&$sent,$location){
  $calls[]=$path;
  if(str_starts_with($path,'/locations/')){
   return ['status'=>200,'body'=>json_encode(['templates'=>[$GLOBALS['meta'],$GLOBALS['flat'],$GLOBALS['pending'],$GLOBALS['image']],'totalCount'=>4])];
  }
  $sent[]=['path'=>$path,'version'=>$version,'method'=>$method,'body'=>$body];
  return ['status'=>200,'body'=>json_encode(['messageId'=>'msg-'.count($sent),'conversationId'=>'conv','status'=>'pending'])];
 });});
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $templates=new WhatsappTemplates($gateway);
 ok(reason(fn()=>$templates->list($connection))==='number_missing','without the account\'s WhatsApp number there is nothing to ask for');
 query('UPDATE ghl_connections SET whatsapp_number_id=? WHERE id=?',['920050454518509',$id]);$connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $free();$list=$templates->list($connection);
 ok(count($list)===3&&array_column($list,'name')===['bienvenida','catalogo','cita_recordatorio'],'the account\'s approved templates are listed, pending ones left out');
 ok(str_contains($calls[0],'/locations/'.$location.'/templates?')&&str_contains($calls[0],'type=whatsapp')&&str_contains($calls[0],'originId=920050454518509'),'read through the published templates endpoint, for this number');
 $before=count($calls);$templates->list($connection);
 ok(count($calls)===$before,'a second look within ten minutes does not ask the account again');

 $diag=WhatsappTemplates::diagnosis($id);
 ok($diag['received']===4&&$diag['shown']===3&&$diag['dropped']===['status_pending'=>1]&&$diag['origin']==='number','the administrator can see how many came back, how many are shown and why the rest are not');
 ok(in_array('templates',$diag['fields']['top'],true)&&in_array('components',$diag['fields']['item'],true)&&!str_contains(json_encode($diag),'Bienvenido'),'with the names of the fields, never the content');
 query('UPDATE ghl_connections SET whatsapp_account_id=? WHERE id=?',['111222333444',$id]);$connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $free();$templates->list($connection,true);
 ok(str_contains(end($calls),'originId=111222333444')&&WhatsappTemplates::diagnosis($id)['origin']==='account','with a WhatsApp Business account id, the templates are asked with it');
 query('UPDATE ghl_connections SET whatsapp_account_id=NULL WHERE id=?',[$id]);$connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();

 $sender=new MessageSender($gateway);
 $input=['requestId'=>str_repeat('t',32),'channel'=>'WhatsApp','template'=>['id'=>'tpl-1']];
 $free();ok(reason(fn()=>$sender->send($connection,$conversation,$input))==='needs_phone','without a phone in the contact sheet nothing is sent');
 query('INSERT INTO contact_profile_cache(connection_id,contact_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)',[$id,'contact',json_encode(['id'=>'contact','name'=>'Raissa','phone'=>'+34 642 795 214'])]);
 $free();$result=$sender->send($connection,$conversation,$input+['message'=>'ignorado','toNumber'=>'+10000000000']);
 ok($result['state']==='accepted','an approved template is sent');
 $body=$sent[0]['body'];
 ok($sent[0]['path']==='/conversations/messages'&&$sent[0]['method']==='POST'&&$sent[0]['version']==='2021-04-15','through the same send endpoint as any message');
 ok($body['whatsapp']['toNumber']==='+34642795214'&&$body['whatsapp']['fromNumberId']==='920050454518509','to the phone in the contact sheet, never one sent by the browser');
 ok($body['contactId']==='contact'&&str_contains($body['message'],'{{contact.name}}'),'for the contact of this conversation, fields left for the account');
 $free();$sender->send($connection,$conversation,$input);
 ok(count($sent)===1,'repeating the same request does not send it twice');
 $free();ok(reason(fn()=>$sender->send($connection,$conversation,['requestId'=>str_repeat('u',32),'channel'=>'WhatsApp','template'=>['id'=>'tpl-3']]))==='template_unknown','a template that is not approved cannot be sent by naming it');
 $free();ok(reason(fn()=>$sender->send($connection,$conversation,['requestId'=>str_repeat('v',32),'channel'=>'WhatsApp','template'=>['id'=>'tpl-2','values'=>['body'=>['1'=>'jueves']]]]))==='template_values','nor one with a variable left empty');
 $free();$done=$sender->send($connection,$conversation,['requestId'=>str_repeat('w',32),'channel'=>'WhatsApp','template'=>['id'=>'tpl-2','values'=>['body'=>['1'=>'jueves','2'=>'17:00']]]]);
 ok($done['state']==='accepted'&&$sent[1]['body']['whatsapp']['placeholders']['body']===['jueves','17:00']&&$sent[1]['body']['whatsapp']['template']['lang']==='es_ES','the written variables travel in order, in the template\'s language');
} finally {
 if($tenant){if(isset($id))query('DELETE FROM app_state WHERE name=?',['wa-diag:'.$id]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
}
echo "PASS $checks template checks (simulated account, real MariaDB)\n";
