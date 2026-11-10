<?php
declare(strict_types=1);
// «Entrar como»: sólo la propietaria, sólo para mirar, sólo lo de esa persona y queda anotado.
// Habla con el servidor de desarrollo (localhost:8080) y su base de datos.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ViewAs.php';
require __DIR__.'/../src/Actor.php';
$suffix=bin2hex(random_bytes(5));$tenants=[];$checks=0;
function va(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function call(string $jar,string $path,string $method='GET',?array $body=null,string $csrf=''):array{
 $ch=curl_init('http://localhost:8080/api/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,
  CURLOPT_HTTPHEADER=>['Origin: http://localhost:8080','Content-Type: application/json','X-CSRF-Token: '.$csrf]]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
 return [$code,json_decode($raw?:'{}',true)??[]];
}
try {
 $mk=function(string $name)use($suffix,&$tenants):array{
  query('INSERT INTO tenants(name,location_id) VALUES(?,?)',[$name,'va-'.$name.'-'.$suffix]);$tenant=(int)db()->lastInsertId();$tenants[]=$tenant;
  query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Sub '.$name,'loc-'.$name.'-'.$suffix,'x']);
  return [$tenant,(int)db()->lastInsertId()];
 };
 [$tenant,$conn]=$mk('a');[$otherTenant,$otherConn]=$mk('b');
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Sub a2','loc-a2-'.$suffix,'x']);$conn2=(int)db()->lastInsertId();
 $user=function(int $c,string $ext,string $role)use($suffix):int{
  query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,role_type,role_name,permissions,scopes,assigned_data_only,active) VALUES(?,?,?,?,?,?,?,?,?,TRUE)',
   [$c,$ext,$ext.'-'.$suffix.'@example.test',ucfirst($ext),'account',$role,'{}','[]',$role!=='admin'?1:0]);
  return (int)db()->lastInsertId();
 };
 $ana=$user($conn,'ana','user');$jefa=$user($conn,'jefa','admin');$foreign=$user($otherConn,'eva','user');
 $pw='Clave-'.$suffix;
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,'owner-'.$suffix.'@example.test',password_hash($pw,PASSWORD_DEFAULT)]);$owner=(int)db()->lastInsertId();
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'seller')",[$tenant,'seller-'.$suffix.'@example.test',password_hash($pw,PASSWORD_DEFAULT)]);

 // Sin pantalla: la persona vista tiene exactamente los permisos de su usuario.
 $actor=ViewAs::actor(['id'=>$owner,'tenant_id'=>$tenant],['row'=>$ana]);
 va($actor!==null&&$actor['identity_type']==='external'&&$actor['capabilities']['assignedDataOnly']===true,'viewing as a seller keeps the «only what is assigned» rule');
 $m=Actor::connectionMembership($actor,$conn);
 va(($m['external_user_id']??null)==='ana'&&($m['can_assign']??null)===false,'inside the sub-account they are that seller, who cannot assign');
 va(Actor::connectionMembership($actor,$conn2)===null,'and they see no other sub-account of the organisation');
 va(ViewAs::actor(['id'=>$owner,'tenant_id'=>$tenant],['row'=>$foreign])===null,'a user of another organisation can never be viewed');
 $boss=ViewAs::actor(['id'=>$owner,'tenant_id'=>$tenant],['row'=>$jefa]);
 va($boss['capabilities']['assignedDataOnly']===false&&Actor::connectionMembership($boss,$conn)['can_assign']===true,'viewing as a sub-account admin shows everything that admin sees');

 // Con el servidor.
 $jar=tempnam(sys_get_temp_dir(),'va-');
 [$code,$login]=call($jar,'login','POST',['email'=>'owner-'.$suffix.'@example.test','password'=>$pw]);
 va($code===200,'the owner signs in');$csrf=$login['csrf'];
 [$code,$list]=call($jar,'admin/view-as');
 $listed=array_merge(...array_map(fn($g)=>array_column($g['users'],'id'),$list['connections']??[]));
 va($code===200&&in_array($ana,$listed,true)&&in_array($jefa,$listed,true)&&!in_array($foreign,$listed,true),'she can choose among her own sub-accounts\' users only');
 va(call($jar,'admin/view-as','POST',['userId'=>$foreign],$csrf)[0]===404,'asking for another organisation\'s user is refused');
 va(call($jar,'admin/view-as','POST',['userId'=>$ana])[0]===403,'starting it needs the session token');
 [$code,$started]=call($jar,'admin/view-as','POST',['userId'=>$ana],$csrf);
 va($code===200&&($started['viewAs']['name']??null)==='Ana','she starts viewing as Ana');
 [,$me]=call($jar,'me');
 va(($me['viewAs']['name']??null)==='Ana'&&($me['user']['identity_type']??null)==='external'&&($me['user']['capabilities']['assignedDataOnly']??null)===true,'the app now loads as Ana, with Ana\'s permissions');
 [$code,$cons]=call($jar,'workspace/connections');
 va($code===200&&array_column($cons['items'],'id')===[$conn],'only Ana\'s sub-account is listed');
 va(call($jar,'workspace/dashboard?connectionId='.$conn2)[0]===403,'another sub-account answers as it would to Ana: no access');
 [$code,$ro]=call($jar,'workspace/read?connectionId='.$conn.'&conversationId=c1','POST',[],$csrf);
 va($code===403&&($ro['code']??null)==='view_as_readonly','nothing can be written in Ana\'s name (not even marking as read)');
 va(call($jar,'workspace/send?connectionId='.$conn.'&conversationId=c1','POST',['message'=>'x'],$csrf)[0]===403,'and no message can be sent');
 va(call($jar,'admin/consumption')[0]===403,'the owner-only screens are closed while viewing');
 va((int)query('SELECT COUNT(*) FROM view_as_log WHERE user_id=? AND external_user_id=? AND ended_at IS NULL',[$owner,'ana'])->fetchColumn()===1,'the start is recorded');
 [$code]=call($jar,'view-as/stop','POST',[],$csrf);
 [,$back]=call($jar,'me');
 va($code===200&&$back['viewAs']===null&&($back['user']['identity_type']??null)==='native','«Volver a mi vista» returns to the owner');
 va((int)query('SELECT COUNT(*) FROM view_as_log WHERE user_id=? AND ended_at IS NOT NULL',[$owner])->fetchColumn()===1,'and the end is recorded too');
 // Nadie más puede usarlo.
 $jar2=tempnam(sys_get_temp_dir(),'va-');
 [,$sl]=call($jar2,'login','POST',['email'=>'seller-'.$suffix.'@example.test','password'=>$pw]);
 va(call($jar2,'admin/view-as')[0]===403&&call($jar2,'admin/view-as','POST',['userId'=>$ana],$sl['csrf']??'')[0]===403,'a non-owner cannot list or start it');
 [,$sme]=call($jar2,'me');
 va(($sme['canViewAs']??null)===false,'and is not offered it');
 @unlink($jar);@unlink($jar2);
 echo "view-as: $checks checks\n";
} finally {
 foreach($tenants as $t){
  query('DELETE FROM view_as_log WHERE tenant_id=?',[$t]);
  query('DELETE eu FROM external_users eu JOIN ghl_connections c ON c.id=eu.connection_id WHERE c.tenant_id=?',[$t]);
  query('DELETE FROM device_sessions WHERE tenant_id=?',[$t]);
  query('DELETE FROM users WHERE tenant_id=?',[$t]);
  query('DELETE FROM ghl_sync_state WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$t]);
  query('DELETE FROM ghl_connections WHERE tenant_id=?',[$t]);
  query('DELETE FROM tenants WHERE id=?',[$t]);
 }
}
