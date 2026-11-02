<?php
declare(strict_types=1);
// Cada pantalla que el servidor de desarrollo conoce también debe estar en las reglas del servidor real (.htaccess):
// si falta, recargar esa pantalla en producción da 404 (ocurrió con /ayuda en la 1.9.0).
$router=file_get_contents(__DIR__.'/../public/router.php');$htaccess=file_get_contents(__DIR__.'/../public/.htaccess');
preg_match("~in_array\(rtrim\(\\\$path,'/'\)\?:'/',\[([^\]]+)\]~",$router,$m)||die("FAIL could not read the screen list from router.php\n");
preg_match_all("~'/([a-z.]+)'~",$m[1],$screens);
preg_match('~\^\(\?:([a-z|]+)\)/\?\$ index\.html~',$htaccess,$h)||die("FAIL could not read the screen rule from .htaccess\n");
$served=explode('|',$h[1]);$missing=[];
foreach($screens[1] as $screen){if($screen==='conexiones.html')continue;if(!in_array($screen,$served,true))$missing[]=$screen;}
if($missing){fwrite(STDERR,"FAIL the real server would 404 on: ".implode(', ',$missing)."\n");exit(1);}
echo "PASS every screen of the app is also routed by the production server rules (".count($screens[1])." checked)\n";
