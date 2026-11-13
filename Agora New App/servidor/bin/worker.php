<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
$db=new Agora\Core\DB($config['db']);
$lock='agora_worker_'.substr(hash('sha256',(string)($config['db']['name']??$config['db']['path']??'agora')),0,30);
if(!(int)$db->one('SELECT GET_LOCK(?,0) ok',[$lock])['ok'])exit;
function ghl(array $c,string $method,string $path,?array $body=null):array{
 $h=curl_init('https://services.leadconnectorhq.com'.$path);
 curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$c['token'],'Version: 2021-07-28','Accept: application/json','Content-Type: application/json']]);
 if($body!==null)curl_setopt($h,CURLOPT_POSTFIELDS,Agora\Core\DB::json($body));
 $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);$err=curl_errno($h);curl_close($h);
 // Do not log tokens, remote response bodies or message content.
 if($err||$status<200||$status>=300)throw new RuntimeException('Proveedor HTTP '.$status.'; transporte '.$err);
 $out=json_decode($raw,true);if(!is_array($out))throw new RuntimeException('Respuesta no válida del proveedor.');return $out;
}
try{
 // A crash after an external send can be ambiguous. Never automatically send it twice.
 $db->run("UPDATE agora_jobs SET status='manual_review',last_error='Proceso interrumpido; verificar entrega antes de reintentar.' WHERE status='running' AND locked_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)");
 foreach(['agora_sessions','agora_rate_limits'] as $table)$db->run("DELETE FROM $table WHERE expires_at<UTC_TIMESTAMP() LIMIT 5000");
 $db->run('DELETE FROM agora_idempotency WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY) LIMIT 5000');
 $max=50;foreach($argv as $arg)if(str_starts_with($arg,'--max-jobs='))$max=max(1,min(100,(int)substr($arg,11)));
 for($n=0;$n<$max;$n++){
  $job=$db->transaction(function()use($db){$r=$db->one("SELECT * FROM agora_jobs WHERE status='queued' AND available_at<=UTC_TIMESTAMP() ORDER BY id LIMIT 1 FOR UPDATE");if($r)$db->run("UPDATE agora_jobs SET status='running',attempts=attempts+1,locked_at=UTC_TIMESTAMP() WHERE id=?",[$r['id']]);return $r;});if(!$job)break;
  $sent=false;$status='failed';$error=null;$payload=json_decode($job['payload'],true);
  try{
   // Agora New App: credenciales de la ficha de la subcuenta (cifradas); si no hay, las de config.php.
   $c=(new Agora\Services\GhlConnection($db,$config))->credentials((int)$job['tenant_id']);if(!$c)throw new RuntimeException('Integración sin configurar.');
   if($job['kind']!=='ghl.send')throw new RuntimeException('Tipo de trabajo no soportado.');
   $contact=ghl($c,'GET','/contacts/'.rawurlencode($payload['contactId']));
   if(($contact['contact']['locationId']??null)!==$c['location_id'])throw new RuntimeException('Contacto fuera de la ubicación configurada.');
   $sent=true;$response=ghl($c,'POST','/conversations/messages',array_intersect_key($payload,array_flip(['contactId','type','message','subject'])));
   if(empty($response['messageId']))throw new RuntimeException('El proveedor no confirmó un identificador de mensaje.');$status='done';
  }catch(Throwable $e){$status=$sent?'manual_review':'failed';$error=$e->getMessage();}
  $db->transaction(function()use($db,$job,$payload,$status,$error){
   $db->run('UPDATE agora_jobs SET status=?,last_error=? WHERE id=?',[$status,$error,$job['id']]);
   if($job['kind']==='ghl.send'){
    $r=$db->one("SELECT * FROM agora_records WHERE tenant_id=? AND kind='message' AND uid=? FOR UPDATE",[$job['tenant_id'],$payload['messageId']]);
    if($r){$d=json_decode($r['data'],true);$d['status']=$status==='done'?'sent':$status;$db->run('UPDATE agora_records SET data=?,version=version+1 WHERE seq=?',[Agora\Core\DB::json($d),$r['seq']]);}
   }
  });
  echo 'Job '.$job['id'].': '.$status.PHP_EOL;
 }
}finally{$db->run('SELECT RELEASE_LOCK(?)',[$lock]);}
