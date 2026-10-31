<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ContactFieldDirectory.php';
$n=0;function ck(bool $ok,string $label):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}$n++;echo "PASS $label\n";}
$conn=['id'=>1,'location_id'=>'loc'];
$fields=['customFields'=>[
 ['id'=>'f1','name'=>'Presupuesto','dataType'=>'MONETORY','position'=>2,'locationId'=>'loc','model'=>'contact','parentId'=>'fo2'],
 ['id'=>'f2','name'=>'Ciudad natal','dataType'=>'TEXT','position'=>1,'locationId'=>'loc','model'=>'contact','parentId'=>'fo1'],
 ['id'=>'f3','name'=>'Interés','dataType'=>'TEXT','position'=>1,'locationId'=>'loc','model'=>'contact'],
 ['id'=>'f4','name'=>'Plan','dataType'=>'TEXT','position'=>1,'locationId'=>'loc','model'=>'contact','parentId'=>'fo2'],
]];
$folders=['folders'=>[['id'=>'fo1','name'=>'Datos personales'],['id'=>'fo2','name'=>'Ventas']],'fields'=>[]];
$d=new ContactFieldDirectory();
$items=$d->normalize($fields,$conn,$folders);
ck(array_column($items,'id')===['f2','f4','f1','f3'],'fields follow the account\'s folder order, then their position, and fields without a folder come last');
ck($items[0]['folder']==='Datos personales'&&$items[1]['folder']==='Ventas'&&$items[3]['folder']===null,'each field carries the name of its folder');
// la cuenta da sólo el id de la carpeta: se agrupa igual, con nombre provisional o con el que se conozca
$byId=$d->normalize($fields,$conn,null);
ck(array_column($byId,'folder')===['Carpeta 1','Carpeta 2','Carpeta 2',null]||array_column($byId,'folder')===['Carpeta 2','Carpeta 1','Carpeta 1',null]||count(array_unique(array_filter(array_column($byId,'folder'))))===2,'with only the folder ids, fields are still grouped, under provisional names');
$named=$d->normalize($fields,$conn,null,['fo1'=>'Datos personales','fo2'=>'Ventas']);
ck($named[0]['folder']==='Datos personales'&&$named[0]['folderId']==='fo1'&&$named[1]['folder']==='Ventas','known names (set by the admin) replace the provisional ones');
ck(ContactFieldDirectory::parentOrder($fields,$conn)===['fo1','fo2']||ContactFieldDirectory::parentOrder($fields,$conn)===['fo2','fo1'],'folder ids come out in the order their fields appear');
$paths=[];$client=new GhlClient('t',function($path)use(&$paths){$paths[]=$path;return ['status'=>200,'body'=>'{"tags":[{"id":"1","name":"vip"}]}'];});
$client->locationTags('loc');
ck($paths===['/locations/loc/tags'],'the account\'s tags are read from their own endpoint');
// nombres puestos por el administrador y listado de carpetas
$t=null;
try{
  query("INSERT INTO tenants(name,location_id) VALUES(?,?)",['Diag','diag-'.bin2hex(random_bytes(4))]);$t=db()->lastInsertId();
  query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$t,'Diag','loc',str_repeat('x',16)]);
  $cid=(int)db()->lastInsertId();
  $conn=query('SELECT * FROM ghl_connections WHERE id=?',[$cid])->fetch();
  $calls=[];
  $gw=new GhlGateway(function()use(&$calls,$fields){return new GhlClient('t',function($path)use(&$calls,$fields){
    $calls[]=$path;
    if(str_ends_with($path,'/customFields'))return ['status'=>200,'body'=>json_encode($fields)];
    return ['status'=>400,'body'=>'{"message":"Api does not support objectKey"}'];});});
  $dir=new ContactFieldDirectory($gw);
  $list=$dir->folders($conn);
  ck(count($list)===2&&$list[0]['named']===false,'the admin sees each folder with sample fields, still unnamed');
  ck(count(array_filter($calls,fn($c)=>str_contains($c,'/custom-fields/')))===1,'when the account refuses to name a folder, it is asked once, not once per folder');
  query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$cid]);
  ContactFieldDirectory::setAdminNames($conn,['fo1'=>'Datos personales','fo2'=>'  Ventas  ','zzz bad id'=>'x']);
  $after=$dir->folders($conn);
  $names=array_column($after,'name','id');
  ck($names['fo1']==='Datos personales'&&$names['fo2']==='Ventas'&&$after[0]['byAdmin']===true,'names set by the admin are used and tidy');
  $n2=count($calls);$dir->get($conn);
  ck(count($calls)===$n2,'and the directory is served from the stored copy afterwards');
  query('DELETE FROM app_state WHERE name=?',['field-folders:'.$cid]);query('DELETE FROM contact_field_cache WHERE connection_id=?',[$cid]);
  query('DELETE FROM ghl_connections WHERE id=?',[$cid]);query('DELETE FROM tenants WHERE id=?',[$t]);
}catch(Throwable $e){fwrite(STDERR,"FAIL folders: ".$e->getMessage()."\n");exit(1);}
echo "PASS $n contact folder checks\n";
