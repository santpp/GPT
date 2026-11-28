<?php
declare(strict_types=1);
// 1.10.36 · Plantillas de WhatsApp por flujos de trabajo: el propietario elige flujos de la cuenta (varios, buscando por nombre);
// la Bandeja mete al contacto en el flujo; sin dobles; seguimiento cuando el mensaje aparece. La cuenta se simula; la base es real.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Installation.php';
require __DIR__.'/../src/WhatsappFlows.php';
// 1.10.37 · En un servidor ya instalado (sin las tablas nuevas) la revisión de arranque las crea sola.
query('DROP TABLE IF EXISTS whatsapp_flow_sends');query('DROP TABLE IF EXISTS whatsapp_flows');
$ready=true;foreach(Installation::checks() as $c)if(!$c['ready'])$ready=false;
$tablas=query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('whatsapp_flows','whatsapp_flow_sends')")->fetchColumn();
if(!$ready||(int)$tablas!==2)throw new RuntimeException('an installed server does not create the workflow tables on its own');
echo "PASS an installed server without the new tables creates them on its own at startup\n";
$tenant=null;$suffix=bin2hex(random_bytes(6));$checks=0;
function ok(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function reason(Closure $f):string{try{$f();return '';}catch(FlowProblem $e){return $e->reason;}}
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Flows test','wf-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;$vault=TokenVault::fromEnvironment();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,$vault->seal('fake',$tenant,$location)]);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $calls=[];$status=200;
 $gateway=new GhlGateway(function()use(&$calls,&$status,$location){return new GhlClient('fake',function($path,$version,$method,$body)use(&$calls,&$status,$location){
  $calls[]=[$method,$path,$body];
  if($status!==200)return ['status'=>$status,'body'=>json_encode(['message'=>'not allowed'])];
  if(str_starts_with($path,'/workflows/')){
   $wf=[['id'=>'wf-bienvenida','name'=>'Ágora · Bienvenida WhatsApp','status'=>'published'],['id'=>'wf-cita','name'=>'Ágora · Recordatorio de cita','status'=>'published'],['id'=>'wf-borrador','name'=>'Promoción','status'=>'draft']];
   for($i=1;$i<=50;$i++)$wf[]=['id'=>sprintf('wf-r%03d',$i),'name'=>sprintf('Seguimiento automático %03d',$i),'status'=>'published'];
   return ['status'=>200,'body'=>json_encode(['workflows'=>$wf])];
  }
  if(preg_match('~^/contacts/[\w-]+/workflow/(wf-bienvenida|wf-cita)$~',$path))return ['status'=>200,'body'=>''];
  return ['status'=>404,'body'=>json_encode(['message'=>'Workflow not found'])];
 });});
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,rate_window_start=0,rate_window_count=0,failures=0 WHERE connection_id=?',[$id]);
 $flows=new WhatsappFlows($gateway);
 $all=$flows->account($connection);
 ok(count($all)===53&&$all[0]['estado']==='publicado'&&end($all)['estado']==='borrador','the account\'s workflows are listed, drafts last');
 ok(str_contains($calls[0][1],'/workflows/?locationId='.$location),'read from the workflows endpoint of this location');
 $free();ok(array_column($flows->account($connection,'RECORDATORIO'),'id')===['wf-cita'],'and can be searched by name, ignoring case and accents');
 $free();ok(reason(fn()=>$flows->save($connection,[['id'=>'wf-borrador']]))==='draft','a draft cannot be chosen');
 $free();ok(reason(fn()=>$flows->save($connection,[['id'=>'wf-nope']]))==='unknown','nor a workflow that is not in the account');
 $free();ok(reason(fn()=>$flows->save($connection,array_map(fn($i)=>['id'=>sprintf('wf-r%03d',$i)],range(1,41))))==='too_many','at most forty per account');
 $free();$saved=$flows->save($connection,[['id'=>'wf-bienvenida','nombre'=>'Bienvenida','texto'=>'¡Hola! Gracias por escribirnos.'],['id'=>'wf-cita','nombre'=>'<b>Cita</b>'],['id'=>'wf-bienvenida']]);
 ok(array_column($saved,'nombre')===['Bienvenida','Cita']&&$saved[1]['nombreCuenta']==='Ágora · Recordatorio de cita','several are chosen, with their own visible name, without HTML or repeats');
 $inbox=WhatsappFlows::forInbox($id);
 ok(array_column($inbox,'id')===['flujo:wf-bienvenida','flujo:wf-cita']&&$inbox[0]['preview']&&!$inbox[1]['preview']&&$inbox[0]['flow'],'the inbox offers them as templates, with a preview when there is text');
 $conv=['id'=>'conv-'.$suffix,'contactId'=>'contact-'.$suffix,'locationId'=>$location];
 $before=count($calls);$free();$r=$flows->send($connection,$conv,'wf-bienvenida',str_repeat('a',32),null);
 ok($r['state']==='requested'&&$r['flow']&&$r['label']==='Bienvenida','sending asks the account to start the workflow');
 [$m,$p,$b]=$calls[$before];ok($m==='POST'&&$p==='/contacts/contact-'.$suffix.'/workflow/wf-bienvenida'&&isset($b['eventStartTime']),'for the contact of this conversation, in the chosen workflow');
 $free();$flows->send($connection,$conv,'wf-bienvenida',str_repeat('a',32),null);ok(count($calls)===$before+1,'the same request repeated (double tap) is not sent twice');
 $free();ok(reason(fn()=>$flows->send($connection,$conv,'wf-bienvenida',str_repeat('b',32),null))==='repeat','the same template to the same contact waits ten minutes');
 $free();ok(reason(fn()=>$flows->send($connection,$conv,'wf-r001',str_repeat('c',32),null))==='template_unknown','a workflow the owner did not choose cannot be used');
 $free();$flows->send($connection,$conv,'wf-cita',str_repeat('d',32),null);
 $t=WhatsappFlows::track($id,$conv['id'],[['id'=>'m-old','direction'=>'outbound','dateAdded'=>gmdate('c',time()-3600)],['id'=>'m-new','direction'=>'outbound','dateAdded'=>gmdate('c',time()+1)]]);
 ok(array_column($t['vistas'],'label')===['Bienvenida']&&array_column($t['pendientes'],'label')===['Cita'],'tracking: the first one shows up in the conversation, the second is still pending');
 ok(WhatsappFlows::summary($id,time()-86400,time()+60)===['pedidas'=>2,'vistas'=>1],'the owner sees how many were requested and how many appeared');
 $status=401;$free();ok(reason(fn()=>$flows->account($connection))==='upstream_401','without the workflows permission, a clear refusal');$status=200;
 query("UPDATE whatsapp_flows SET account_status='missing' WHERE connection_id=? AND workflow_id='wf-cita'",[$id]);
 ok(array_column(WhatsappFlows::forInbox($id),'id')===['flujo:wf-bienvenida'],'a workflow missing from the account is no longer offered');
 $cs=query("SELECT CCSA.character_set_name FROM information_schema.`TABLES` T JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY CCSA ON CCSA.collation_name=T.table_collation WHERE T.table_schema=DATABASE() AND T.table_name IN ('whatsapp_flows','whatsapp_flow_sends')")->fetchAll(PDO::FETCH_COLUMN);
 ok($cs===['utf8mb4','utf8mb4'],'the new tables are utf8mb4');
} finally {
 if($tenant){if(isset($id)){query('DELETE FROM whatsapp_flows WHERE connection_id=?',[$id]);query('DELETE FROM whatsapp_flow_sends WHERE connection_id=?',[$id]);}query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
}
echo "PASS $checks whatsapp flow checks (simulated account, real MariaDB)\n";
