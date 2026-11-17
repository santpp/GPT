<?php
declare(strict_types=1);
/* 1.10.19 · Contactos «Creados hoy»: filtro por fecha de alta en la zona del dispositivo; quien solo ve lo suyo, solo los suyos. */
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ContactDirectory.php';
$n=0;$tenant=null;$suffix=bin2hex(random_bytes(6));
function ok(bool $c,string $l):void{global $n;if(!$c)throw new RuntimeException($l);$n++;echo "PASS $l\n";}
try{
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Hoy','hoy-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $id=(int)db()->lastInsertId();$connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $now=gmdate('Y-m-d\TH:i:s\Z');$old=gmdate('Y-m-d\TH:i:s\Z',time()-5*86400);
 $calls=[];$ignore=false;
 $contacts=[['id'=>'h1','contactName'=>'Hoy Ana','assignedTo'=>'seller-1','locationId'=>$location,'dateAdded'=>$now],
            ['id'=>'h2','contactName'=>'Hoy Carlos','assignedTo'=>'seller-2','locationId'=>$location,'dateAdded'=>$now],
            ['id'=>'v1','contactName'=>'Viejo','assignedTo'=>'seller-1','locationId'=>$location,'dateAdded'=>$old]];
 $gw=new GhlGateway(function()use(&$calls,&$ignore,$contacts):GhlClient{return new GhlClient('fake',function($path,$version,$method,$payload)use(&$calls,&$ignore,$contacts){
  $calls[]=[$method,$path,$payload];
  $items=$contacts;
  if(!$ignore)foreach($payload['filters']??[] as $f){
   if($f['field']==='dateAdded')$items=array_filter($items,fn($c)=>$c['dateAdded']>$f['value']['gt']&&$c['dateAdded']<$f['value']['lt']);
   if($f['field']==='assignedTo')$items=array_filter($items,fn($c)=>$c['assignedTo']===$f['value']);
  }
  return ['status'=>200,'body'=>json_encode(['contacts'=>array_values($items),'total'=>count($items)])];
 });});
 $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$id]);
 $admin=['external_user_id'=>null,'capabilities'=>['viewContacts'=>true,'assignedDataOnly'=>false]];
 $seller=['external_user_id'=>'seller-1','capabilities'=>['viewContacts'=>true,'assignedDataOnly'=>true]];
 $d=new ContactDirectory($gw);
 $r=$d->recientes($connection,null,$admin,'hoy','America/Caracas');
 $f=$calls[0][2]['filters'];
 ok($calls[0][0]==='POST'&&$calls[0][1]==='/contacts/search'&&$f[0]['field']==='dateAdded'&&$f[0]['operator']==='range','se pide a la cuenta por fecha de alta');
 ok(array_column($r['items'],'id')===['h1','h2']&&$r['since']==='hoy','quien ve todo recibe todos los creados hoy');
 $free();$calls=[];$r=$d->recientes($connection,null,$seller,'hoy','America/Caracas');
 ok(array_column($r['items'],'id')===['h1']&&($calls[0][2]['filters'][1]['value']??'')==='seller-1'&&$r['restricted'],'un vendedor recibe solo los suyos');
 $free();$ignore=true;$r=$d->recientes($connection,null,$seller,'hoy','America/Caracas');
 ok(array_column($r['items'],'id')===['h1'],'aunque la cuenta ignore el filtro, no se cuelan contactos ajenos ni viejos');
 $r=$d->recientes($connection,null,['external_user_id'=>null,'capabilities'=>['assignedDataOnly'=>true]],'hoy','UTC');
 ok($r['items']===[],'un vendedor sin usuario enlazado no ve contactos');
}finally{if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}}
echo "PASS $n contacts-today checks\n";
