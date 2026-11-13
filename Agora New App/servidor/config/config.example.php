<?php
// Agora New App · configuración. Cópialo como config.php (paso 6 de INSTALAR-CYBERPANEL.md). Nunca lo subas a un repositorio.
return [
 'env' => 'production',
 'app_url' => 'https://tu-dominio.com',            // exactamente como se entra (con o sin www)
 // Genera cada clave con: /usr/local/lsws/lsphp83/bin/php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
 'app_key' => 'REEMPLAZAR_POR_64_CARACTERES_HEXADECIMALES',
 'vault_key' => 'REEMPLAZAR_POR_OTROS_64_CARACTERES_HEXADECIMALES', // cifra los tokens de las cuentas. No la cambies después.
 'db' => ['host'=>'localhost','port'=>3306,'name'=>'u123_agora','user'=>'u123_agora','password'=>'CAMBIAR','persistent'=>true],
 'session_seconds'=>28800,     // sesión normal: 8 horas
 'remember_seconds'=>604800,   // «Recuérdame en este equipo»: 7 días
 'cookie_secure'=>true,
 'page_size'=>100,
 // Las conexiones con las cuentas se escriben en la app (Agencia → Editar datos) y se guardan cifradas.
 // Esto queda solo como respaldo para instalaciones antiguas: tenant ID => ['token'=>'...','location_id'=>'...']
 'ghl'=>['tenants'=>[]],
 // Firma por enlace: habilitar solamente después de aprobar el texto del convenio.
 'signing_enabled'=>false,
];
