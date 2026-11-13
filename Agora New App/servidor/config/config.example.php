<?php
return [
 'env' => 'production',
 'app_url' => 'https://tu-dominio.com',
 // Generar: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
 'app_key' => 'REEMPLAZAR_POR_64_CARACTERES_HEXADECIMALES',
 'db' => ['host'=>'localhost','port'=>3306,'name'=>'u123_agora','user'=>'u123_agora','password'=>'CAMBIAR','persistent'=>true],
 'install_token'=>'', // Genera otro token aleatorio de 64 caracteres para instalar por web. Vacío deshabilita.
 'session_seconds'=>28800,     // sesión normal: 8 horas
 'remember_seconds'=>604800,   // «Recuérdame en este equipo»: 7 días
 'cookie_secure'=>true,
 'page_size'=>100,
 // Las integraciones requieren credenciales del cliente. Nunca exponerlas en public_html.
 'ghl'=>['tenants'=>[]], // tenant ID => ['token'=>'...','location_id'=>'...']
 // Firma por enlace: habilitar solamente después de aprobar el texto del convenio.
 'signing_enabled'=>false,
];
