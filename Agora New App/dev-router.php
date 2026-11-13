<?php
// Solo desarrollo local: imita las reglas del .htaccess con el servidor de PHP.
// php -S 127.0.0.1:8090 -t "Agora New App/public_html" "Agora New App/dev-router.php"
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$root=__DIR__.'/public_html';
if(preg_match('~^/api(/|$)~',$path)){chdir($root.'/api');require $root.'/api/index.php';return true;}
if($path!=='/'&&is_file($root.$path))return false;
if(preg_match('~^/(centro|modulos|inicio|comercial|bandeja|contactos|oportunidades|tareas|equipo|publicidad|ventas|facturacion|costos|catalogo|tendencias|calidad|configuracion|contratos|con-convenio|registro|auditoria|usuarios|ajustes|ayuda|agencia|perfil|tablero|personas|conexiones|entrar|conversations|migracion|productos|personalizar)(/.*)?$~',$path)||$path==='/'){readfile($root.'/index.html');return true;}
http_response_code(404);
