<?php
declare(strict_types=1);
require_once __DIR__.'/../src/config.php';
$file=getenv('APP_KEY_FILE') ?: __DIR__.'/../var/integration.key';
if(is_file($file)) {echo "Encryption key already exists; preserved\n";exit;}
if(!is_dir(dirname($file)) && !mkdir(dirname($file),0700,true)) throw new RuntimeException('Cannot create key directory');
$handle=fopen($file,'x');if(!$handle)throw new RuntimeException('Cannot create key');
chmod($file,0600);fwrite($handle,base64_encode(random_bytes(32)));fclose($handle);
echo "Encryption key created; keep a separate secure backup\n";
