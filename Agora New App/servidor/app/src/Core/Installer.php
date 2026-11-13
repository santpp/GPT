<?php
declare(strict_types=1);
namespace Agora\Core;
final class Installer {
 public static function migrate(DB $db):array{
  if($db->sqlite()){ // Modo pruebas: un solo esquema SQLite equivalente a todas las migraciones MySQL.
   $f=dirname(__DIR__,2).'/database/sqlite/esquema.sql';$sql=(string)file_get_contents($f);
   $hecho=false;try{$hecho=(bool)$db->one('SELECT 1 ok FROM agora_schema WHERE version=?',[Pruebas::ESQUEMA]);}catch(\PDOException){}
   // Agora New App: tablas añadidas después del esquema inicial (idempotentes).
   foreach(glob(dirname(__DIR__,2).'/database/sqlite/0*.sql') as $extra)$db->pdo->exec((string)file_get_contents($extra));
   if($hecho)return [];
   $db->pdo->exec($sql);$db->run('INSERT INTO agora_schema(version,checksum) VALUES(?,?)',[Pruebas::ESQUEMA,hash('sha256',$sql)]);return [Pruebas::ESQUEMA];
  }
  if(!(int)$db->one("SELECT GET_LOCK('agora_migration',10) ok")['ok'])Http::fail('Hay otra instalación en curso.',409);
  try{$db->pdo->exec("CREATE TABLE IF NOT EXISTS agora_schema (version VARCHAR(50) PRIMARY KEY,checksum CHAR(64) NOT NULL,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");$done=[];
   foreach(glob(dirname(__DIR__,2).'/database/migrations/*.sql') as $f){$name=basename($f);$sql=file_get_contents($f);$sha=hash('sha256',$sql);$r=$db->one('SELECT checksum FROM agora_schema WHERE version=?',[$name]);if($r){if(!hash_equals($r['checksum'],$sha))Http::fail('La migración aplicada cambió: '.$name,409);continue;}
    // Migrations contain simple DDL, no procedures or data values with semicolons.
    foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$db->pdo->exec($statement);
    $db->run('INSERT INTO agora_schema(version,checksum) VALUES(?,?)',[$name,$sha]);$done[]=$name;
   }return $done;
  }finally{$db->run("SELECT RELEASE_LOCK('agora_migration')");}
 }
 public static function owner(DB $db,array $b):void{$db->transaction(function()use($db,$b){if($db->one("SELECT id FROM agora_users WHERE role='MASTER'"))Http::fail('Ya existe un dueño. Instalador cerrado.',409);$db->run("INSERT INTO agora_users(name,email,password_hash,role) VALUES(?,?,?,'MASTER')",[Http::text($b,'nombre'),Http::email($b),password_hash(Http::password($b['clave']??''),PASSWORD_DEFAULT)]);});}
}
