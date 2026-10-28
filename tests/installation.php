<?php
declare(strict_types=1);
// Isolated installation checks: mock only schema metadata/counts, never secrets.
$stored=0;$missingSchema=false;
class MissingTestSchema extends PDOException {public function __construct(){parent::__construct('fixture');$this->code='42S02';}}
function query(string $sql,array $args=[]):object {
    global $stored,$missingSchema;
    if($missingSchema && str_contains($sql,'LIMIT 0'))throw new MissingTestSchema();
    $value=str_contains($sql,'COUNT(*)')?$stored:1;
    return new class($value){public function __construct(private int $value){}public function fetchColumn():int{return $this->value;}};
}
require __DIR__.'/../src/Installation.php';
$directory=sys_get_temp_dir().'/agora-install-test-'.bin2hex(random_bytes(6));mkdir($directory,0700);
$key=$directory.'/integration.key';$previous=[];$checks=0;
foreach(['APP_KEY_FILE','APP_ENV','APP_ORIGIN'] as $name)$previous[$name]=getenv($name);
function verifyInstallation(bool $ok,string $label):void{global $checks;if(!$ok)throw new Exception($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
try{
 putenv('APP_ENV=production');putenv('APP_ORIGIN=https://nueva.agoradigital.app');putenv('APP_KEY_FILE='.$key);
 $result=Installation::checks();
 verifyInstallation(in_array('encryption_key_missing',array_column($result,'code'),true),'missing key produces actionable setup code');
 verifyInstallation(!str_contains(json_encode($result),$directory),'diagnostics do not expose private paths');
 Installation::prepareEncryption();
 verifyInstallation(is_file($key) && (fileperms($key)&0777)===0600,'admin preparation creates private key with mode 600');
 $vault=TokenVault::fromEnvironment();$cipher=$vault->seal('test-token',1,'test');
 verifyInstallation($vault->open($cipher,1,'test')==='test-token','prepared key supports credential encryption');
 $hash=hash_file('sha256',$key);$stored=1;Installation::prepareEncryption();
 verifyInstallation(hash_file('sha256',$key)===$hash,'existing key is preserved even with stored connections');
 unlink($key);$blocked=false;
 try{Installation::prepareEncryption();}catch(SetupException $e){$blocked=$e->issue==='encryption_restore_required';}
 verifyInstallation($blocked&&!file_exists($key),'missing original key is never replaced when credentials exist');
 $stored=0;file_put_contents($key,'invalid-key');$invalid=false;
 try{TokenVault::fromEnvironment();}catch(SetupException $e){$invalid=$e->issue==='encryption_key_invalid';}
 verifyInstallation($invalid,'invalid key is diagnosed without silently regenerating');
 putenv('APP_KEY_FILE='.__DIR__.'/../public/forbidden-test.key');$unsafe=false;
 try{Installation::prepareEncryption();}catch(SetupException $e){$unsafe=$e->issue==='encryption_key_unsafe_path';}
 verifyInstallation($unsafe&&!file_exists(__DIR__.'/../public/forbidden-test.key'),'key creation inside document root is blocked');
 putenv('APP_KEY_FILE='.$key);$missingSchema=true;
 verifyInstallation(in_array('schema_pending',array_column(Installation::checks(),'code'),true),'missing migration has a separate diagnostic');
 echo 'PASS '.$checks.' installation checks'.PHP_EOL;
}finally{
 if(is_file($key))unlink($key);rmdir($directory);
 foreach($previous as $name=>$value)putenv($value===false?$name:$name.'='.$value);
}
