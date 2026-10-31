<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/InboxSync.php';
$suffix=bin2hex(random_bytes(6));$tenant=null;$checks=0;$calls=0;
function verify(bool $ok,string $label):void{global $checks;if(!$ok)throw new Exception($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function resetState(int $id):void{query('UPDATE ghl_sync_state SET next_sync_at=0,request_not_before=0,requests_today=0,budget_day=?,failures=0,last_error=NULL WHERE connection_id=?',[gmdate('Y-m-d'),$id]);}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Sync test','sync-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;$vault=TokenVault::fromEnvironment();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Sync fixture',$location,$vault->seal('fake-test-token',$tenant,$location)]);
 $id=(int)db()->lastInsertId();$connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $response=['status'=>200,'headers'=>['X-RateLimit-Remaining'=>'98'],'body'=>json_encode(['conversations'=>[['id'=>'shared-conversation','locationId'=>$location,'lastMessageBody'=>'Hello','unreadCount'=>2]],'total'=>22])];
 $gateway=new GhlGateway(function($row)use(&$response,&$calls){return new GhlClient('fake',function($path,$version)use(&$response,&$calls){$calls++;verify(str_starts_with($path,'/conversations/search?')&&str_contains($path,'limit=20')&&$version==='2021-04-15','published search route, bounded limit and version');return $response;});});
 $sync=new InboxSync($gateway);
 $result=$sync->sync($connection);verify($result['count']===1 && $result['partial'] && $result['total']===22,'partial recent inbox imported honestly');
 $snapshot=query('SELECT payload FROM ghl_inbox_snapshots WHERE connection_id=?',[$id])->fetchColumn();
 $prior=$calls;$cooldown=false;try{$sync->sync($connection);}catch(GhlCooldown $e){$cooldown=true;}
 verify($cooldown&&$calls===$prior,'repeat refresh does not make another upstream call');
 resetState($id);$response['body']=json_encode(['conversations'=>[['id'=>'foreign','locationId'=>'wrong-location']],'total'=>1]);$bad=false;
 try{$sync->sync($connection);}catch(RuntimeException $e){$bad=true;}
 verify($bad&&query('SELECT payload FROM ghl_inbox_snapshots WHERE connection_id=?',[$id])->fetchColumn()===$snapshot,'foreign location rejected without corrupting prior snapshot');
 resetState($id);$response=['status'=>429,'headers'=>['Retry-After'=>'180'],'body'=>'{}'];$limited=false;
 try{$sync->sync($connection);}catch(GhlApiException $e){$limited=$e->status===429;}
 verify($limited&&(int)query('SELECT request_not_before FROM ghl_sync_state WHERE connection_id=?',[$id])->fetchColumn()>=time()+175,'429 Retry-After persists shared cooldown');
 $prior=$calls;try{$sync->sync($connection);}catch(GhlCooldown $e){}
 verify($calls===$prior,'cooldown shared across calls and no immediate retry');
 resetState($id);query('UPDATE ghl_sync_state SET requests_today='.GhlGateway::DAILY_BUDGET.' WHERE connection_id=?',[$id]);$prior=$calls;
 try{$sync->sync($connection);}catch(GhlCooldown $e){}
 verify($calls===$prior,'local daily budget prevents upstream calls');
 resetState($id);$response=['status'=>200,'headers'=>['X-RateLimit-Remaining'=>'0','X-RateLimit-Interval-Milliseconds'=>'10000'],'body'=>json_encode(['conversations'=>[],'total'=>0])];
 $sync->sync($connection);verify((int)query('SELECT request_not_before FROM ghl_sync_state WHERE connection_id=?',[$id])->fetchColumn()>=time()+8,'exhausted burst header pauses later reads');
 resetState($id);$response=['status'=>401,'headers'=>[],'body'=>'{"token":"must-not-be-logged"}'];query('UPDATE ghl_sync_state SET enabled=TRUE WHERE connection_id=?',[$id]);
 try{$sync->sync($connection);}catch(GhlApiException $e){}
 $state=query('SELECT * FROM ghl_sync_state WHERE connection_id=?',[$id])->fetch();
 verify(!(bool)$state['enabled']&&$state['last_error']==='authentication_or_scope','revoked credential pauses scheduled synchronization');
 resetState($id);query('UPDATE ghl_connections SET token_ciphertext=? WHERE id=?',[$vault->seal('new-fake-token',$tenant,$location),$id]);$prior=$calls;
 try{$sync->sync($connection);}catch(GhlCooldown $e){}
 verify($calls===$prior,'in-flight stale credential rejected after rotation');
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 resetState($id);$response=['status'=>200,'headers'=>['X-RateLimit-Daily-Remaining'=>'0'],'body'=>json_encode(['conversations'=>[],'total'=>0])];
 $sync->sync($connection);verify((int)query('SELECT request_not_before FROM ghl_sync_state WHERE connection_id=?',[$id])->fetchColumn()>=strtotime('tomorrow UTC'),'exhausted daily header defers until next UTC day');
 verify(!str_contains((string)query('SELECT quota FROM ghl_sync_state WHERE connection_id=?',[$id])->fetchColumn(),'must-not-be-logged'),'only whitelisted numeric quota metadata stored');
 // Requested windows persist for scheduled runs and reject arbitrary sizes.
 foreach([50,100] as $window){
  resetState($id);query('UPDATE ghl_sync_state SET next_sync_at=? WHERE connection_id=?',[time()+120,$id]);
  $wideGateway=new GhlGateway(fn($row)=>new GhlClient('fake',function($path,$version)use($window,$location){
   verify(str_contains($path,'limit='.$window),'requested window reaches API');
   return ['status'=>200,'body'=>json_encode(['conversations'=>array_map(fn($n)=>['id'=>'conv-'.$n,'locationId'=>$location],range(1,$window)),'total'=>150])];
  }));
  $wide=new InboxSync($wideGateway);$wideResult=$wide->sync($connection,$window);
  verify($wideResult['count']===$window&&(int)query('SELECT inbox_limit FROM connection_preferences WHERE connection_id=?',[$id])->fetchColumn()===$window,'requested window stored after successful sync');
  resetState($id);$wide->sync($connection);
 }
 $badLimit=false;try{(new InboxSync())->sync($connection,999);}catch(InvalidArgumentException $e){$badLimit=true;}
 verify($badLimit,'unsupported window rejected before network');
 echo 'PASS '.$checks.' sync checks (mock upstream, real MariaDB)'.PHP_EOL;
}finally{
 if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
}
