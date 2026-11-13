<?php
declare(strict_types=1);
namespace Agora\Core;
use PDO;
/*
 * Base de datos. Producción: MySQL/MariaDB. Modo pruebas (2.1.14): SQLite en un archivo, sin crear nada a mano.
 * Con SQLite, run() traduce las pocas construcciones propias de MySQL que usa el código
 * (FOR UPDATE, ON DUPLICATE KEY UPDATE, DATE_ADD/DATE_SUB, IF, GET_LOCK…), así todos los controladores funcionan igual.
 */
final class DB {
 public PDO $pdo;
 public string $driver='mysql';
 private const UPSERT_KEYS=['agora_rate_limits'=>'bucket','agora_settings'=>'tenant_id,setting_key'];
 private array $cache=[];
 public function __construct(array $c){
  $opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
  if(($c['driver']??'mysql')==='sqlite'){
   $this->driver='sqlite';$path=(string)($c['path']??'');
   if($path==='')throw new \PDOException('Falta la ruta del archivo SQLite.');
   $dir=dirname($path);if(!is_dir($dir))@mkdir($dir,0750,true);
   $this->pdo=new PDO('sqlite:'.$path,null,null,$opts+[PDO::ATTR_TIMEOUT=>10]);
   foreach(['PRAGMA journal_mode=WAL','PRAGMA foreign_keys=ON','PRAGMA busy_timeout=10000','PRAGMA synchronous=NORMAL'] as $p)$this->pdo->exec($p);
   $this->sqliteFunctions();
   return;
  }
  $this->pdo=new PDO("mysql:host={$c['host']};port=".($c['port']??3306).";dbname={$c['name']};charset=utf8mb4",$c['user'],$c['password'],$opts+[PDO::ATTR_PERSISTENT=>(bool)($c['persistent']??false)]);
  if($this->pdo->inTransaction())$this->pdo->rollBack();
  $this->pdo->exec("SET time_zone='+00:00'");
 }
 public function sqlite():bool{return $this->driver==='sqlite';}
 private function sqliteFunctions():void{
  $p=$this->pdo;
  $p->sqliteCreateFunction('UTC_TIMESTAMP',fn()=>gmdate('Y-m-d H:i:s'),0);
  $p->sqliteCreateFunction('JSON_UNQUOTE',fn($v)=>$v,1);
  $p->sqliteCreateFunction('GET_LOCK',fn()=>1);
  $p->sqliteCreateFunction('RELEASE_LOCK',fn()=>1);
  $p->sqliteCreateFunction('agora_date_add',function($base,$n,$unit){
   if($base===null)return null;$t=strtotime((string)$base.' UTC');if($t===false)return null;
   $u=['SECOND'=>'seconds','MINUTE'=>'minutes','HOUR'=>'hours','DAY'=>'days','MONTH'=>'months','YEAR'=>'years'][strtoupper((string)$unit)]??'seconds';
   return gmdate('Y-m-d H:i:s',strtotime(sprintf('%+d %s',(int)$n,$u),$t));
  },3);
 }
 /** Traduce una consulta escrita para MySQL al dialecto de SQLite (solo lo que usa Ágora). */
 public function translate(string $sql):string{
  if(isset($this->cache[$sql]))return $this->cache[$sql];
  $s=preg_replace('/\s+FOR UPDATE\b/i','',$sql);
  $s=preg_replace_callback('/DATE_(ADD|SUB)\(\s*(UTC_TIMESTAMP\(\)|\?)\s*,\s*INTERVAL\s+(\?|\d+)\s+(SECOND|MINUTE|HOUR|DAY|MONTH|YEAR)\s*\)/i',
   fn($m)=>'agora_date_add('.$m[2].','.(strtoupper($m[1])==='SUB'?'-':'').($m[3]==='?'?'(?)':$m[3]).",'".strtoupper($m[4])."')",$s);
  $s=preg_replace('/\bIF\(/','iif(',$s);
  if(preg_match('/^\s*INSERT INTO (\w+)/i',$s,$t)&&stripos($s,'ON DUPLICATE KEY UPDATE')!==false){
   $key=self::UPSERT_KEYS[$t[1]]??null;
   if($key){$s=str_ireplace('ON DUPLICATE KEY UPDATE','ON CONFLICT('.$key.') DO UPDATE SET',$s);$s=preg_replace('/\bVALUES\((\w+)\)/','excluded.$1',$s);}
  }
  if(preg_match('/^\s*DELETE\b/i',$s))$s=preg_replace('/\s+LIMIT\s+\d+\s*$/i','',$s);
  $s=preg_replace('/\bAS UNSIGNED\b/i','AS INTEGER',$s);
  return $this->cache[$sql]=$s;
 }
 public function run(string $sql,array $args=[]):\PDOStatement{if($this->driver==='sqlite')$sql=$this->translate($sql);$s=$this->pdo->prepare($sql);$s->execute($args);return $s;}
 public function one(string $sql,array $args=[]):?array{return $this->run($sql,$args)->fetch()?:null;}
 public function all(string $sql,array $args=[]):array{return $this->run($sql,$args)->fetchAll();}
 public function transaction(callable $f):mixed{if($this->pdo->inTransaction())return $f();$this->pdo->beginTransaction();try{$r=$f();$this->pdo->commit();return $r;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
 public static function json(mixed $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
 public function setting(int $t,string $key,mixed $default=[]):mixed{$r=$this->one('SELECT data FROM agora_settings WHERE tenant_id=? AND setting_key=?',[$t,$key]);return $r?json_decode($r['data'],true,512,JSON_THROW_ON_ERROR):$default;}
 public function saveSetting(int $t,string $key,mixed $v):void{$this->run('INSERT INTO agora_settings(tenant_id,setting_key,data) VALUES(?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),updated_at=UTC_TIMESTAMP()',[$t,$key,self::json($v)]);}
}
