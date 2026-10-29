<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ContactProfile.php';
require __DIR__.'/../src/ContactFieldDirectory.php';
$tenant=null;$calls=0;$suffix=bin2hex(random_bytes(6));
function contactCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Contact test','contact-'.$suffix]);$tenant=(int)db()->lastInsertId();$location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Contact fixture',$location,'fake']);$connectionId=(int)db()->lastInsertId();
 query('INSERT INTO external_users(connection_id,external_user_id,display_name,permissions,scopes) VALUES(?,?,?,?,?)',[$connectionId,'seller-1','Ana Responsable','{}','[]']);
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 $response=['contact'=>['id'=>'contact-1','locationId'=>$location,'name'=>'Cliente Uno','email'=>'cliente@example.test','phone'=>'+34123456789','source'=>'Formulario','assignedTo'=>'seller-1','tags'=>['Interesado','VIP'],'lastActivity'=>'2026-10-29T10:00:00Z','customFields'=>[['id'=>'field-1','value'=>'Valor'],['id'=>'field-bad','value'=>['A','B']],['id'=>'../bad','value'=>'discard']]]];
 $gateway=new GhlGateway(function()use(&$response,&$calls){return new GhlClient('fake',function($path,$version)use(&$response,&$calls){$calls++;contactCheck($path==='/contacts/contact-1'&&$version==='2021-07-28','documented contact endpoint and version');return ['status'=>200,'body'=>json_encode($response)];});});
 $service=new ContactProfile($gateway);$profile=$service->get($connection,'contact-1');
 contactCheck($profile['name']==='Cliente Uno'&&$profile['assignedName']==='Ana Responsable'&&$profile['tags']===['Interesado','VIP'],'contact identity, assignment and tags normalized');
 contactCheck(count($profile['customFields'])===2&&$profile['customFields'][1]['value']==='A, B','custom fields bounded and unsafe identifiers discarded');
 contactCheck($service->get($connection,'contact-1')['email']==='cliente@example.test'&&$calls===1,'fresh contact profile served from shared cache');
 query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$connectionId]);$fieldCalls=0;
 $fieldGateway=new GhlGateway(function()use(&$fieldCalls,$location){return new GhlClient('fake',function($path,$version)use(&$fieldCalls,$location){$fieldCalls++;contactCheck($path==='/locations/'.$location.'/customFields'&&$version==='2021-07-28','documented custom-field endpoint and version');return ['status'=>200,'body'=>json_encode(['customFields'=>[['id'=>'field-1','name'=>'Tipo de consulta','locationId'=>$location,'model'=>'contact'],['id'=>'opportunity','name'=>'Valor','locationId'=>$location,'model'=>'opportunity']]])];});});
 $fields=(new ContactFieldDirectory($fieldGateway))->get($connection);contactCheck($fields===[['id'=>'field-1','name'=>'Tipo de consulta']]&&$fieldCalls===1,'contact field names normalized and opportunity fields excluded');
 query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$connectionId]);$response['contact']['locationId']='foreign';$failed=false;
 try{$service->get($connection,'contact-1',true);}catch(RuntimeException $e){$failed=true;}
 contactCheck($failed&&json_decode(query('SELECT payload FROM contact_profile_cache WHERE connection_id=? AND contact_id=?',[$connectionId,'contact-1'])->fetchColumn(),true)['name']==='Cliente Uno','mismatched response cannot replace cached profile');
}finally{if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}}
