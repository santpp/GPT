<?php
declare(strict_types=1);
// Nota de voz por WhatsApp: audio real (generado con ffmpeg), subida en dos pasos, envío como adjunto
// y protección contra repeticiones. La cuenta y el almacén se simulan; la base de datos es real.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/VoiceNote.php';
$tenant=null;$suffix=bin2hex(random_bytes(5));$checks=0;
function vox(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function trouble(Closure $f):string{try{$f();return '';}catch(SendProblem $e){return $e->reason;}}
$dir=getenv('AUDIO_DIR')?:__DIR__.'/fixtures/audio';
foreach(['voz.ogg','voz.m4a','voz-vorbis.ogg','voz.webm'] as $f)if(!is_file("$dir/$f")){echo "SKIP: faltan los audios de prueba en $dir (ffmpeg)\n";exit(0);}
$ogg=file_get_contents("$dir/voz.ogg");$m4a=file_get_contents("$dir/voz.m4a");
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Voice','vx-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'x']);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $conversation=['id'=>'conv','contactId'=>'contact','locationId'=>$location,'lastMessageType'=>'TYPE_WHATSAPP'];

 // ---- qué audios se aceptan ---------------------------------------------------------------
 vox(trouble(fn()=>VoiceNote::check('audio/ogg',$ogg))==='','un audio OGG con Opus se acepta: es lo que WhatsApp reconoce como nota de voz');
 vox(trouble(fn()=>VoiceNote::check('audio/mp4',$m4a))==='','y uno AAC (Safari en iPhone)');
 vox(trouble(fn()=>VoiceNote::check('audio/ogg',file_get_contents("$dir/voz-vorbis.ogg")))==='voice_format','un OGG con Vorbis, que WhatsApp no acepta, se rechaza');
 vox(trouble(fn()=>VoiceNote::check('audio/webm',file_get_contents("$dir/voz.webm")))==='voice_format','el WebM que graba Chrome se rechaza con un mensaje claro: ningún canal lo admite');
 vox(trouble(fn()=>VoiceNote::check('audio/ogg',$m4a))==='voice_format','un audio que no es lo que dice ser se rechaza por su contenido, no por lo que declara');
 vox(trouble(fn()=>VoiceNote::check('audio/ogg','OggS'))==='voice_empty','una grabación vacía se rechaza');
 vox(trouble(fn()=>VoiceNote::check('audio/ogg',str_repeat('x',VoiceNote::MAX_BYTES+1)))==='voice_too_large','y una demasiado grande');

 // ---- el envío completo --------------------------------------------------------------------
 $calls=[];$uploadedUrl='https://storage.googleapis.com/agora/conversations-assets/nota-de-voz.ogg';
 $gateway=new GhlGateway(function()use(&$calls,$uploadedUrl){return new GhlClient('fake',function($path,$version,$method,$body)use(&$calls,$uploadedUrl){
  $calls[]=[$method,$path,$body];
  if($path==='/conversations/messages/upload')return ['status'=>200,'body'=>json_encode(['uploadedFiles'=>['nota-de-voz.ogg'=>$uploadedUrl]])];
  if($path==='/conversations/messages')return ['status'=>200,'body'=>json_encode(['messageId'=>'msg-voz-'.count($calls),'conversationId'=>'conv','status'=>'pending'])];
  throw new GhlApiException(404);});});
 $uploads=function()use(&$calls){return count(array_filter($calls,fn($c)=>$c[1]==='/conversations/messages/upload'));};
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $voice=new VoiceNote($gateway,false);
 $free();$result=$voice->send($connection,$conversation,str_repeat('v',20),'audio/ogg; codecs=opus',$ogg);
 $up=$calls[0][2];$file=$up['__file']??[];
 vox($calls[0][1]==='/conversations/messages/upload'&&$up['conversationId']==='conv'&&$up['contactId']==='contact'&&$up['locationId']===$location,'se sube el audio a la conversación de ese contacto, con la subida clásica');
 vox(($file['name']??'')==='nota-de-voz.ogg'&&($file['mime']??'')==='audio/ogg'&&($file['bytes']??'')===$ogg,'el archivo va tal cual, como nota-de-voz.ogg de tipo audio/ogg');
 $sent=$calls[1][2];
 vox($calls[1][1]==='/conversations/messages'&&$sent['type']==='WhatsApp'&&$sent['contactId']==='contact'&&$sent['attachments']===[$uploadedUrl]&&!isset($sent['message']),'se envía por WhatsApp al contacto de la conversación, con el audio como adjunto y sin texto');
 vox($result['state']==='accepted'&&$result['url']===$uploadedUrl,'y se devuelve la dirección para poder reproducirlo en el chat');

 // ---- repeticiones ------------------------------------------------------------------------------
 $free();$before=count($calls);$again=$voice->send($connection,$conversation,str_repeat('v',20),'audio/ogg',$ogg);
 vox(count($calls)===$before&&$uploads()===1&&$again['state']==='accepted','repetir la misma nota con la misma clave no sube ni envía nada más');
 $free();$voice->send($connection,$conversation,str_repeat('w',20),'audio/ogg',$ogg);
 vox($uploads()===2,'una nota nueva, con otra clave, sí se envía');

 // ---- cuando la cuenta falla ---------------------------------------------------------------------------
 foreach([[415,'voice_upload_rejected'],[413,'voice_upload_rejected'],[403,'voice_upload_rejected'],[500,'voice_upload_rejected']] as [$status,$reason]) {
  $bad=new GhlGateway(function()use($status){return new GhlClient('fake',function()use($status){throw new GhlApiException($status);});});
  $free();$e=null;try{(new VoiceNote($bad,false))->send($connection,$conversation,str_pad('f'.$status,20,'x'),'audio/ogg',$ogg);}catch(SendProblem $x){$e=$x;}
  vox($e&&$e->reason===$reason,"si la cuenta responde $status al subir, se dice con claridad");
 }
 $noUrl=new GhlGateway(function(){return new GhlClient('fake',function($path){return $path==='/conversations/messages/upload'?['status'=>200,'body'=>json_encode(['uploadedFiles'=>new stdClass])]:throw new GhlApiException(404);});});
 $free();vox(trouble(fn()=>(new VoiceNote($noUrl,false))->send($connection,$conversation,str_repeat('g',20),'audio/ogg',$ogg))==='voice_upload_failed','si la cuenta no devuelve la dirección del audio, no se envía nada');
 vox(trouble(fn()=>$voice->send($connection,$conversation,'corta','audio/ogg',$ogg))==='invalid_message','la petición lleva su clave contra repeticiones');
 vox(trouble(fn()=>$voice->send($connection,array_replace($conversation,['lastMessageType'=>'TYPE_EMAIL','contactId'=>null]),str_repeat('h',20),'audio/ogg',$ogg))!=='','una conversación sin contacto de WhatsApp no recibe notas de voz');
 echo "PASS $checks voice checks (real audio, simulated account, real MariaDB)\n";
} finally {if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}}

// Lo que la cuenta explica al rechazar se conserva (sin marcas) para poder diagnosticar.
$client=new GhlClient('tok',fn()=>['status'=>400,'headers'=>[],'body'=>json_encode(['message'=>['attachments must be supported by GoHighLevel'],'statusCode'=>400])]);
try{$client->sendMessage(['type'=>'WhatsApp']);$detail='none';}catch(GhlApiException $e){$detail=$e->detail;}
vox($detail==='attachments must be supported by la cuenta','the account\'s reason for a rejection is kept, without brand names');
