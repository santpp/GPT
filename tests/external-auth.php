<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ExternalAuth.php';
require __DIR__.'/../src/Actor.php';

$tenant=null;$captured=[];$suffix=bin2hex(random_bytes(6));
function authCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Auth test','auth-'.$suffix]);$tenant=(int)db()->lastInsertId();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Auth fixture','loc-'.$suffix,'not-used']);$connection=(int)db()->lastInsertId();
 $email='TEAM-'.$suffix.'@example.test';
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',[$connection,'member-'.$suffix,strtolower($email),'Team fixture',json_encode(['conversationsEnabled'=>true,'assignedDataOnly'=>true]),json_encode(['conversations.readonly','conversations/message.write']),1]);
 $externalRow=(int)db()->lastInsertId();
 $auth=new ExternalAuth(function(int $tenantId,string $to,string $code)use(&$captured):void{$captured=[$to,$code,$tenantId];});
 $auth->request($email,'127.0.0.2');
 authCheck($captured[0]===strtolower($email)&&preg_match('/^\d{6}$/D',$captured[1])===1&&$captured[2]===$tenant,'one-time code delivered only to imported email through the account of that team');
 authCheck((int)query('SELECT COUNT(*) FROM external_identity_memberships WHERE external_user_row_id=?',[$externalRow])->fetchColumn()===1,'verified identity prepared from synchronized membership');
 try{$auth->verify($email,'000000','127.0.0.2');$wrong=false;}catch(InvalidArgumentException $e){$wrong=true;}
 authCheck($wrong,'wrong code rejected');
 $identity=$auth->verify($email,$captured[1],'127.0.0.2');
 try{$auth->verify($email,$captured[1],'127.0.0.2');$reused=false;}catch(InvalidArgumentException $e){$reused=true;}
 authCheck($reused,'one-time code cannot be reused');
 $actor=Actor::fromSession(['external_identity_id'=>$identity]);$membership=Actor::connectionMembership($actor,$connection);
 authCheck($actor['identity_type']==='external'&&$actor['role']==='team'&&!$actor['capabilities']['configureConnections'],'imported identity creates no native administrator');
 authCheck($membership['capabilities']['viewConversations']&&$membership['capabilities']['sendMessages']&&$membership['capabilities']['assignedDataOnly'],'synchronized permissions evaluated for the selected account');
 $visible=Actor::filterConversations([['id'=>'mine','assignedTo'=>'member-'.$suffix],['id'=>'other','assignedTo'=>'someone-else'],['id'=>'unassigned']],$membership);
 authCheck(array_column($visible,'id')===['mine'],'assigned-only user sees only assigned conversations');
 query('UPDATE external_users SET active=FALSE WHERE id=?',[$externalRow]);
 authCheck(Actor::fromSession(['external_identity_id'=>$identity])===null,'removed team member loses access on next request');
}finally{
 if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM external_identities WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
 foreach(['request','verify'] as $action)foreach(['127.0.0.2|external-'.$action.'|'.strtolower($email??''),'email|external-'.$action.'|'.strtolower($email??''),'origin|external-'.$action.'|127.0.0.2'] as $scope)query('DELETE FROM login_attempts WHERE key_hash=?',[ExternalAuth::key($scope)]);
}
