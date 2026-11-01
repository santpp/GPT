<?php
declare(strict_types=1);
// Asignar una conversación: el responsable del contacto y el de sus oportunidades
// abiertas, sólo por administradores de la subcuenta. La cuenta se simula.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ConversationAssignment.php';
require __DIR__.'/../src/Actor.php';
$tenants=[];$suffix=bin2hex(random_bytes(5));$checks=0;
function asg(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function trouble(Closure $f):?string{try{$f();return null;}catch(ActionProblem $e){return $e->reason;}}
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Assign test','asg-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'x']);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 foreach([['boss','Jefa','admin','account'],['agency','Agencia','admin','agency'],['ana','Ana Ventas','user','account'],['luis','Luis Ventas','user','account']] as [$ext,$name,$role,$type])
  query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,role_type,role_name,permissions,scopes,assigned_data_only,active) VALUES(?,?,?,?,?,?,?,?,?,TRUE)',[$id,$ext,$ext.'-'.$suffix.'@example.test',$name,$type,$role,'{}','[]',0]);
 // quién puede asignar
 foreach([['boss',true],['agency',true],['ana',false]] as [$ext,$expected]) {
  query('INSERT INTO external_identities(tenant_id,email) VALUES(?,?)',[$tenant,$ext.'-'.$suffix.'@example.test']);$identity=(int)db()->lastInsertId();
  $rowId=(int)query('SELECT id FROM external_users WHERE connection_id=? AND external_user_id=?',[$id,$ext])->fetchColumn();
  query('INSERT INTO external_identity_memberships(identity_id,external_user_row_id) VALUES(?,?)',[$identity,$rowId]);
  $m=Actor::connectionMembership(['id'=>$identity,'tenant_id'=>$tenant,'identity_type'=>'external','role'=>'team'],$id);
  asg(($m['can_assign']??null)===$expected,"$ext: ".($expected?'puede':'no puede').' asignar');
 }
 asg(Actor::connectionMembership(['id'=>1,'tenant_id'=>$tenant,'identity_type'=>'native','role'=>'administrator','email'=>'x@x'],$id)['can_assign']===true,'el propietario de Ágora puede asignar');

 $calls=[];
 $gateway=new GhlGateway(function()use(&$calls,$location){return new GhlClient('fake',function($path,$version,$method,$body)use(&$calls,$location){
  $calls[]=[$method,$path,$body];
  if($method==='PUT'&&str_starts_with($path,'/contacts/'))return ['status'=>200,'body'=>json_encode(['contact'=>['id'=>'k1']])];
  if(str_starts_with($path,'/opportunities/search'))return ['status'=>200,'body'=>json_encode(['opportunities'=>[
    ['id'=>'o-open','status'=>'open','assignedTo'=>'luis','locationId'=>$location],['id'=>'o-open2','status'=>'open','locationId'=>$location],
    ['id'=>'o-won','status'=>'won','assignedTo'=>'luis','locationId'=>$location],['id'=>'o-same','status'=>'open','assignedTo'=>'ana','locationId'=>$location]]])];
  if($method==='GET'&&preg_match('~^/opportunities/([A-Za-z0-9_-]+)$~',$path,$m))return ['status'=>200,'body'=>json_encode(['opportunity'=>['id'=>$m[1],'name'=>'Op','locationId'=>$location,'contactId'=>'k1','status'=>'open','assignedTo'=>$m[1]==='o-open'?'luis':null]])];
  if($method==='PUT'&&str_starts_with($path,'/opportunities/'))return ['status'=>200,'body'=>json_encode(['opportunity'=>['id'=>substr($path,strrpos($path,'/')+1)]])];
  throw new GhlApiException(404);
 });});
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $conversation=['id'=>'conv-1','contactId'=>'k1','locationId'=>$location,'assignedTo'=>'luis'];
 query('INSERT INTO conversation_index(connection_id,conversation_id,payload,unread_count,assigned_to) VALUES(?,?,?,?,?)',[$id,'conv-1',json_encode($conversation),0,'luis']);
 $admin=['external_user_id'=>'boss','can_assign'=>true,'capabilities'=>['viewConversations'=>true,'editContacts'=>false,'editOpportunities'=>false,'assignedDataOnly'=>false]];
 $seller=['external_user_id'=>'ana','can_assign'=>false,'capabilities'=>['viewConversations'=>true,'editContacts'=>true,'editOpportunities'=>true,'assignedDataOnly'=>false]];
 $service=new ConversationAssignment($gateway);

 $free();asg(trouble(fn()=>$service->assign($connection,$conversation,$seller,['assignedTo'=>'ana','requestId'=>str_repeat('a',20)]))==='assign_forbidden'&&$calls===[],'un vendedor no puede asignar, y no se toca nada de la cuenta');
 $free();asg(trouble(fn()=>$service->assign($connection,$conversation,$admin,['assignedTo'=>'ghost','requestId'=>str_repeat('b',20)]))==='unknown_assignee','sólo se asigna a alguien del equipo de esa cuenta');
 asg(trouble(fn()=>$service->assign($connection,$conversation,$admin,['assignedTo'=>'ana','requestId'=>'corta']))==='invalid_request_key','la acción lleva su clave contra repeticiones');

 $free();$calls=[];
 $result=$service->assign($connection,$conversation,$admin,['assignedTo'=>'ana','requestId'=>str_repeat('c',20)]);
 $writes=array_values(array_filter($calls,fn($c)=>$c[0]==='PUT'));
 asg($writes[0][1]==='/contacts/k1'&&($writes[0][2]['assignedTo']??null)==='ana','el contacto pasa a ser de la persona elegida, aunque el administrador no tenga permiso de editar fichas');
 $moved=array_map(fn($w)=>$w[1],array_slice($writes,1));sort($moved);
 asg($moved===['/opportunities/o-open','/opportunities/o-open2'],'y también sus oportunidades abiertas; no la ganada ni la que ya era suya');
 asg(array_values(array_unique(array_map(fn($w)=>$w[2]['assignedTo']??null,array_slice($writes,1))))===['ana'],'todas a la misma persona');
 asg($result['assigned']===true&&$result['assignedName']==='Ana Ventas'&&$result['opportunities']===['updated'=>2,'failed'=>0],'se cuenta cuántas oportunidades se movieron');
 asg(json_decode((string)query('SELECT payload FROM conversation_index WHERE connection_id=? AND conversation_id=?',[$id,'conv-1'])->fetchColumn(),true)['assignedTo']==='ana'&&query('SELECT assigned_to FROM conversation_index WHERE connection_id=? AND conversation_id=?',[$id,'conv-1'])->fetchColumn()==='ana','la lista de conversaciones lo refleja al momento');
 asg((int)query("SELECT COUNT(*) FROM push_queue WHERE connection_id=? AND conversation_id='conv-1' AND kind='assigned' AND target='ana'",[$id])->fetchColumn()===1,'la persona elegida recibe el aviso «te asignaron»');

 $free();$calls=[];
 $service->assign($connection,['assignedTo'=>'ana']+$conversation,$admin,['assignedTo'=>'','requestId'=>str_repeat('d',20)]);
 asg(array_values(array_filter($calls,fn($c)=>$c[0]==='PUT'))[0][2]['assignedTo']==='','dejar sin asignar vacía el responsable');
 asg(!isset(json_decode((string)query('SELECT payload FROM conversation_index WHERE connection_id=? AND conversation_id=?',[$id,'conv-1'])->fetchColumn(),true)['assignedTo']),'y desaparece de la conversación');

 // Las fichas no aceptan un cambio de responsable de quien no es administrador.
 $contacts=new ContactActions($gateway);
 $free();asg(trouble(fn()=>$contacts->contact($connection,'k1',$seller,['fields'=>['assignedTo'=>'luis'],'requestId'=>str_repeat('e',20)]))==='assign_forbidden','en la ficha del contacto, sólo un administrador cambia el responsable');
 $free();$contacts->contact($connection,'k1',$seller,['fields'=>['city'=>'Madrid'],'requestId'=>str_repeat('f',20)]);
 asg(true,'pero el resto de la ficha sigue editándose con normalidad');
 echo "PASS $checks assignment checks (simulated account, real MariaDB)\n";
} finally {foreach($tenants as $t){query('DELETE FROM external_identities WHERE tenant_id=?',[$t]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$t]);query('DELETE FROM tenants WHERE id=?',[$t]);}}
