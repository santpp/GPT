<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
/**
 * Agora New App · poner una contraseña nueva a una cuenta y quitar el bloqueo por intentos (solo por terminal).
 *
 *   php servidor/bin/cambiar-clave.php correo@ejemplo.com   → pide la contraseña nueva dos veces (no se ve al escribirla)
 *   php servidor/bin/cambiar-clave.php --desbloquear         → solo borra los bloqueos por «Demasiados intentos»
 *
 * Sirve para cualquier cuenta (también la Master). Cierra las sesiones abiertas de esa cuenta y la deja activa.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
use Agora\Core\{DB,Installer,Http,HttpError};
$db=new DB($config['db']);
try{Installer::migrate($db);}catch(Throwable $e){fwrite(STDERR,"No se pudo preparar la base de datos: ".$e->getMessage()."\n");exit(1);}

$desbloquear=function()use($db):void{$db->run('DELETE FROM agora_rate_limits');};
if(in_array('--desbloquear',$argv,true)){$desbloquear();echo "Listo: se quitaron los bloqueos por intentos. Ya se puede volver a probar la entrada.\n";exit(0);}

$correo=strtolower(trim((string)($argv[1]??'')));
if($correo===''||str_starts_with($correo,'-')){fwrite(STDERR,"Uso: php servidor/bin/cambiar-clave.php correo@ejemplo.com\n     php servidor/bin/cambiar-clave.php --desbloquear\n");exit(1);}
$u=$db->one('SELECT id,name,email,role,active FROM agora_users WHERE email=?',[$correo]);
if(!$u){fwrite(STDERR,"No hay ninguna cuenta con el correo $correo.\n");$hay=$db->all("SELECT email,role FROM agora_users WHERE role IN ('MASTER','SUPERADMIN') ORDER BY id");if($hay){fwrite(STDERR,"Cuentas de dueño que existen:\n");foreach($hay as $h)fwrite(STDERR,"  {$h['role']}  {$h['email']}\n");}exit(1);}

$pregunta=function(string $texto):string{
 fwrite(STDOUT,$texto);
 if(stream_isatty(STDIN))system('stty -echo 2>/dev/null');
 $v=fgets(STDIN);
 if(stream_isatty(STDIN)){system('stty echo 2>/dev/null');fwrite(STDOUT,"\n");}
 return rtrim((string)$v,"\r\n");
};
echo "Cuenta: {$u['name']} <{$u['email']}> · {$u['role']}".($u['active']?'':' (estaba desactivada)')."\n";
$clave=$pregunta('Contraseña nueva (mínimo 12 caracteres, no se ve al escribir): ');
$repite=$pregunta('Repite la contraseña: ');
if($clave!==$repite){fwrite(STDERR,"Las contraseñas no coinciden. No se cambió nada.\n");exit(1);}
try{$hash=password_hash(Http::password($clave),PASSWORD_DEFAULT);}catch(HttpError $e){fwrite(STDERR,$e->getMessage()." No se cambió nada.\n");exit(1);}
$db->transaction(function()use($db,$u,$hash){
 $db->run('UPDATE agora_users SET password_hash=?,active=1,auth_version=auth_version+1 WHERE id=?',[$hash,$u['id']]);
 $db->run('DELETE FROM agora_sessions WHERE user_id=?',[$u['id']]);
});
$desbloquear();
echo "Listo: {$u['email']} ya puede entrar con la contraseña nueva. Se cerraron sus sesiones abiertas y se quitaron los bloqueos por intentos.\n";
