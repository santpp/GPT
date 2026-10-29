<?php
// Copy to local.php in this PRIVATE directory and edit through SFTP/the panel.
// Never put this file under public_html or send its contents in chat.
return [
    'APP_ENV'=>'production',
    'APP_ORIGIN'=>'https://nueva.agoradigital.app',
    'DB_DSN'=>'mysql:host=localhost;dbname=REPLACE_DATABASE_NAME;charset=utf8mb4',
    'DB_USER'=>'REPLACE_DATABASE_USER',
    'DB_PASSWORD'=>'REPLACE_DATABASE_PASSWORD',
    'GHL_LOCATION_ID'=>'agora-workspace', // Initial workspace reference, not a PIT.
    'APP_KEY_FILE'=>dirname(__DIR__).'/var/integration.key',
    'GHL_WEBHOOKS_ENABLED'=>'0',
    'LOGIN_MAIL_FROM'=>'acceso@nueva.agoradigital.app',
];
