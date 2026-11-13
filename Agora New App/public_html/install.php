<?php
declare(strict_types=1);
// Agora New App: la instalación ya no se hace desde el navegador. La cuenta de dueño se crea solo por terminal.
http_response_code(410);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalación</title>
<body style="font-family:system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 20px;line-height:1.5">
<h1>La instalación se hace por la terminal</h1>
<p>Por seguridad, la base de datos y la cuenta de dueño se preparan desde la terminal del servidor:</p>
<pre style="background:#f1f1f5;padding:12px;border-radius:8px;white-space:pre-wrap">php servidor/bin/migrate.php
php servidor/bin/crear-dueno.php</pre>
<p>Los pasos completos están en <b>INSTALAR-CYBERPANEL.md</b>. Puedes borrar este archivo.</p></body></html>
