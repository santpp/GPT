<?php
declare(strict_types=1);
/* 1.10.17 · Adjuntos por la bandeja (subida y envío) y snippets (respuestas guardadas). Cuenta simulada, MariaDB real. */
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/MessageSender.php';
require __DIR__.'/../src/Attachments.php';
require __DIR__.'/../src/Snippets.php';
$tenant=null;$suffix=bin2hex(random_bytes(6));$n=0;$dir=sys_get_temp_dir().'/att-'.$suffix;@mkdir($dir);
function ok(bool $c,string $l):void{global $n;if(!$c)throw new RuntimeException($l);$n++;echo "PASS $l\n";}
function why(Closure $f):string{try{$f();return '';}catch(AttachmentProblem|SendProblem $e){return $e->reason;}}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Adjuntos','att-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;$vault=TokenVault::fromEnvironment();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Academia',$location,$vault->seal('fake',$tenant,$location)]);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $conversation=['id'=>'conv','contactId'=>'contact','locationId'=>$location,'lastMessageType'=>'TYPE_WHATSAPP'];
 $calls=[];
 $gateway=new GhlGateway(function()use(&$calls){return new GhlClient('fake',function($path,$version,$method,$body)use(&$calls){
  $calls[]=[$path,$version,$method,$body];
  if($path==='/conversations/messages/upload')return ['status'=>200,'body'=>json_encode(['uploadedFiles'=>[$body['__file']['name']=>'https://storage.example.test/conv/'.rawurlencode($body['__file']['name'])]])];
  if($path==='/conversations/messages/upload/initiate')return ['status'=>200,'body'=>json_encode(['uploadUrl'=>'https://storage.googleapis.com/b/p?sig=1','uploadId'=>'u1','filePath'=>'p/video.mp4','expiresAt'=>1,'maxFileSize'=>104857600])];
  if($path==='__put__')return ['status'=>200,'body'=>''];
  if($path==='/conversations/messages/upload/complete')return ['status'=>200,'body'=>json_encode(['uploadedFiles'=>['video.mp4'=>'https://storage.example.test/conv/video.mp4'],'metadata'=>['size'=>1]])];
  if($path==='/conversations/messages')return ['status'=>200,'body'=>json_encode(['messageId'=>'m'.count($calls),'conversationId'=>'conv','status'=>'pending'])];
  if(str_starts_with($path,'/locations/'))return ['status'=>200,'body'=>json_encode(['templates'=>[
   ['id'=>'s1','name'=>'Bienvenida','type'=>'sms','template'=>['body'=>"Hola {{contact.first_name}}, soy {{ user.first_name }} de {{location.name}}.",'attachments'=>[]],'urlAttachments'=>['https://storage.example.test/folleto.pdf']],
   ['id'=>'s2','name'=>'Precio','type'=>'sms','template'=>['body'=>'El precio es {{custom_values.precio}}']],
   ['id'=>'e1','name'=>'Correo','type'=>'email','template'=>['html'=>'<p>x</p>']]],'totalCount'=>3])];
  return ['status'=>404,'body'=>'{}'];
 });});
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $att=new Attachments($gateway);
 $pdf=$dir.'/folleto.pdf';file_put_contents($pdf,"%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
 $r=$att->upload($connection,$conversation,$pdf,'Folleto 2026.pdf',filesize($pdf),'WhatsApp');
 $up=end($calls);
 ok($up[0]==='/conversations/messages/upload'&&$up[1]==='2021-04-15'&&$up[3]['conversationId']==='conv'&&$up[3]['contactId']==='contact'&&$up[3]['locationId']===$location,'el archivo se sube a la conversación con el contacto y la subcuenta del servidor');
 ok($up[3]['__file']['mime']==='application/pdf'&&$r['url']==='https://storage.example.test/conv/Folleto%202026.pdf','y vuelve la dirección del archivo');
 $exe=$dir.'/x.exe';file_put_contents($exe,'MZ');
 ok(why(fn()=>$att->upload($connection,$conversation,$exe,'virus.exe',2,'WhatsApp'))==='file_type','un tipo no admitido se rechaza');
 $fake=$dir.'/foto.png';file_put_contents($fake,"%PDF-1.4 no es imagen");
 ok(why(fn()=>$att->upload($connection,$conversation,$fake,'foto.png',filesize($fake),'WhatsApp'))==='file_mismatch','un archivo disfrazado (contenido distinto a la extensión) se rechaza');
 ok(why(fn()=>$att->upload($connection,$conversation,$pdf,'grande.pdf',6*1024*1024,'SMS'))==='file_too_large','más de 5 MB por un canal que no es WhatsApp se rechaza');
 ok(why(fn()=>$att->upload($connection,$conversation,$pdf,'grande.pdf',17*1024*1024,'WhatsApp'))==='file_too_large','por WhatsApp, más de 16 MB se rechaza');
 // Más de 5 MB por WhatsApp: subida en dos pasos.
 $video=$dir.'/video.mp4';file_put_contents($video,base64_decode('AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDE=').str_repeat("\0",6*1024*1024));
 $free();$calls=[];$v=$att->upload($connection,$conversation,$video,'video.mp4',filesize($video),'WhatsApp');
 $paths=array_column($calls,0);
 ok($paths===['/conversations/messages/upload/initiate','__put__','/conversations/messages/upload/complete']&&$calls[0][3]['channel']==='WHATSAPP'&&$calls[1][1]==='https://storage.googleapis.com/b/p?sig=1','por WhatsApp, un archivo de más de 5 MB va por la subida en dos pasos');
 ok($v['url']==='https://storage.example.test/conv/video.mp4','y también devuelve su dirección');
 // Envío con adjuntos
 $sender=new MessageSender($gateway);$free();$calls=[];
 $s=$sender->send($connection,$conversation,['requestId'=>str_repeat('a',32),'channel'=>'WhatsApp','message'=>'','attachments'=>[$r['url']]]);
 $sent=end($calls)[3];
 ok($s['state']==='accepted'&&$sent['attachments']===[$r['url']]&&!isset($sent['message']),'se envía un adjunto solo, sin texto');
 $free();$sender->send($connection,$conversation,['requestId'=>str_repeat('b',32),'channel'=>'WhatsApp','message'=>'Te mando el folleto','attachments'=>[$r['url'],$v['url']]]);
 $sent=end($calls)[3];
 ok($sent['message']==='Te mando el folleto'&&count($sent['attachments'])===2,'o con texto y varios adjuntos');
 ok(why(fn()=>$sender->send($connection,$conversation,['requestId'=>str_repeat('c',32),'channel'=>'WhatsApp','message'=>'x','attachments'=>['https://evil.example/malo.pdf']]))==='invalid_attachments','una dirección que no se subió en esta conversación no se puede adjuntar');
 ok(why(fn()=>$sender->send($connection,array_replace($conversation,['id'=>'otra']),['requestId'=>str_repeat('d',32),'channel'=>'WhatsApp','message'=>'x','attachments'=>[$r['url']]]))==='invalid_attachments','ni un adjunto de otra conversación');
 ok(why(fn()=>$sender->send($connection,$conversation,['requestId'=>str_repeat('e',32),'channel'=>'WhatsApp','message'=>'x','attachments'=>array_fill(0,6,$r['url'])]))==='invalid_attachments','más de 5 adjuntos se rechaza');
 ok(why(fn()=>$sender->send($connection,$conversation,['requestId'=>str_repeat('f',32),'channel'=>'WhatsApp','message'=>'  ']))==='invalid_message','sin texto ni adjuntos no se envía');
 ok(why(fn()=>$sender->send($connection,$conversation,['requestId'=>str_repeat('g',32),'channel'=>'WhatsApp','message'=>'Hola {{contact.first_name}}']))==='unfilled_fields','un mensaje con campos {{…}} sin rellenar no sale');
 // Snippets
 $free();$calls=[];$list=(new Snippets($gateway))->list($connection,true);
 $q=$calls[0][0];
 ok(str_contains($q,'/locations/'.$location.'/templates?')&&str_contains($q,'type=sms')&&str_contains($q,'originId='.$location)&&$calls[0][1]==='2021-07-28','los snippets se piden como plantillas de texto de la subcuenta');
 ok(count($list)===2&&$list[0]['name']==='Bienvenida'&&$list[0]['attachments']===['https://storage.example.test/folleto.pdf'],'solo los de texto, ordenados, con sus archivos');
 $calls=[];(new Snippets($gateway))->list($connection);ok($calls===[],'la lista se guarda unos minutos y no se vuelve a pedir');
 $x=Snippets::render($list[0]['body'],['firstName'=>'Ana','name'=>'Ana Pérez'],['name'=>'Lisbella Moreno'],'Academia');
 ok($x['text']==='Hola Ana, soy Lisbella de Academia.'&&$x['missing']===[],'los campos del contacto, de quien escribe y de la subcuenta se rellenan');
 $y=Snippets::render($list[1]['body'],[],[],'Academia');
 ok($y['missing']===['{{custom_values.precio}}']&&str_contains($y['text'],'{{custom_values.precio}}'),'lo que no se conoce queda marcado para completarlo');
 $z=Snippets::render('Hola {{contact.first_name}}',['name'=>'Pedro Gómez'],[],'');
 ok($z['text']==='Hola Pedro','sin nombre de pila, se toma la primera palabra del nombre');
 Attachments::allow($id,'conv',$list[0]['attachments']);$free();
 $sender->send($connection,$conversation,['requestId'=>str_repeat('h',32),'channel'=>'WhatsApp','message'=>$x['text'],'attachments'=>$list[0]['attachments']]);
 ok(end($calls)[3]['attachments']===['https://storage.example.test/folleto.pdf'],'el archivo de un snippet se puede enviar en esa conversación');
 // ---- 1.10.18 · respuestas guardadas propias (en Ágora) ----
 require_once __DIR__.'/../src/Installation.php';Installation::applyMigrations();
 $qp=fn(Closure $f)=>(function()use($f){try{$f();return '';}catch(QuickReplyProblem $e){return $e->reason;}})();
 $a=QuickReplies::save($tenant,$id,'conv','external',501,false,['name'=>'Saludo','body'=>'Hola {{contact.first_name}}','attachments'=>[$r['url']]]);
 $mine=QuickReplies::list($id,'external',501,false);
 ok(count($mine)===1&&$mine[0]['mine']&&$mine[0]['editable']&&!$mine[0]['shared']&&$mine[0]['attachments']===[$r['url']],'cada persona crea su respuesta, con el archivo subido en la conversación');
 ok(QuickReplies::list($id,'external',502,false)===[],'las respuestas personales no las ve el resto del equipo');
 ok($qp(fn()=>QuickReplies::save($tenant,$id,'conv','external',501,false,['name'=>'Equipo','body'=>'x','shared'=>true]))==='share_forbidden','sin administrar la subcuenta no se comparte con el equipo');
 QuickReplies::save($tenant,$id,'conv','native',1,true,['name'=>'Horario','body'=>'Atendemos de 8 a 5','shared'=>true]);
 $team=array_values(array_filter(QuickReplies::list($id,'external',502,false),fn($x)=>$x['shared']));
 ok(count($team)===1&&!$team[0]['editable'],'una compartida la ve todo el equipo, pero solo la cambia quien administra');
 ok($qp(fn()=>QuickReplies::save($tenant,$id,'conv','external',502,false,['id'=>$a['id'],'name'=>'Mía','body'=>'x']))==='edit_forbidden','nadie cambia la respuesta de otra persona');
 ok($qp(fn()=>QuickReplies::save($tenant,$id,'conv','external',501,false,['name'=>'Malo','body'=>'x','attachments'=>['https://evil.example/x.pdf']]))==='invalid_attachments','no se guarda un archivo que no se subió');
 ok($qp(fn()=>QuickReplies::save($tenant,$id,'conv','external',501,false,['name'=>'','body'=>'x']))==='invalid_name'&&$qp(fn()=>QuickReplies::save($tenant,$id,'conv','external',501,false,['name'=>'x','body'=>' ']))==='invalid_body','nombre y texto son obligatorios');
 QuickReplies::save($tenant,$id,'conv','external',501,false,['id'=>$a['id'],'name'=>'Saludo corto','body'=>'Hola','attachments'=>[$r['url']]]);
 ok(QuickReplies::list($id,'external',501,false)[0]['name']==='Saludo corto','quien la creó la puede cambiar, conservando su archivo');
 ok($qp(fn()=>QuickReplies::delete($id,'external',502,false,$a['id']))==='edit_forbidden','ni borrar la de otra persona');
 QuickReplies::delete($id,'external',501,false,$a['id']);
 ok(count(QuickReplies::list($id,'external',501,false))===1,'y la puede borrar');
}finally{
 if($tenant){query('DELETE FROM quick_replies WHERE tenant_id=?',[$tenant]);query('DELETE FROM directory_cache WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$tenant]);query('DELETE FROM message_send_attempts WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$tenant]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
 array_map('unlink',glob($dir.'/*')?:[]);@rmdir($dir);
}
echo "PASS $n attachment and snippet checks (simulated account, real MariaDB)\n";
