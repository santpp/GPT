<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
/**
 * 2.19.0 · Diagnóstico por terminal (pedido del usuario). Sirve aunque la web no abra.
 *
 *   php servidor/bin/diagnostico.php
 *
 * Revisa PHP, configuración, base de datos, migraciones, tablas, disco, hora, correo de Ágora, subcuentas, tasa BCV,
 * errores y reportes recientes y códigos equivocados. Cada línea sale en verde (bien), amarillo (revisar) o rojo (falla).
 * Solo lee: no cambia nada. Nunca muestra claves, tokens ni códigos. Termina con código 1 si hay algo en rojo.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$rojo=0;$amar=0;$color=stream_isatty(STDOUT);
$linea=function(string $nivel,string $que,string $detalle='')use(&$rojo,&$amar,$color):void{
 [$m,$c]=match($nivel){'ok'=>['✔','32'],'aviso'=>['!','33'],default=>['✖','31']};
 if($nivel==='mal')$rojo++;if($nivel==='aviso')$amar++;
 $txt=(function_exists('mb_str_pad')?mb_str_pad($que,28):$que.str_repeat(' ',max(1,28-mb_strlen($que)))).($detalle!==''?' '.$detalle:'');
 echo ($color?"\033[{$c}m$m\033[0m":$m).' '.$txt."\n";
};
$seccion=function(string $t)use($color):void{echo "\n".($color?"\033[1m$t\033[0m":$t)."\n";};
echo "Diagnóstico de Ágora Digital App · ".gmdate('Y-m-d H:i')." UTC\n";

$seccion('Servidor');
$linea(PHP_VERSION_ID>=80300?'ok':(PHP_VERSION_ID>=80100?'aviso':'mal'),'PHP',PHP_VERSION.(PHP_VERSION_ID<80300?' (se recomienda 8.3)':''));
foreach(['pdo_mysql','mbstring','openssl','json','curl'] as $ext)$linea(extension_loaded($ext)?'ok':'mal','Extensión '.$ext,extension_loaded($ext)?'instalada':'falta: dnf install lsphp83-'.($ext==='pdo_mysql'?'mysqlnd':$ext));
$libre=@disk_free_space(__DIR__);if($libre!==false){$gb=$libre/1073741824;$linea($gb>=2?'ok':($gb>=0.5?'aviso':'mal'),'Disco libre',number_format($gb,1,',','.').' GB');}
$st=dirname(__DIR__).'/storage';$linea(is_dir($st)&&is_writable($st)?'ok':'mal','Carpeta storage',is_dir($st)?(is_writable($st)?'se puede escribir':'sin permiso de escritura'):'no existe');
$api=@file_get_contents(dirname(__DIR__).'/app/src/Controllers/Api.php');$ver=$api&&preg_match("/'version'=>'([\d.]+)'/",$api,$m)?$m[1]:'?';$linea('ok','Versión instalada',$ver);

$seccion('Configuración');
try{require dirname(__DIR__).'/bootstrap.php';}catch(Throwable $e){$linea('mal','config.php',$e->getMessage());echo "\nResumen: falta la configuración; lo demás no se puede revisar.\n";exit(1);}
$linea('ok','config.php','cargado'.(defined('AGORA_MODO_PRUEBAS')?' (MODO PRUEBAS)':''));
$url=(string)($config['app_url']??'');$linea(str_starts_with($url,'https://')?'ok':'aviso','Dirección (app_url)',$url!==''?$url.(str_starts_with($url,'https://')?'':' · sin https: el logo no sale en los correos'):'vacía');
$linea(($config['env']??'')==='production'?'ok':'aviso','Entorno',(string)($config['env']??'sin definir'));

$seccion('Base de datos');
try{$db=new \Agora\Core\DB($config['db']);$linea('ok','Conexión','bien');}catch(Throwable $e){$linea('mal','Conexión',preg_replace('/password[^ ]*/i','',$e->getMessage()));echo "\nResumen: sin base de datos.\n";exit(1);}
$mysql=$db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
if($mysql){
 $v=(string)$db->pdo->query('SELECT VERSION()')->fetchColumn();$linea('ok','Motor',$v);
 $desfase=abs(time()-(int)$db->pdo->query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP())')->fetchColumn());
 $linea($desfase<120?'ok':'aviso','Hora',$desfase<120?'servidor y base de datos de acuerdo':"desfase de $desfase s entre PHP y la base de datos");
 $mal=$db->all("SELECT TABLE_NAME t,TABLE_COLLATION c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'agora\\_%' AND TABLE_COLLATION NOT LIKE 'utf8mb4%'");
 $linea($mal?'mal':'ok','Tablas en utf8mb4',$mal?count($mal).' sin utf8mb4: '.implode(', ',array_slice(array_column($mal,'t'),0,5)):'todas');
}
$archivos=glob(dirname(__DIR__).'/app/database/migrations/*.sql')?:[];$hechas=[];
try{foreach($db->all('SELECT version,checksum FROM agora_schema') as $r)$hechas[$r['version']]=$r['checksum'];}catch(Throwable){}
$falta=[];$cambio=[];foreach($archivos as $f){$n=basename($f);if(!isset($hechas[$n]))$falta[]=$n;elseif(!hash_equals($hechas[$n],hash('sha256',(string)file_get_contents($f))))$cambio[]=$n;}
$linea($cambio?'mal':'ok','Migraciones cambiadas',$cambio?implode(', ',$cambio).' · vuelve a subir los archivos de la versión correcta':'ninguna');
$linea($falta?'aviso':'ok','Migraciones pendientes',$falta?implode(', ',$falta).' · se aplican solas al abrir la web':'ninguna ('.count($archivos).' aplicadas)');

$uno=function(string $sql,array $a=[])use($db){try{return $db->one($sql,$a);}catch(Throwable){return null;}};
$todos=function(string $sql,array $a=[])use($db){try{return $db->all($sql,$a);}catch(Throwable){return [];}};
$seccion('Subcuentas y correo');
$cs=$todos('SELECT status,COUNT(*) n FROM agora_ghl_connections GROUP BY status');$por=array_column($cs,'n','status');
$tot=array_sum($por);$ok=(int)($por['conectada']??0);
$linea($tot===0?'aviso':($ok===$tot?'ok':'aviso'),'Subcuentas conectadas',"$ok de $tot".($ok<$tot?' · revisa las demás en Agencia':''));
foreach($todos("SELECT t.name,g.status,g.last_error,g.checked_at FROM agora_ghl_connections g JOIN agora_tenants t ON t.id=g.tenant_id WHERE g.status<>'conectada' OR (g.last_error IS NOT NULL AND g.last_error<>'') LIMIT 8") as $c)
 $linea('aviso','  '.mb_substr((string)$c['name'],0,24),(string)$c['status'].($c['last_error']?' · '.mb_substr((string)$c['last_error'],0,90):''));
$canal=$uno("SELECT data FROM agora_settings WHERE tenant_id=0 AND setting_key='correo.canal'");$cc=$canal?json_decode((string)$canal['data'],true):null;
if(!is_array($cc)||!(int)($cc['connectionId']??0))$linea('aviso','Correo de Ágora','sin subcuenta elegida: se entra solo con la clave');
else{$s=$uno('SELECT status FROM agora_ghl_connections WHERE tenant_id=?',[(int)$cc['connectionId']]);$linea(($s['status']??'')==='conectada'?'ok':'mal','Correo de Ágora','sale por la subcuenta '.(int)$cc['connectionId'].' · '.($s['status']??'no existe').(($cc['codigoAlEntrar']??true)?' · pide código al entrar':''));}
$fx=$uno("SELECT MAX(day) d FROM agora_fiscal_fx WHERE source='BCV'");
if($fx&&$fx['d']){$dias=(int)floor((time()-strtotime($fx['d'].' UTC'))/86400);$linea($dias<=3?'ok':'aviso','Tasa BCV','última del '.$fx['d'].($dias>3?" (hace $dias días: revisa servidor/bin/worker.php)":''));}else $linea('aviso','Tasa BCV','sin tasas automáticas guardadas');

$seccion('Últimas 24 horas');
$e=$uno('SELECT COUNT(*) n,SUM(status>=500) g FROM agora_error_log WHERE created_at>=UTC_TIMESTAMP()-INTERVAL 1 DAY');
if($e===null)$linea('aviso','Errores','la bitácora todavía no existe (se crea al abrir la web)');
else{$g=(int)($e['g']??0);$linea($g?'mal':'ok','Errores del servidor',$g?"$g · míralos en Agencia → Errores y reportes":'ninguno');$linea('ok','Errores en total',(string)(int)$e['n']);
 foreach($todos('SELECT codigo,COUNT(*) n FROM agora_error_log WHERE created_at>=UTC_TIMESTAMP()-INTERVAL 1 DAY GROUP BY codigo ORDER BY n DESC LIMIT 3') as $t){$x=\Agora\Core\Errores::explicar($t['codigo']);$linea('aviso','  '.$t['codigo'],$t['n'].' veces · '.($x['titulo']??''));}}
$rp=$uno("SELECT COUNT(*) n FROM agora_error_reports WHERE estado='nuevo'");if($rp)$linea((int)$rp['n']?'aviso':'ok','Reportes sin revisar',(string)(int)$rp['n']);
$cod=$uno("SELECT ip,COUNT(*) n FROM agora_access_log WHERE evento='incorrecto' AND created_at>=UTC_TIMESTAMP()-INTERVAL 1 DAY GROUP BY ip ORDER BY n DESC LIMIT 1");
$linea($cod&&(int)$cod['n']>=10?'aviso':'ok','Códigos equivocados',$cod?((int)$cod['n'].' desde '.$cod['ip'].' (la IP con más)'):'ninguno');

echo "\nResumen: ".($rojo?"$rojo en rojo, ":'').($amar?"$amar para revisar":'').(!$rojo&&!$amar?'todo en orden':'').".\n";
exit($rojo?1:0);
