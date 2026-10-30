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
    // Android notifications. The Firebase service account JSON, kept OUTSIDE
    // public_html and out of Git. Leave it out and Ágora simply does not notify.
    // 'FCM_CREDENTIALS_FILE'=>dirname(__DIR__).'/var/fcm-service-account.json',
    // Avisos en el navegador y en el móvil sin tienda de aplicaciones. Ágora
    // crea este archivo solo la primera vez; mantenlo fuera de public_html.
    'WEBPUSH_KEY_FILE'=>dirname(__DIR__).'/var/webpush.json',
    // Access codes are delivered by the connected account chosen in
    // Conexiones → Canal de acceso. No mail server credentials belong here.
];
