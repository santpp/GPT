<?php
declare(strict_types=1);
// La subcuenta de demostración: datos ficticios servidos desde Ágora, sin llamar nunca a la cuenta de origen.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/DemoSpace.php';
$suffix=bin2hex(random_bytes(5));$tenant=null;$n=0;
function dk(bool $v,string $l):void{global $n;if(!$v){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
$cookie=tempnam(sys_get_temp_dir(),'agora-demo-');$csrf='';
function dcall(string $path,string $method='GET',?array $body=null):array{
 global $cookie,$csrf;
 $ch=curl_init('http://localhost:8080/api/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Origin: http://localhost:8080','X-CSRF-Token: '.$csrf]]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $out=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
 return [$code,json_decode($out?:'{}',true)];
}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Demo test '.$suffix,'demo-test-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $email='owner-'.$suffix.'@example.test';$password=bin2hex(random_bytes(12));
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,$email,password_hash($password,PASSWORD_DEFAULT)]);
 DemoSpace::ensure($tenant);DemoSpace::ensure($tenant);
 dk((int)query('SELECT COUNT(*) FROM ghl_connections WHERE tenant_id=? AND is_demo=TRUE',[$tenant])->fetchColumn()===1,'the demo sub-account is created once, however many times it is asked for');
 $conn=(int)query('SELECT id FROM ghl_connections WHERE tenant_id=? AND is_demo=TRUE',[$tenant])->fetchColumn();
 dk((int)query('SELECT COUNT(*) FROM external_users WHERE connection_id=?',[$conn])->fetchColumn()===4,'with a made-up team of four');
 [$c,$login]=dcall('login','POST',['email'=>$email,'password'=>$password]);$csrf=$login['csrf']??'';dk($c===200,'the owner signs in');
 [,$conns]=dcall('workspace/connections');dk(in_array('Demo · Academia Horizonte',array_column($conns['items'],'name'),true),'the owner finds it among the sub-accounts');
 $q='connectionId='.$conn;
 [$c,$sync]=dcall('workspace/sync?'.$q.'&limit=20','POST');dk($c===200,'syncing the inbox works');
 [$c,$inbox]=dcall('workspace/inbox?'.$q.'&limit=20');
 dk($c===200&&count($inbox['items'])===8&&$inbox['stale']===false&&$inbox['unread']>=5,'the inbox has eight conversations, current, with unread ones');
 $types=array_unique(array_column($inbox['items'],'lastMessageType'));sort($types);
 dk($types===['TYPE_EMAIL','TYPE_FACEBOOK','TYPE_INSTAGRAM','TYPE_SMS','TYPE_WHATSAPP'],'across WhatsApp, Instagram, Facebook, SMS and email');
 $first=$inbox['items'][0]['id'];
 [$c,$msgs]=dcall('workspace/messages?'.$q.'&conversationId='.$first);dk($c===200&&count($msgs['items'])>=2&&isset($msgs['channels']),'a conversation opens with its messages and the channels to answer by');
 [$c,$contact]=dcall('workspace/contact?'.$q.'&conversationId='.$first);dk($c===200&&str_contains($contact['contact']['phone']??'','555'),'the contact sheet opens, with fictional phone numbers');
 [$c,$dash]=dcall('workspace/dashboard?'.$q.'&tz=UTC');dk($c===200&&$dash['conversations']['total']===8&&$dash['opportunities']['count']===6,'the dashboard shows the made-up business');
 [$c,$won]=dcall('workspace/won?'.$q.'&period='.gmdate('Y-m'));dk($c===200&&$won['totals']['count']===5&&$won['totals']['missing']===2,'five won sales this month, two of them with no amount (to show the alert)');
 [$c,$alert]=dcall('workspace/won-alert?'.$q);dk($c===200&&$alert['team']['missing']===2,'and the team alert counts those two');
 [$c,$board]=dcall('workspace/board-month?'.$q.'&period='.gmdate('Y-m'));dk($c===200&&$board['sales']['closings']===5&&count($board['team'])===3,'the month board lists the three sellers');
 [$c,$pipes]=dcall('workspace/pipelines?'.$q);dk($c===200&&count($pipes['pipelines'])===1,'there is one pipeline with its stages');
 [$c,$ops]=dcall('workspace/dia?'.$q.'&date='.gmdate('Y-m-d').'&period='.gmdate('Y-m'));dk($c===200&&$ops['conversacionesDia']>=3&&$ops['sinLeer']>=5,'the day operation report works on it');
 [$c,$eff]=dcall('workspace/eficacia?'.$q.'&period='.gmdate('Y-m'));dk($c===200&&$eff['cerradas']===5&&$eff['valorTotal']===2030.5,'and the sales efficiency');
 [$c,$ads]=dcall('workspace/ads?'.$q.'&period='.gmdate('Y-m').'&date='.gmdate('Y-m-d'));dk($c===200&&$ads['estado']==='ok'&&$ads['mes']>0,'and the ad spend');
 // lo que se hace se recuerda
 $reqId=bin2hex(random_bytes(16));
 [$c,$sent]=dcall('workspace/send?'.$q.'&conversationId='.$first,'POST',['requestId'=>$reqId,'message'=>'Hola, te escribo desde la demostración.','channel'=>'WhatsApp']);
 dk($c===200&&($sent['state']??'')==='accepted','sending a message in the demo is accepted');
 [,$msgs2]=dcall('workspace/messages?'.$q.'&conversationId='.$first.'&refresh=1');dk(in_array('Hola, te escribo desde la demostración.',array_column($msgs2['items'],'body'),true),'and it shows up in the conversation');
 [$c,$opp]=dcall('workspace/opportunity/status?'.$q.'&opportunityId=do1','POST',['status'=>'won','monetaryValue'=>1860,'requestId'=>bin2hex(random_bytes(16))]);
 dk($c===200,'closing a sale is accepted');
 query("DELETE FROM directory_cache WHERE connection_id=?",[$conn]);query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$conn]);
 [,$won2]=dcall('workspace/won?'.$q.'&period='.gmdate('Y-m'));dk($won2['totals']['count']===6,'and it appears among the won sales');
 DemoSpace::reset();
 query("DELETE FROM directory_cache WHERE connection_id=?",[$conn]);query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$conn]);
 [,$won3]=dcall('workspace/won?'.$q.'&period='.gmdate('Y-m'));dk($won3['totals']['count']===5,'the demo can be reset to how it started');
 $src=(string)file_get_contents(__DIR__.'/../src/DemoSpace.php');
 dk(!preg_match('/curl_|file_get_contents\(\s*[\'"]http|https?:\/\/(?!demo\.agora|www\.w3)/',$src),'and its code makes no network call at all');
 echo "$n demo checks\n";
}finally{
 if($tenant){foreach(['audit_log','workspace_tasks','workspace_branding'] as $t)query("DELETE FROM $t WHERE tenant_id=?",[$tenant]);query('DELETE FROM device_sessions WHERE tenant_id=?',[$tenant]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
 query('DELETE FROM login_attempts WHERE key_hash IN (?,?)',[hash('sha256','127.0.0.1|owner-'.$suffix.'@example.test'),hash('sha256','::1|owner-'.$suffix.'@example.test')]);
 DemoSpace::reset();@unlink($cookie);
}
