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
$noFolders=$d->normalize($fields,$conn,null);
ck(count(array_filter($noFolders,fn($i)=>$i['folder']!==null))===0,'when the folders cannot be read, fields are still listed, just ungrouped');
$viaFolderRead=$d->normalize(['customFields'=>[['id'=>'f9','name'=>'X','dataType'=>'TEXT','locationId'=>'loc','model'=>'contact']]],$conn,['folders'=>[['id'=>'fo1','name'=>'Carpeta']],'fields'=>[['id'=>'f9','parentId'=>'fo1']]]);
ck($viaFolderRead[0]['folder']==='Carpeta','the folder of a field is also read from the folder listing when the field list does not say');
// tags and folders go through the documented paths
$paths=[];$client=new GhlClient('t',function($path)use(&$paths){$paths[]=$path;return ['status'=>200,'body'=>'{"tags":[{"id":"1","name":"vip"}],"folders":[]}'];});
$client->locationTags('loc');$client->contactFolders('loc');
ck($paths===['/locations/loc/tags','/custom-fields/object-key/contact?locationId=loc'],'the account\'s tags and field folders are read from their own endpoints');
echo "PASS $n contact folder checks\n";
