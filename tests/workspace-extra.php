<?php
declare(strict_types=1);
// Las rutas nuevas del servidor (1.11.0): tareas, recordatorios, auditoría, identidad y resumen,
// contra el servidor de desarrollo y con la cuenta propietaria.
require __DIR__.'/../src/bootstrap.php';
$suffix=bin2hex(random_bytes(5));$tenant=null;$n=0;
function ok(bool $v,string $l):void{global $n;if(!$v){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
$cookie=tempnam(sys_get_temp_dir(),'agora-wx-');$csrf='';
function call(string $path,string $method='GET',?array $body=null,bool $raw=false):array{
 global $cookie,$csrf;
 $ch=curl_init('http://localhost:8080/api/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_HEADER=>$raw,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Origin: http://localhost:8080','X-CSRF-Token: '.$csrf]]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $out=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$size=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
 if($raw)return [$code,substr((string)$out,0,$size),substr((string)$out,$size)];
 return [$code,json_decode($out?:'{}',true)];
}
function Branding_has(int $t):bool{return (bool)query('SELECT 1 FROM workspace_branding WHERE tenant_id=? AND logo IS NOT NULL',[$t])->fetchColumn();}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Espacio '.$suffix,'wx-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $email='owner-'.$suffix.'@example.test';$password=bin2hex(random_bytes(12));
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,$email,password_hash($password,PASSWORD_DEFAULT)]);$owner=(int)db()->lastInsertId();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta A','loc-wx-'.$suffix,'x']);$conn=(int)db()->lastInsertId();
 query('INSERT INTO conversation_index(connection_id,conversation_id,payload) VALUES(?,?,?)',[$conn,'cv1',json_encode(['id'=>'cv1','contactId'=>'ct1','contactName'=>'Mariana Pérez','locationId'=>'loc-wx-'.$suffix,'lastMessageDirection'=>'inbound'])]);
 ok(call('workspace/tareas')[0]===401,'tasks need a session');
 [$c,$login]=call('login','POST',['email'=>$email,'password'=>$password]);$csrf=$login['csrf']??'';ok($c===200&&$csrf!=='','the owner signs in');

 // ---- tareas
 $month=gmdate('Y-m');$today=gmdate('Y-m-d');
 [$c,$task]=call('workspace/tareas','POST',['titulo'=>'Llamar a Mariana','detalle'=>'Pidió el convenio','vence'=>$today,'hora'=>'14:30','responsable'=>$email,'origen'=>'manual']);
 ok($c===201&&str_starts_with($task['id'],'tk_')&&$task['estado']==='pendiente'&&$task['hora']==='14:30','a task is created with its date and time');
 ok(call('workspace/tareas','POST',['titulo'=>'','vence'=>$today])[0]===422,'a task needs a title');
 ok(call('workspace/tareas','POST',['titulo'=>'x','vence'=>'31-12-2026'])[0]===422,'and a valid date');
 [$c,$list]=call('workspace/tareas?period='.$month);ok($c===200&&count($list['items'])===1&&$list['resumen']['pendientes']===1,'the list shows it and counts pending ones');
 ok(call('workspace/tareas/'.$task['id'].'/estado','POST',['estado'=>'hecha'])[0]===200,'a task is marked done');
 [,$list]=call('workspace/tareas?period='.$month);ok($list['items'][0]['estado']==='hecha'&&$list['resumen']['hechas']===1,'and it shows as done');
 ok(call('workspace/tareas/tk_999999999/estado','POST',['estado'=>'hecha'])[0]===404,'a task that does not exist is a 404');
 ok(call('workspace/tareas/'.$task['id'].'/estado','POST',['estado'=>'rara'])[0]===422,'an unknown state is refused');
 [,$list]=call('workspace/tareas?period='.$month.'&usuario='.rawurlencode('Otra Persona'));ok($list['items']===[],'the list can be filtered by person');

 // ---- recordatorios
 $at=gmdate('Y-m-d',time()+86400).'T09:00';
 [$c,$rem]=call('workspace/reminders?connectionId='.$conn.'&conversationId=cv1','POST',['at'=>$at,'nota'=>'Quedó en confirmar con su esposo']);
 ok($c===200&&$rem['at']===$at,'a follow-up reminder is saved');
 [,$tasks]=call('workspace/tareas?period='.gmdate('Y-m',time()+86400));
 $fromReminder=array_values(array_filter($tasks['items'],fn($t)=>$t['origen']==='recordatorio'));
 ok(count($fromReminder)===1&&$fromReminder[0]['conversationId']==='cv1'&&$fromReminder[0]['contacto']==='Mariana Pérez','it also creates the task, linked to the conversation');
 ok(call('workspace/reminders?connectionId='.$conn.'&conversationId=cv1','POST',['at'=>'mañana'])[0]===422,'a bad date is refused');
 ok(call('workspace/reminders?connectionId='.$conn.'&conversationId=cv-no','POST',['at'=>$at])[0]===404,'a conversation Ágora has not seen is refused');
 call('workspace/reminders?connectionId='.$conn.'&conversationId=cv1','POST',['at'=>gmdate('Y-m-d',time()+2*86400).'T10:00','nota'=>'Mejor pasado mañana']);
 ok((int)query("SELECT COUNT(*) FROM workspace_tasks WHERE tenant_id=? AND origin='recordatorio'",[$tenant])->fetchColumn()===1,'changing it updates the same task instead of adding another');
 require_once __DIR__.'/../src/Reminders.php';
 ok(isset(Reminders::forInbox($conn,'n'.$owner)['cv1'])&&Reminders::forInbox($conn,'n'.$owner)['cv1']['nota']==='Mejor pasado mañana','the inbox gets the reminders of whoever is looking');
 ok(Reminders::forInbox($conn,'n999999')===[],'and nobody else sees them');
 Reminders::fulfil($tenant,$conn,'cv1');
 ok(Reminders::forInbox($conn,'n'.$owner)===[]&&(int)query("SELECT COUNT(*) FROM workspace_tasks WHERE tenant_id=? AND origin='recordatorio' AND state='hecha'",[$tenant])->fetchColumn()===1,'sending a message fulfils it: the reminder goes and its task is done');
 call('workspace/reminders?connectionId='.$conn.'&conversationId=cv1','POST',['at'=>$at]);
 ok(call('workspace/reminders?connectionId='.$conn.'&conversationId=cv1','DELETE')[0]===200&&(int)query("SELECT COUNT(*) FROM workspace_tasks WHERE tenant_id=? AND origin='recordatorio' AND state='pendiente'",[$tenant])->fetchColumn()===0,'removing a reminder removes its pending task');

 // ---- alcance de quien supervisa una subcuenta
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta B','loc-wx-b-'.$suffix,'x']);$connB=(int)db()->lastInsertId();
 require_once __DIR__.'/../src/WorkspaceTasks.php';
 WorkspaceTasks::create($tenant,'n'.$owner,['titulo'=>'Tarea de la cuenta B','vence'=>gmdate('Y-m-d'),'responsable'=>'Alguien de B'],$connB);
 WorkspaceTasks::create($tenant,'n'.$owner,['titulo'=>'Tarea de la cuenta A','vence'=>gmdate('Y-m-d'),'responsable'=>'Alguien de A'],$conn);
 $seenBySupervisor=array_column(WorkspaceTasks::list($tenant,gmdate('Y-m'),'e77',['Supervisora A'],true,'',[$conn])['items'],'titulo');
 ok(in_array('Tarea de la cuenta A',$seenBySupervisor,true)&&!in_array('Tarea de la cuenta B',$seenBySupervisor,true),'someone who supervises one sub-account sees its tasks, not those of another company in the same space');

 // ---- identidad
 [$c,$brand]=call('workspace/branding');ok($c===200&&$brand['nombre']==='Espacio '.$suffix&&$brand['logo']===null&&$brand['bloqueado']===false,'the space starts with its own name and no logo');
 $png='data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
 [$c,$brand]=call('workspace/branding','POST',['nombre'=>'ASOCIM','logo'=>$png]);
 ok($c===200&&$brand['nombre']==='ASOCIM'&&str_contains((string)$brand['logo'],'/api/workspace/branding/logo'),'the owner sets the name and logo');
 [$c,$headers,$bytes]=call('workspace/branding/logo','GET',null,true);
 ok($c===200&&stripos($headers,'Content-Type: image/png')!==false&&str_starts_with($bytes,"\x89PNG")&&stripos($headers,'nosniff')!==false,'the logo is served as an image, never as a page');
 $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(1)</script><a href="javascript:alert(1)"><rect width="4" height="4"/></a><circle cx="5" cy="5" r="3" fill="#ad103c" style="fill:url(http://evil.test/x)"/></svg>';
 [$c,$brand]=call('workspace/branding','POST',['nombre'=>'ASOCIM','logo'=>'data:image/svg+xml;base64,'.base64_encode($svg)]);
 ok($c===200&&$brand['logo']!==null,'an SVG logo is accepted, as it always was');
 [$c,$headers,$bytes]=call('workspace/branding/logo','GET',null,true);
 ok($c===200&&stripos($headers,'Content-Type: image/svg+xml')!==false&&stripos($headers,'sandbox')!==false&&stripos($headers,'nosniff')!==false,'and is served as an image, in a sandbox, never as a page');
 ok(str_contains($bytes,'<circle')&&!preg_match('/script|onload|javascript:|evil\.test|<a\b/i',$bytes),'with the scripts, event handlers, outside links and external references taken out of it');
 ok(call('workspace/branding','POST',['nombre'=>'X','logo'=>'data:image/svg+xml;base64,'.base64_encode('<!DOCTYPE svg [<!ENTITY a "b">]><svg xmlns="http://www.w3.org/2000/svg"/>')])[0]===422,'an SVG with a DOCTYPE or entities is refused');
 ok(call('workspace/branding','POST',['nombre'=>'X','logo'=>'data:image/svg+xml;base64,'.base64_encode('<html><body/></html>')])[0]===422,'and so is a file that is not an SVG');
 ok(call('workspace/branding','POST',['nombre'=>'X','logo'=>'data:image/png;base64,'.base64_encode('no soy una imagen')])[0]===422,'a file that only claims to be an image is refused');
 ok(call('workspace/branding','POST',['nombre'=>'X','logo'=>'data:image/png;base64,'.base64_encode(str_repeat('a',410000))])[0]===422,'a logo over 400 KB is refused');
 ok(call('workspace/branding','POST',['nombre'=>'X','logo'=>'data:image/png;base64,'.base64_encode(str_repeat('a',900000))])[0]===413,'and a request far over that is cut off before it is read');
 ok(call('workspace/branding','POST',['nombre'=>'','logo'=>null])[0]===422,'the name cannot be empty');
 [$c,$kept]=call('workspace/branding','POST',['nombre'=>'ASOCIM Digital','logo'=>$brand['logo']]);
 ok($c===200&&$kept['nombre']==='ASOCIM Digital'&&$kept['logo']!==null&&Branding_has($tenant),'changing only the name keeps the logo that is already there');
 [$c,$brand]=call('workspace/branding','POST',['nombre'=>'ASOCIM','logo'=>null]);ok($c===200&&$brand['logo']===null,'the logo can be removed');
 call('admin/maintenance','POST',['on'=>true]);[,$brand]=call('workspace/branding');call('admin/maintenance','POST',['on'=>false]);
 ok($brand['bloqueado']===true,'the space shows as locked while it is in maintenance');

 // ---- resumen
 [$c,$sum]=call('workspace/resumen?period='.$month);
 ok($c===200&&count($sum['serie'])===6&&$sum['serie'][5]['ym']===$month&&$sum['espacios'][0]['nombre']==='Cuenta A'&&$sum['superadmins'][0]['nombre']===$email,'the business summary lists the spaces and owners, with six months of series');
 ok($sum['mrr']===0&&$sum['origenDeCobros']==='sin_conectar','and says the charges are not connected yet instead of inventing them');

 // ---- auditoría
 [$c,$audit]=call('workspace/auditoria?period='.$month);
 $texts=array_column($audit['items'],'accionTexto');
 ok($c===200&&in_array('Inició sesión',$texts,true)&&in_array('Cambió la identidad del espacio',$texts,true)&&in_array('Puso un recordatorio de seguimiento',$texts,true),'the audit trail recorded the sign-in, the identity change and the reminder');
 $first=$audit['items'][0];ok($first['actor']===$email&&$first['rol']==='Propietario'&&isset($first['dispositivo']),'each entry says who, with what role and from what device');
 ok(in_array('Puso Ágora en mantenimiento',$texts,true),'putting the app in maintenance is recorded');
 [,$only]=call('workspace/auditoria?period='.$month.'&modulo=ajuste');ok(count($only['items'])>=2&&count(array_filter($only['items'],fn($i)=>$i['modulo']!=='ajuste'))===0,'the trail can be filtered by module');
 [,$contact]=call('workspace/auditoria?period='.$month.'&contacto=Mariana');ok(count($contact['items'])>=1&&$contact['items'][0]['contacto']==='Mariana Pérez','and by contact');
 [,$none]=call('workspace/auditoria?period=2020-01');ok($none['items']===[]&&$none['resumen']['total']===0,'a month with nothing is empty');
 ok(call('workspace/auditoria?period='.$month,'DELETE')[0]===405&&call('workspace/auditoria?period='.$month,'POST',['x'=>1])[0]===405,'the trail itself cannot be edited or replaced through the API');
 require_once __DIR__.'/../src/Roles.php';
 ok(Roles::can('SUPERADMIN','auditoria')&&!Roles::can('ADMIN','auditoria')&&!Roles::can('SUPERVISOR','auditoria')&&!Roles::can('EJECUTIVO','auditoria')&&!Roles::can('CONSULTA','auditoria'),'only the owner can open the audit trail');

 // ---- depurar: sólo lo de hace seis meses o más
 $old=gmdate('Y-m-d H:i:s',strtotime('-7 months'));$edge=gmdate('Y-m-d H:i:s',strtotime('-5 months -20 days'));$fresh=gmdate('Y-m-d H:i:s',strtotime('-1 day'));
 foreach([[$old,'Registro viejo A'],[$old,'Registro viejo B'],[$edge,'Registro de hace casi seis meses'],[$fresh,'Registro de ayer']] as [$at,$text])
   query("INSERT INTO audit_log(tenant_id,at,actor_name,action,action_text,module) VALUES(?,?,?,'edito',?,'ajuste')",[$tenant,$at,'Alguien',$text]);
 [$c,$purged]=call('workspace/auditoria/depurar','POST',[]);
 ok($c===200&&$purged['borrados']===2,'purging deletes the entries from six months ago or more, and only those');
 $left=array_column(query('SELECT action_text FROM audit_log WHERE tenant_id=?',[$tenant])->fetchAll(),'action_text');
 ok(in_array('Registro de hace casi seis meses',$left,true)&&in_array('Registro de ayer',$left,true)&&!in_array('Registro viejo A',$left,true),'what is younger than six months stays, even by a few days');
 ok(in_array('Borró 2 registros de hace seis meses o más',$left,true),'and the purge itself is recorded');
 [,$again]=call('workspace/auditoria/depurar','POST',[]);ok($again['borrados']===0,'purging again deletes nothing');
 ok(call('workspace/auditoria/depurar','POST',['hasta'=>'2099-01'])[0]===200&&(int)query('SELECT COUNT(*) FROM audit_log WHERE tenant_id=? AND at>?',[$tenant,gmdate('Y-m-d H:i:s',strtotime('-6 months'))])->fetchColumn()>=3,'asking for a later cut-off changes nothing: the server decides the date');
 $src=(string)file_get_contents(__DIR__.'/../src/Audit.php').(string)file_get_contents(__DIR__.'/../src/workspace-extra.php');
 ok(preg_match_all('/DELETE\s+FROM\s+audit_log/i',$src)===1&&preg_match('/DELETE FROM audit_log WHERE tenant_id=\? AND at < \?/',$src)===1&&!preg_match('/UPDATE\s+audit_log/i',$src),'the only delete in the code is that one, with its age limit; nothing updates an entry');
 call('logout','POST',[]);
 echo "$n workspace-extra checks\n";
}finally{
 if($tenant){foreach(['audit_log','workspace_tasks','workspace_branding'] as $t)query("DELETE FROM $t WHERE tenant_id=?",[$tenant]);query('DELETE FROM device_sessions WHERE tenant_id=?',[$tenant]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
 query('DELETE FROM login_attempts WHERE key_hash IN (?,?)',[hash('sha256','127.0.0.1|owner-'.$suffix.'@example.test'),hash('sha256','::1|owner-'.$suffix.'@example.test')]);
 @unlink($cookie);
}
