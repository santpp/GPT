<?php
declare(strict_types=1);
// Copia de seguridad diaria. Cron: una vez al día, por ejemplo
//   15 3 * * * php /RUTA/agora-private/bin/backup.php
// Con BACKUP_PASSPHRASE en config/local.php la copia va cifrada e incluye las
// claves de var/; sin ella, sólo la base de datos.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Backup.php';
require_once __DIR__.'/../src/PushDelivery.php';
$root=dirname(__DIR__);
$dir=getenv('BACKUP_DIR')?:$root.'/backups';
$files=['integration.key'=>getenv('APP_KEY_FILE')?:$root.'/var/integration.key','webpush.json'=>getenv('WEBPUSH_KEY_FILE')?:'',
    'fcm-service-account.json'=>getenv('FCM_CREDENTIALS_FILE')?:'','local.php'=>$root.'/config/local.php'];
try {
    $result=Backup::run($dir,getenv('BACKUP_PASSPHRASE')?:null,$files,(int)(getenv('BACKUP_KEEP')?:14));
    PushDelivery::remember('backup_last_run',['at'=>gmdate('c'),'ok'=>true,'encrypted'=>$result['encrypted'],'bytes'=>$result['bytes'],'tables'=>$result['tables'],'keys'=>count($result['keys'])]);
    printf("Backup %s, %d bytes, %d tables, %s%s",$result['file'],$result['bytes'],$result['tables'],$result['encrypted']?'encrypted with keys':'database only (set BACKUP_PASSPHRASE to include keys)',PHP_EOL);
} catch(Throwable $e) {
    PushDelivery::remember('backup_last_run',['at'=>gmdate('c'),'ok'=>false,'error'=>$e->getMessage()==='passphrase_short'?'passphrase_short':'failed']);
    fwrite(STDERR,'Backup failed: '.($e->getMessage()==='passphrase_short'?'BACKUP_PASSPHRASE must have at least 12 characters':'check the folder permissions and the database').PHP_EOL);
    exit(1);
}
