<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
function fail(string $message): never {fwrite(STDERR,'FAIL: '.$message.PHP_EOL);exit(1);}
if(PHP_VERSION_ID<80300)fail('PHP 8.3+ required');
foreach(['pdo_mysql','sodium','curl','openssl','mbstring'] as $module) {
    if(!extension_loaded($module))fail("missing PHP extension $module");
}
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/TokenVault.php';
if(getenv('APP_ENV')!=='production')fail('configure APP_ENV=production in private config/local.php');
if(!str_starts_with(getenv('APP_ORIGIN') ?: '', 'https://'))fail('HTTPS APP_ORIGIN required');
try {
    db()->query('SELECT connection_id FROM ghl_sync_state LIMIT 0');
    db()->query('SELECT connection_id FROM ghl_inbox_snapshots LIMIT 0');
    TokenVault::fromEnvironment();
} catch(Throwable $e) {fwrite(STDERR,"FAIL: check database credentials, schema migrations, encryption key and file permissions in private configuration\n");exit(1);}
echo "PASS PHP version, extensions, database and encryption key; no secret values displayed\n";
