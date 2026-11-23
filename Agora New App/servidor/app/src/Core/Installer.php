<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
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
  try{$db->pdo->exec("CREATE TABLE IF NOT EXISTS agora_schema (version VARCHAR(50) PRIMARY KEY,checksum CHAR(64) NOT NULL,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$done=[];
   foreach(glob(dirname(__DIR__,2).'/database/migrations/*.sql') as $f){$name=basename($f);$sql=file_get_contents($f);$sha=hash('sha256',$sql);$r=$db->one('SELECT checksum FROM agora_schema WHERE version=?',[$name]);if($r){if(!hash_equals($r['checksum'],$sha))Http::fail('La migración aplicada cambió: '.$name,409);continue;}
    // Migrations contain simple DDL, no procedures or data values with semicolons.
    foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$db->pdo->exec($statement);
    $db->run('INSERT INTO agora_schema(version,checksum) VALUES(?,?)',[$name,$sha]);$done[]=$name;
   }
   // 2.11.0 · Facturación: los disparadores que protegen lo emitido (no caben en el DDL simple de las migraciones).
   if($done&&in_array('007_facturacion.sql',array_map('basename',glob(dirname(__DIR__,2).'/database/migrations/*.sql')),true))\Agora\Fiscal\Esquema::instalar($db->pdo);
   // 2.12.0 · Columnas nuevas de facturación (comprobadas una a una; no rompen si ya están).
   if($done&&in_array('008_divisas.sql',$done,true))\Agora\Fiscal\Esquema::columnas($db->pdo);
   // 2.23.1 · Base y tablas en utf8mb4 (CyberPanel crea la base en latin1).
   if(in_array('016_utf8mb4.sql',$done,true))self::utf8mb4($db);
   return $done;
  }finally{$db->run("SELECT RELEASE_LOCK('agora_migration')");}
 }
 /**
  * 2.23.1 · Pone la base en utf8mb4 (lo que se cree después nace así, también las tablas del motor) y convierte las tablas que
  * no lo estén: columna por columna, solo las que están en otro juego que no sea ascii (las columnas ASCII del motor se respetan),
  * con su tipo, si admite nulos y su valor por defecto. Idempotente. Devuelve las tablas que cambió.
  */
 public static function utf8mb4(DB $db):array{
  $pdo=$db->pdo;$maria=stripos((string)$pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),'mariadb')!==false;
  $base=$db->one('SELECT DEFAULT_CHARACTER_SET_NAME c FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE()');
  if(($base['c']??'')!=='utf8mb4')try{$pdo->exec('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');}catch(\PDOException $e){error_log('Agora utf8mb4: la base no cambió ('.$e->getCode().')');}
  $tablas=$db->all("SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND (TABLE_COLLATION IS NULL OR TABLE_COLLATION NOT LIKE 'utf8mb4%')");
  $cols=$db->all("SELECT TABLE_NAME t,COLUMN_NAME c,COLUMN_TYPE ty,IS_NULLABLE n,COLUMN_DEFAULT d,EXTRA x,CHARACTER_SET_NAME cs FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME NOT IN ('utf8mb4','ascii','binary') ORDER BY TABLE_NAME,ORDINAL_POSITION");
  $por=[];foreach($tablas as $r)$por[$r['t']]=[];foreach($cols as $c)$por[$c['t']][]=$c;
  $q=fn(string $n):string=>'`'.str_replace('`','``',$n).'`';
  $hechas=[];$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
  try{foreach($por as $t=>$lista){
   $partes=['DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'];
   foreach($lista as $c){
    $def='';
    if($c['d']!==null&&!preg_match('/^text|blob/i',$c['ty'])){
     $d=(string)$c['d'];
     if($maria)$def=strtoupper($d)==='NULL'?'':' DEFAULT '.$d; // MariaDB ya da el literal entre comillas (o la expresión)
     else $def=' DEFAULT '.$pdo->quote($d);
    }
    $extra=preg_match('/on update [a-z_()0-9]+/i',(string)$c['x'],$m)?' '.$m[0]:'';
    $partes[]='MODIFY '.$q($c['c']).' '.$c['ty'].' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci '.($c['n']==='YES'?'NULL':'NOT NULL').$def.$extra;
   }
   try{$pdo->exec('ALTER TABLE '.$q($t).' '.implode(', ',$partes));$hechas[]=$t;}
   catch(\PDOException){ // una columna que no se deja (por ejemplo, de texto con una relación): se hacen una por una y se salta esa
    foreach($partes as $p)try{$pdo->exec('ALTER TABLE '.$q($t).' '.$p);}catch(\PDOException $e){error_log('Agora utf8mb4: '.$t.' · '.substr($p,0,60).' no cambió ('.$e->getCode().')');}
    $hechas[]=$t;}
  }}finally{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
  return $hechas;
 }
 public static function owner(DB $db,array $b):void{$db->transaction(function()use($db,$b){if($db->one("SELECT id FROM agora_users WHERE role='MASTER'"))Http::fail('Ya existe un dueño. Instalador cerrado.',409);$db->run("INSERT INTO agora_users(name,email,password_hash,role) VALUES(?,?,?,'MASTER')",[Http::text($b,'nombre'),Http::email($b),password_hash(Http::password($b['clave']??''),PASSWORD_DEFAULT)]);});}
}
