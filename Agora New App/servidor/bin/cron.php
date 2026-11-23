<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
/**
 * 2.20.0 · El único cron de Ágora (pedido del usuario: «podemos usar el mismo cron que tiene Mobile»). Una línea en CyberPanel,
 * cada minuto, con el usuario del sitio:
 *
 *   * * * * * php /home/TU-DOMINIO/servidor/bin/cron.php >/dev/null 2>&1
 *
 * En cada minuto:
 *  1. worker.php: tareas pendientes, tasa BCV y limpieza (sesiones, límites, idempotencia).
 *  2. La pasada de la Bandeja de Mobile (Sweep del motor): trae los mensajes nuevos de cada subcuenta conectada y el equipo de la cuenta.
 *     Se repite cada 10 s durante 50 s, así un mensaje se nota en segundos y no en un minuto.
 *  3. Después de cada pasada, las notificaciones de mensajes nuevos (Services/Notificaciones::generar).
 * Deja la hora de la última corrida en agora_settings(0,'cron.ultimo'): el diagnóstico avisa si el cron no corre.
 * Dos cron a la vez no se pisan (bloqueo). «--once» hace una sola pasada y dice qué hizo, para probarlo a mano.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
use Agora\Core\{DB,MotorMobile,Installer};use Agora\Services\Notificaciones;
$db=new DB($config['db']);
$once=in_array('--once',$argv,true);
$lock='agora_cron_'.substr(hash('sha256',(string)($config['db']['name']??'agora')),0,30);
if(!(int)($db->one('SELECT GET_LOCK(?,0) ok',[$lock])['ok']??0)){if($once)echo "Ya hay otro cron corriendo.\n";exit(0);}
$inicio=microtime(true);$hecho=['worker'=>null,'pasadas'=>0,'mensajes'=>0,'errores'=>[]];
try{
 try{Installer::migrate($db);}catch(Throwable $e){$hecho['errores'][]='migraciones: '.get_class($e);}
 // 1. worker (tiene su propio bloqueo)
 $out=[];$rc=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/worker.php').' 2>&1',$out,$rc);$hecho['worker']=$rc===0?'bien':'código '.$rc;
 // 2 y 3. Bandeja de Mobile + notificaciones
 $hay=(bool)$db->one("SELECT 1 ok FROM agora_ghl_connections WHERE status='conectada' LIMIT 1");
 if($hay){
  MotorMobile::instalar($db,$config);MotorMobile::arrancar($config);
  require_once dirname(__DIR__).'/motor/src/Sweep.php';
  $vueltas=$once?1:5;
  for($i=0;$i<$vueltas;$i++){
   $t=microtime(true);
   try{\Sweep::run('cron-erp');$hecho['pasadas']++;}catch(Throwable $e){$hecho['errores'][]='bandeja: '.get_class($e);}
   $hecho['mensajes']+=Notificaciones::generar($db)['mensajes'];
   if($i<$vueltas-1){$espera=10-(microtime(true)-$t);if($espera>0)usleep((int)($espera*1e6));if(microtime(true)-$inicio>52)break;}
  }
 } else Notificaciones::generar($db);
}catch(Throwable $e){$hecho['errores'][]=get_class($e).': '.mb_substr($e->getMessage(),0,160);error_log('Agora cron: '.get_class($e));}
finally{
 try{$db->saveSetting(0,'cron.ultimo',['at'=>gmdate('c'),'segundos'=>round(microtime(true)-$inicio,1)]+$hecho);}catch(Throwable){}
 $db->one('SELECT RELEASE_LOCK(?) ok',[$lock]);
}
if($once)printf("Cron: worker %s · %d pasada(s) de la Bandeja · %d notificación(es) de mensajes%s\n",$hecho['worker'],$hecho['pasadas'],$hecho['mensajes'],$hecho['errores']?' · problemas: '.implode('; ',$hecho['errores']):'');
