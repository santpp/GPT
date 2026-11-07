<?php
declare(strict_types=1);
// «Consumos»: el token de agencia (nunca vuelve), POST y GET, paginación, suma por subcuenta y lo que sólo ve la propietaria.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/AgencyWallet.php';
$n=0;function ck(bool $v,string $l):void{global $n;if(!$v){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
$suffix=bin2hex(random_bytes(5));$tenant=null;$cookie=tempnam(sys_get_temp_dir(),'agora-cons-');$csrf='';
function hc(string $path,string $method='GET',?array $body=null):array{global $cookie,$csrf;
 $ch=curl_init('http://localhost:8080/api/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Origin: http://localhost:8080','X-CSRF-Token: '.$csrf]]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return [$code,(string)$raw];}
$SECRET='pit-'.bin2hex(random_bytes(16));
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Cons '.$suffix,'cons-'.$suffix]);$tenant=(int)db()->lastInsertId();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Clínica Sol','locA-'.$suffix,'x']);
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Academia Luna','locB-'.$suffix,'x']);
 $email='o-'.$suffix.'@example.test';$pw=bin2hex(random_bytes(10));
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,$email,password_hash($pw,PASSWORD_DEFAULT)]);
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'seller')",[$tenant,'s-'.$suffix.'@example.test',password_hash($pw,PASSWORD_DEFAULT)]);

 // ---- el token y el Company ID
 $w=new AgencyWallet($tenant);
 ck($w->settings()==['companyId'=>'','tokenSaved'=>false,'tokenHint'=>'','updatedAt'=>null],'nothing is configured at the start');
 $s=$w->save('COMP123456',$SECRET);
 ck($s['tokenSaved']&&$s['tokenHint']===substr($SECRET,-4)&&$s['companyId']==='COMP123456'&&!str_contains(json_encode($s),$SECRET),'saving keeps the company and only the last four characters of the token');
 ck(!str_contains((string)query('SELECT GROUP_CONCAT(value) FROM app_state WHERE name LIKE ?',['agency:%:'.$tenant])->fetchColumn(),$SECRET)&&(int)query('SELECT COUNT(*) FROM app_state WHERE name=?',['agency:token:'.$tenant])->fetchColumn()===1,'and the token is not stored in the clear');
 $w->save('COMP123456',null);ck($w->settings()['tokenSaved'],'saving only the company id keeps the token');
 foreach([['x y z',null],['ab',null],['COMP123456','corto'],['COMP123456',"con espacios aqui 0123456789"]] as [$c,$t]){$bad=false;try{$w->save($c,$t);}catch(InvalidArgumentException $e){$bad=true;}ck($bad,'an invalid company id or token is refused ('.json_encode([$c,$t],JSON_UNESCAPED_UNICODE).')');}

 // ---- POST (la forma que contesta la cuenta), con una cuenta de mentira
 $log=[];$mode='ok';$page1=[];$status=200;
 for($i=0;$i<1000;$i++)$page1[]=['locationId'=>'locA-'.$suffix,'amount'=>-0.01,'chargeType'=>'SMS','settlementTime'=>'2026-11-02T10:00:00Z'];
 $fake=function(string $m,string $url,array $h,?string $body)use(&$log,&$mode,&$page1,&$status,$suffix){
   $log[]=compact('m','url','h','body');
   if($mode==='deny')return ['status'=>403,'headers'=>[],'body'=>'{"message":"forbidden '.$GLOBALS['SECRET'].'"}'];
   if(str_contains($url,'wallet-transactions')){
     $skip=json_decode((string)$body,true)['skip']??0;
     if($skip===0)return ['status'=>$status,'headers'=>['X-RateLimit-Remaining'=>'99','Set-Cookie'=>'x'],'body'=>json_encode(['transactions'=>$page1])];
     return ['status'=>$status,'headers'=>[],'body'=>json_encode(['transactions'=>[
       ['locationId'=>'locA-'.$suffix,'amount'=>-2.5,'chargeType'=>'WhatsApp conversation','settlementTime'=>'2026-11-03T10:00:00Z'],
       ['locationId'=>'locA-'.$suffix,'amount'=>50,'type'=>'Wallet recharge','settlementTime'=>'2026-11-04T10:00:00Z'],
       ['locationId'=>'locB-'.$suffix,'amount'=>-4,'description'=>'Conversation AI usage','settlementTime'=>'2026-11-05T10:00:00Z'],
       ['locationId'=>'loc-otra','amount'=>-9,'chargeType'=>'Email','settlementTime'=>'2026-11-05T10:00:00Z'],
       ['amount'=>-1,'chargeType'=>'domainPurchase'],['locationId'=>'locB-'.$suffix,'chargeType'=>'raro']]])];
   }
   if(str_contains($url,'get-saas-subscription'))return ['status'=>201,'headers'=>[],'body'=>json_encode(['subscriptionStatus'=>'active','saasPlanId'=>'plan1'])];
   if(str_contains($url,'saas-plan'))return ['status'=>201,'headers'=>[],'body'=>json_encode(['title'=>'Plan Pro','prices'=>[['amount'=>97,'currency'=>'USD','recurringInterval'=>'month']]])];
   return ['status'=>404,'headers'=>[],'body'=>'{}'];
 };
 $w=new AgencyWallet($tenant,$fake);
 $r=$w->transactions('2026-11','locA-'.$suffix,0,50);$body=json_decode($log[0]['body'],true);
 ck($log[0]['m']==='POST'&&str_ends_with($log[0]['url'],'/wallet-transactions')&&in_array('Version: v3',$log[0]['h'],true)&&$body['filters']['settlementTime']['from']==='2026-11-01T00:00:00.000Z'&&$body['filters']['settlementTime']['to']==='2026-11-30T23:59:59.999Z'&&$body['filters']['locationId']==='locA-'.$suffix&&$body['limit']===50&&$body['timezone']==='UTC','POST sends the month and the sub-account as a JSON body, with Version v3');
 ck(in_array('Authorization: Bearer '.$SECRET,$log[0]['h'],true)&&!str_contains($r['url'],$SECRET)&&str_contains($r['url'],'{companyId}')&&!isset($r['headers']['set-cookie']),'the token goes in the header, and nothing shown keeps it, the company id or cookies');
 // paginación y suma
 $log=[];$all=$w->monthRows('2026-11',null);
 ck($all['pages']===2&&count($all['rows'])===1006&&count($log)===2&&$log[1]['body']&&json_decode($log[1]['body'],true)['skip']===1000,'a month is read in pages of 1000 until a short page arrives');
 $status=201;$log=[];$created=$w->monthRows('2026-11',null);ck($created['error']===null&&count($created['rows'])===1006,'a 201 answer counts as success (the account answers 200 or 201 depending on the endpoint)');$status=200;
 $sum=array_column(AgencyWallet::summarize($all['rows']),null,'locationId');
 ck(abs($sum['locA-'.$suffix]['gasto']-12.5)<1e-9&&$sum['locA-'.$suffix]['recargas']===50.0&&$sum['locA-'.$suffix]['movimientos']===1002,'spending and recharges are told apart and summed per sub-account');
 $cats=array_column($sum['locA-'.$suffix]['categorias'],'monto','tipo');ck($cats['sms']===10.0&&$cats['whatsapp conversation']===2.5,'with a breakdown by type, largest first');
 ck(count($sum['locA-'.$suffix]['ultimos'])===50&&count(AgencyWallet::summarize($all['rows'],1000)[0]['ultimos'])===1000,'the movement list is the latest 50, or up to 1000 for one sub-account');
 ck($sum['locB-'.$suffix]['gasto']===4.0&&$sum['locB-'.$suffix]['sinClasificar']===1,'a movement it cannot read is counted apart instead of guessed');
 ck(isset($sum['agencia'])&&$sum['agencia']['gasto']===1.0,'and one with no sub-account goes to the agency itself');
 $mode='deny';$f=$w->monthRows('2026-11',null);$GLOBALS['SECRET']=$SECRET;$f=$w->monthRows('2026-11',null);
 ck($f['error']['status']===403&&!str_contains(json_encode($f),$SECRET),'a refusal is reported with its status, with the token blacked out of the message');
 $mode='ok';
 $p=$w->planOf('locA-'.$suffix);ck($p['plan']==='Plan Pro'&&$p['estado']==='active'&&$p['precio']['monto']===97&&$p['errores']===[]&&!array_key_exists('saldo',$p),'the active plan and its price, read from 201 answers');
 ck(str_contains($w->redact('hola '.$SECRET.' fin'),'••••'.substr($SECRET,-4))&&!str_contains($w->redact('hola '.$SECRET),$SECRET),'redact blacks out the token anywhere');
 $rows=AgencyWallet::rows(['data'=>['items'=>[['a'=>1]]]]);ck(count($rows)===1,'rows are found whatever box the response puts them in');

 // ---- por HTTP: sólo la propietaria, y el token no vuelve
 ck(hc('admin/consumption')[0]===401,'the tab needs a session');
 $seller='s-'.$suffix.'@example.test';
 [$c,$raw]=hc('login','POST',['email'=>$seller,'password'=>$pw]);$csrf=json_decode($raw,true)['csrf']??'';
 ck(hc('admin/consumption')[0]===403&&hc('admin/consumption/settings','POST',['companyId'=>'COMP123456'])[0]===403,'someone who is not the owner cannot open or change it');
 hc('logout','POST',[]);
 [$c,$raw]=hc('login','POST',['email'=>$email,'password'=>$pw]);$csrf=json_decode($raw,true)['csrf']??'';ck($c===200,'the owner signs in');
 [$c,$raw]=hc('admin/consumption/settings','POST',['companyId'=>'COMPNEW9999','token'=>$SECRET.'zz']);
 $d=json_decode($raw,true);ck($c===200&&$d['tokenSaved']&&$d['tokenHint']==='9zz'||$d['tokenHint']===substr($SECRET.'zz',-4),'the owner saves the company and token');
 ck(!str_contains($raw,$SECRET),'the answer never contains the token');
 [,$raw]=hc('admin/consumption');ck(!str_contains($raw,$SECRET)&&str_contains($raw,'Clínica Sol')&&str_contains($raw,'Academia Luna'),'reading it back shows the connected sub-accounts and no token');
 [$c]=hc('admin/consumption/settings','POST',['companyId'=>'malo malo']);ck($c===422,'a bad company id is a 422');
 ck(hc('admin/consumption/test','POST',['target'=>'transactions'])[0]===404,'the debugging route is gone');
 [$c,$raw]=hc('admin/consumption/report?month=2026-11');ck(in_array($c,[424,200],true)&&!str_contains($raw,$SECRET),'a report on a real (unreachable) account fails politely, without the token');
 [$c]=hc('admin/consumption/report?month=2026-11&locationId=otra-cuenta');ck($c===404||$c===424,'an unknown sub-account is refused');
 [$c,$raw]=hc('admin/consumption/token','DELETE');ck($c===200&&json_decode($raw,true)['tokenSaved']===false,'the token can be removed');
 [$c]=hc('admin/consumption/report?month=2026-11');ck($c===409,'and without it there is nothing to ask');
 echo "$n consumption checks\n";
}finally{
 if($tenant){query('DELETE FROM app_state WHERE name LIKE ?',['agency:%:'.$tenant]);query('DELETE FROM device_sessions WHERE tenant_id=?',[$tenant]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
 foreach(['o-','s-'] as $p)query('DELETE FROM login_attempts WHERE key_hash IN (?,?)',[hash('sha256','127.0.0.1|'.$p.$suffix.'@example.test'),hash('sha256','::1|'.$p.$suffix.'@example.test')]);@unlink($cookie);
}
