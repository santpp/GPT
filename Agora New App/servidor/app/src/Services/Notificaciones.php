<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\DB;

/**
 * 2.20.0 · Notificaciones dentro de Ágora (pedido del usuario: «notificaciones en tiempo real»). No van al teléfono (eso sigue en pausa):
 * aparecen en la campana de la barra de arriba y como aviso corto, en cuanto la pantalla consulta (cada 20 s, y al volver a la pestaña).
 *  - Al momento: convenio firmado, factura abierta por el cliente, reporte de un problema, intentos de código sospechosos.
 *  - Desde el cron (bin/cron.php), después de cada pasada de la Bandeja: mensajes nuevos de clientes.
 * Cada aviso es de una persona y sale una sola vez (clave única por persona). Se guardan 60 días.
 */
final class Notificaciones {
 public static function crear(DB $db,int $userId,?int $tenant,string $tipo,string $titulo,string $texto='',string $enlace='',string $clave=''):void{
  try{
   $db->run('INSERT IGNORE INTO agora_notifications(user_id,tenant_id,tipo,titulo,texto,enlace,clave) VALUES(?,?,?,?,?,?,?)',
    [$userId,$tenant,mb_substr($tipo,0,30),mb_substr($titulo,0,160),mb_substr($texto,0,300),mb_substr($enlace,0,200),mb_substr($clave!==''?$clave:$tipo.':'.bin2hex(random_bytes(6)),0,120)]);
  }catch(\Throwable){}
 }
 /** A todas las personas activas con esos roles (de la subcuenta, o los dueños si $tenant es null). */
 public static function aRoles(DB $db,?int $tenant,array $roles,string $tipo,string $titulo,string $texto='',string $enlace='',string $clave=''):void{
  if(!$roles)return;$ph=implode(',',array_fill(0,count($roles),'?'));
  try{
   $u=$tenant===null?$db->all("SELECT id FROM agora_users WHERE active=1 AND role IN ($ph)",$roles)
    :$db->all("SELECT id FROM agora_users WHERE active=1 AND role IN ($ph) AND local_tenant_id=?",array_merge($roles,[$tenant]));
   foreach($u as $r)self::crear($db,(int)$r['id'],$tenant,$tipo,$titulo,$texto,$enlace,$clave);
  }catch(\Throwable){}
 }
 public static function aDuenos(DB $db,string $tipo,string $titulo,string $texto='',string $enlace='',string $clave=''):void{self::aRoles($db,null,['MASTER','SUPERADMIN'],$tipo,$titulo,$texto,$enlace,$clave);}

 /**
  * Lo que hace el cron después de cada pasada: mensajes nuevos de clientes en cada subcuenta conectada y limpieza.
  * Mensaje nuevo = conversación con mensajes sin leer cuyo último mensaje es del cliente y llegó después de la última revisión.
  * Le llega a quien la tiene asignada (su usuario de Ágora enlazado); si nadie, a administración y supervisión de la subcuenta.
  */
 public static function generar(DB $db):array{
  $hechos=0;
  try{
   foreach($db->all("SELECT tenant_id FROM agora_ghl_connections WHERE status='conectada'") as $c){
    $t=(int)$c['tenant_id'];$clave='notif.mensajes';
    $desde=(int)($db->setting($t,$clave,['ms'=>0])['ms']??0);
    if($desde===0){$db->saveSetting($t,$clave,['ms'=>(int)(microtime(true)*1000)]);continue;} // la primera vez solo marca desde cuándo (no avisa de lo viejo)
    try{$rows=$db->all('SELECT conversation_id,payload FROM conversation_index WHERE connection_id=? AND seen_at>=FROM_UNIXTIME(?) LIMIT 300',[$t,intdiv($desde,1000)-120]);}catch(\PDOException){continue;}
    $max=$desde;$usuarios=null;$enlaces=null;
    foreach($rows as $r){
     $p=json_decode((string)$r['payload'],true);if(!is_array($p))continue;
     $ms=is_numeric($p['lastMessageDate']??null)?(int)$p['lastMessageDate']:(int)(strtotime((string)($p['lastMessageDate']??''))*1000);
     if($ms<=$desde||(int)($p['unreadCount']??0)<=0||($p['lastMessageDirection']??'')==='outbound')continue;
     $max=max($max,$ms);
     if($usuarios===null){$usuarios=$db->all('SELECT id,email,role FROM agora_users WHERE active=1 AND local_tenant_id=?',[$t]);$enlaces=array_filter((new UserLinks($db))->forUsers($t,$usuarios));}
     $para=[];$asig=(string)($p['assignedTo']??'');
     if($asig!=='')foreach($enlaces as $uid=>$e)if($e['id']===$asig)$para[]=(int)$uid;
     if(!$para)foreach($usuarios as $u)if(in_array($u['role'],['ADMIN','SUPERVISOR'],true))$para[]=(int)$u['id'];
     $quien=trim((string)($p['contactName']??$p['fullName']??''))?:'Un cliente';
     $texto=mb_substr(trim((string)($p['lastMessageBody']??'')),0,140);
     foreach($para as $uid){self::crear($db,$uid,$t,'mensaje','Nuevo mensaje de '.$quien,$texto,'/bandeja?c='.rawurlencode((string)$r['conversation_id']),'msg:'.$r['conversation_id'].':'.$ms);$hechos++;}
    }
    if($max>$desde)$db->saveSetting($t,$clave,['ms'=>$max]);
   }
  }catch(\Throwable $e){error_log('Agora notificaciones: '.get_class($e));}
  try{if(random_int(1,30)===1)$db->run('DELETE FROM agora_notifications WHERE created_at<UTC_TIMESTAMP()-INTERVAL 60 DAY');}catch(\Throwable){}
  return ['mensajes'=>$hechos];
 }
}
