<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/WonSales.php';
$n=0;function wk(bool $ok,string $l):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
$s=bin2hex(random_bytes(4));
query("INSERT INTO tenants(name,location_id) VALUES(?,?)",['Won '.$s,'won-'.$s]);$t=(int)db()->lastInsertId();
try{
  query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status,won_alert_days) VALUES(?,?,?,?,'connected',7)",[$t,'C','loc-'.$s,'x']);$c=(int)db()->lastInsertId();
  $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$c])->fetch();
  foreach([['ana','Ana'],['luis','Luis']] as [$u,$name])query("INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,active) VALUES(?,?,?,?,'{}','[]',1)",[$c,$u,$u.'@example.test',$name]);
  $now=strtotime('2026-11-20 12:00:00 UTC');$old=gmdate('c',$now-9*86400);$recent=gmdate('c',$now-86400);
  $calls=0;$list=[
    ['id'=>'o1','name'=>'Curso A','locationId'=>'loc-'.$s,'monetaryValue'=>0,'assignedTo'=>'ana','lastStatusChangeAt'=>$old,'contact'=>['name'=>'Pedro']],
    ['id'=>'o2','name'=>'Curso B','locationId'=>'loc-'.$s,'monetaryValue'=>500,'assignedTo'=>'ana','lastStatusChangeAt'=>$recent,'contact'=>['name'=>'María']],
    ['id'=>'o3','name'=>'Curso C','locationId'=>'loc-'.$s,'assignedTo'=>'luis','lastStatusChangeAt'=>$recent,'contact'=>['name'=>'Raúl']],
    ['id'=>'o4','name'=>'Curso D','locationId'=>'loc-'.$s,'monetaryValue'=>0,'lastStatusChangeAt'=>$recent,'contact'=>['name'=>'Sin dueño']],
    ['id'=>'oa','name'=>'Ganada en octubre','locationId'=>'loc-'.$s,'monetaryValue'=>0,'assignedTo'=>'ana','lastStatusChangeAt'=>'2026-10-28T10:00:00Z'],
    ['id'=>'ox','name'=>'Otra cuenta','locationId'=>'otra','monetaryValue'=>9,'assignedTo'=>'ana'],
  ];
  $gw=new GhlGateway(function()use(&$calls,&$list){return new GhlClient('fake',function($path)use(&$calls,&$list){$calls++;return ['status'=>200,'body'=>json_encode(['opportunities'=>$list,'meta'=>['total'=>count($list)]])];});});
  $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$c]);
  $won=new WonSales($gw);$period=gmdate('Y-m',$now);
  $ana=['self_id'=>'ana','can_assign'=>false,'capabilities'=>['viewOpportunities'=>true,'editOpportunities'=>true]];
  $admin=['self_id'=>null,'can_assign'=>true,'capabilities'=>['viewOpportunities'=>true,'editOpportunities'=>true]];
  $free();$a=$won->alert($connection,$ana,$now);
  wk($a['count']===1&&$a['items'][0]['id']==='o1'&&$a['items'][0]['days']===9,'a seller is alerted about their own won sales with no amount, with how many days they have been like that');
  wk($a['escalate']===true&&$a['days']===7&&$a['oldestDays']===9,'and it escalates when one has waited the days set for the sub-account');
  wk($calls===1,'the account is read once');
  wk(!in_array('oa',array_column($a['items'],'id'),true),'a sale the account returns but that changed to won in another month is left out');
  $b=$won->alert($connection,$admin,$now);
  wk($calls===1,'a second screen within the cache time does not ask the account again');
  wk($b['count']===1&&$b['items'][0]['id']==='o4'&&$b['team']['missing']===3,'an administrator is alerted about the ones with nobody assigned and sees the team total');
  wk(array_column($b['team']['bySeller'],'missing','name')===['Ana'=>1,'Luis'=>1,'Sin responsable'=>1],'with the breakdown by seller');
  $l=$won->listing($connection,$period,$ana,null,$now);
  wk($l['totals']['count']===2&&$l['totals']['sum']===500.0&&$l['totals']['missing']===1&&$l['items'][0]['missing']===true&&$l['bySeller']===[],'a seller\'s list has only their sales, those without an amount first, and no team breakdown');
  $l=$won->listing($connection,$period,$admin,null,$now);
  wk($l['totals']['count']===4&&$l['totals']['sum']===500.0&&count($l['bySeller'])===3,'an administrator sees everything, grouped by seller, and nothing from another account');
  $l=$won->listing($connection,$period,$admin,'luis',$now);
  wk($l['totals']['count']===1&&$l['items'][0]['id']==='o3'&&$l['items'][0]['missing']===true,'a missing "monetaryValue" counts as no amount, and the seller filter works');
  query("DELETE FROM directory_cache WHERE connection_id=? AND cache_key LIKE 'won:%'",[$c]);$free();$list[0]['monetaryValue']=1200;
  wk($won->alert($connection,$ana,$now)['count']===0&&$calls===2,'after the cache is cleared (an edit), the next read sees the amount');
  query('UPDATE ghl_connections SET won_alert_days=0 WHERE id=?',[$c]);$connection['won_alert_days']=0;$list[0]['monetaryValue']=0;
  query("DELETE FROM directory_cache WHERE connection_id=? AND cache_key LIKE 'won:%'",[$c]);$free();
  $z=$won->alert($connection,$ana,$now);
  wk($z['count']===1&&$z['escalate']===false,'with 0 days it alerts but never blocks');
  query('INSERT INTO won_sales(connection_id,opportunity_id,assigned_to,value,won_at) VALUES(?,?,?,?,?)',[$c,'local1','ana',0,gmdate('Y-m-d H:i:s',$now)]);
  query("DELETE FROM directory_cache WHERE connection_id=? AND cache_key LIKE 'won:%'",[$c]);$free();
  wk($won->alert($connection,$ana,$now)['count']===2,'a sale won from Ágora that the account did not return is counted too');
}finally{
  query('DELETE FROM won_sales WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$t]);
  query('DELETE FROM directory_cache WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$t]);
  query('DELETE FROM external_users WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$t]);
  query('DELETE FROM ghl_connections WHERE tenant_id=?',[$t]);query('DELETE FROM tenants WHERE id=?',[$t]);
}
echo "PASS $n won sales checks\n";
