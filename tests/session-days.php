<?php
declare(strict_types=1);
/** 1.10.29 · Una sesión de dispositivo dura 15 días desde que se entra: usarla no la alarga, y las de antes también se recortan. */
require __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/Actor.php';
require_once __DIR__.'/../src/DeviceSession.php';
$suffix=bin2hex(random_bytes(6));$checks=0;$tenant=null;
function sessionCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
/** Una sesión guardada como si se hubiera abierto hace $days días, con el vencimiento que tenía entonces. */
function device(int $tenant,int $user,int $daysAgo,int $expiresIn,int $rotatedAgo):string {
    $selector=DeviceSession::encode(random_bytes(18));$secret=DeviceSession::encode(random_bytes(32));
    query('INSERT INTO device_sessions(tenant_id,identity_type,actor_id,selector,verifier_hash,rotated_at,csrf,label,last_used_at,expires_at,created_at) VALUES(?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?,FROM_UNIXTIME(?))',
        [$tenant,'native',$user,$selector,hash('sha256',$secret),time()-$rotatedAgo,str_repeat('c',64),'Prueba',time()+$expiresIn,time()-$daysAgo*86400]);
    return $selector.'.'.$secret;
}
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Session test','sess-'.$suffix]);$tenant=(int)db()->lastInsertId();
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,'owner-'.$suffix.'@example.test',password_hash('x',PASSWORD_DEFAULT)]);$user=(int)db()->lastInsertId();
 sessionCheck(DeviceSession::DAYS===15,'una sesión dura 15 días');
 // Abierta hace 10 días: sigue entrando; al usarla se cambia la clave, pero el vencimiento no se mueve.
 $cookie=device($tenant,$user,10,50*86400,2*86400);
 $s=DeviceSession::resume($cookie);
 sessionCheck(is_array($s)&&($s['uid']??null)===$user,'una sesión de hace 10 días sigue abierta');
 $row=query('SELECT expires_at,UNIX_TIMESTAMP(created_at) AS c FROM device_sessions WHERE selector=?',[explode('.',$cookie)[0]])->fetch();
 sessionCheck((int)$row['expires_at']<=(int)$row['c']+15*86400+1,'usarla no la alarga: vence a los 15 días de haber entrado');
 // Abierta hace 16 días con el vencimiento viejo de 60 días (antes de 1.10.29): ya no entra.
 sessionCheck(DeviceSession::resume(device($tenant,$user,16,44*86400,3600))===null,'una sesión de hace 16 días ya no entra, aunque se abriera con el vencimiento viejo');
 // Una nueva vence a los 15 días.
 $sess=['uid'=>$user];$id=DeviceSession::start($sess,str_repeat('d',64),'Mozilla/5.0 (iPhone)');
 $new=query('SELECT expires_at FROM device_sessions WHERE id=?',[$id])->fetchColumn();
 sessionCheck(abs((int)$new-(time()+15*86400))<5,'una sesión nueva vence a los 15 días');
 $alive=['device_id'=>$id];sessionCheck(DeviceSession::refresh($alive),'y mientras tanto sigue viva');
 query('UPDATE device_sessions SET created_at=FROM_UNIXTIME(?) WHERE id=?',[time()-16*86400,$id]);
 $old=['device_id'=>$id];sessionCheck(!DeviceSession::refresh($old),'pasados los 15 días, una sesión abierta en ese momento se corta');
 echo "$checks comprobaciones".PHP_EOL;
} finally {
 if($tenant){query('DELETE FROM device_sessions WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
}
