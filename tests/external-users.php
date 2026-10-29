<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ExternalUserDirectory.php';
$tenant=null;$calls=0;$suffix=bin2hex(random_bytes(6));
function directoryCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Directory test','directory-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;$vault=TokenVault::fromEnvironment();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Directory fixture',$location,$vault->seal('fake',$tenant,$location)]);$id=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $response=['users'=>[['id'=>'user-1','name'=>'Ana Vendedora','email'=>'ANA@example.test','roles'=>['type'=>'account','role'=>'user','locationIds'=>[$location]],'permissions'=>['conversationsEnabled'=>true,'assignedDataOnly'=>true,'settingsEnabled'=>false],'scopes'=>['conversations.readonly','conversations/message.readonly','bad scope value']]]];
 $gateway=new GhlGateway(function($row)use(&$response,&$calls,$location){return new GhlClient('fake',function($path,$version)use(&$response,&$calls,$location){$calls++;directoryCheck($path==='/users/?locationId='.$location&&$version==='2021-07-28','location user endpoint and version');return ['status'=>200,'body'=>json_encode($response)];});});
 $directory=new ExternalUserDirectory($gateway);$result=$directory->sync($connection);
 $stored=query('SELECT * FROM external_users WHERE connection_id=?',[$id])->fetch();
 directoryCheck($result['count']===1&&$stored['email']==='ana@example.test'&&(int)$stored['assigned_data_only']===1,'user and assigned-only permission imported');
 directoryCheck((int)query('SELECT users_next_sync_at>UNIX_TIMESTAMP() AND users_last_success_at IS NOT NULL FROM ghl_sync_state WHERE connection_id=?',[$id])->fetchColumn()===1,'successful team sync schedules the next permission refresh');
 directoryCheck(json_decode($stored['scopes'],true)===['conversations.readonly','conversations/message.readonly'],'invalid scope discarded');
 query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$id]);$response=['users'=>[]];$directory->sync($connection);
 directoryCheck((int)query('SELECT active FROM external_users WHERE connection_id=?',[$id])->fetchColumn()===0,'missing upstream user becomes inactive without deletion');
 query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$id]);$response=['users'=>[['id'=>'../invalid']]];$failed=false;
 try{$directory->sync($connection);}catch(RuntimeException $e){$failed=true;}
 directoryCheck($failed&&(int)query('SELECT active FROM external_users WHERE connection_id=?',[$id])->fetchColumn()===0,'invalid response cannot corrupt prior projection');
}finally{if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}}
