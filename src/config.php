<?php
declare(strict_types=1);
// Native hosting configuration stays outside the document root. Existing process
// variables (Docker/cloud bindings) take precedence; values are never logged.
$file=dirname(__DIR__).'/config/local.php';
if(is_file($file)) {
    $settings=require $file;
    if(!is_array($settings))throw new RuntimeException('Invalid local configuration');
    $allowed=['APP_ENV','APP_ORIGIN','DB_DSN','DB_USER','DB_PASSWORD','GHL_LOCATION_ID','APP_KEY_FILE','GHL_WEBHOOKS_ENABLED'];
    // Access codes travel through a connected account, so no mail credentials are read here.
    foreach($allowed as $name) {
        if(getenv($name)===false && isset($settings[$name]) && is_string($settings[$name]))putenv($name.'='.$settings[$name]);
    }
    unset($settings);
}
