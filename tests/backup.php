<?php
declare(strict_types=1);
// La copia diaria: cifrada con frase e incluyendo las claves, sólo la base de
// datos sin frase, y restaurable de verdad en una base de datos nueva.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Backup.php';
$checks=0;$dir=sys_get_temp_dir().'/agora-backup-test-'.bin2hex(random_bytes(4));$restoreDb='agora_restore_'.bin2hex(random_bytes(4));
function ok(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
$key=tempnam(sys_get_temp_dir(),'key-');file_put_contents($key,'clave-de-prueba-'.bin2hex(random_bytes(8)));
$marker='backup-'.bin2hex(random_bytes(5));
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Copia',$marker]);
 $plain=Backup::run($dir,null,['integration.key'=>$key]);
 $zip=new ZipArchive();$zip->open($dir.'/'.$plain['file']);
 ok(!$plain['encrypted']&&str_ends_with($plain['file'],'.zip')&&$zip->locateName('private/integration.key')===false,'without a passphrase the copy holds the database only: keys are never copied unencrypted');
 ok(str_contains($zip->getFromName('LEEME.txt'),'NO incluye las claves'),'and it says so inside');
 $sql=$zip->getFromName('database.sql');$zip->close();
 ok(str_contains($sql,'CREATE TABLE `tenants`')&&str_contains($sql,$marker),'the database is in it, with its rows');
 ok(!str_contains($sql,'CREATE TABLE `directory_cache`')&&!str_contains($sql,'CREATE TABLE `conversation_index`')&&!str_contains($sql,'CREATE TABLE `login_attempts`'),'caches and short-lived records are left out: they rebuild themselves');

 $failed=false;try{Backup::run($dir,'corta',['integration.key'=>$key]);}catch(InvalidArgumentException $e){$failed=$e->getMessage()==='passphrase_short';}
 ok($failed,'a passphrase shorter than 12 characters is refused');
 $sealed=Backup::run($dir,'una frase larga de prueba',['integration.key'=>$key,'missing.json'=>'/does/not/exist']);
 $raw=file_get_contents($dir.'/'.$sealed['file']);
 ok($sealed['encrypted']&&str_ends_with($sealed['file'],'.agora-backup')&&str_starts_with($raw,Backup::MAGIC)&&!str_contains($raw,$marker)&&!str_contains($raw,'clave-de-prueba'),'with a passphrase the copy is encrypted: neither the data nor the key can be read');
 ok($sealed['keys']===['integration.key'],'and it carries the keys that exist, skipping the ones that do not');
 $failed=false;try{Backup::open($raw,'otra frase cualquiera');}catch(InvalidArgumentException $e){$failed=$e->getMessage()==='wrong_passphrase';}
 ok($failed,'the wrong passphrase opens nothing');
 $tampered=$raw;$tampered[strlen($tampered)-5]=chr(ord($tampered[strlen($tampered)-5])^1);
 $failed=false;try{Backup::open($tampered,'una frase larga de prueba');}catch(InvalidArgumentException $e){$failed=true;}
 ok($failed,'a damaged copy is detected instead of restoring garbage');
 $opened=tempnam(sys_get_temp_dir(),'open-');file_put_contents($opened,Backup::open($raw,'una frase larga de prueba'));
 $zip=new ZipArchive();$zip->open($opened);
 ok($zip->getFromName('private/integration.key')===file_get_contents($key),'opened with its passphrase, the key comes back exactly');
 $sql=$zip->getFromName('database.sql');$zip->close();@unlink($opened);

 // Restaurar de verdad en una base de datos vacía.
 db()->exec('CREATE DATABASE `'.$restoreDb.'` CHARACTER SET utf8mb4');
 $dsn=preg_replace('/dbname=[^;]+/','dbname='.$restoreDb,(string)getenv('DB_DSN'));
 $restore=new PDO($dsn,getenv('DB_USER')?:'',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $restore->exec($sql);
 ok((int)$restore->query("SELECT COUNT(*) FROM tenants WHERE location_id=".$restore->quote($marker))->fetchColumn()===1,'the copy restores into an empty database, rows included');
 ok((int)$restore->query('SELECT COUNT(*) FROM users')->fetchColumn()===(int)query('SELECT COUNT(*) FROM users')->fetchColumn(),'with every user');

 for($i=0;$i<4;$i++){usleep(1100000);Backup::run($dir,null,[],3);}
 ok(count(glob($dir.'/agora-*'))===3,'only the latest copies are kept');
 echo "PASS $checks backup checks (real MariaDB, real restore)\n";
} finally {
 query('DELETE FROM tenants WHERE location_id=?',[$marker]);
 try{db()->exec('DROP DATABASE IF EXISTS `'.$restoreDb.'`');}catch(Throwable $e){}
 foreach(glob($dir.'/*')?:[] as $f)@unlink($f);@rmdir($dir);@unlink($key);
}
