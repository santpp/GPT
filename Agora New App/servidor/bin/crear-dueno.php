<?php
declare(strict_types=1);
/**
 * Agora New App · crear una cuenta de dueño de Ágora (solo por terminal).
 *
 *   php servidor/bin/crear-dueno.php              → pregunta nombre, correo y contraseña (la contraseña no se ve al escribirla)
 *   php servidor/bin/crear-dueno.php --listar     → muestra las cuentas de dueño que existen (sin contraseñas)
 *
 * La primera cuenta es la Master (dueña principal: hay una sola). Las siguientes son Superadmin (los demás dueños de Ágora).
 * Desde la web no se pueden crear ni ascender cuentas de dueño.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
use Agora\Core\{DB,Installer,Http,HttpError};
$db=new DB($config['db']);
try{Installer::migrate($db);}catch(Throwable $e){fwrite(STDERR,"No se pudo preparar la base de datos: ".$e->getMessage()."\n");exit(1);}

if(in_array('--listar',$argv,true)){
 $rows=$db->all("SELECT name,email,role,active FROM agora_users WHERE role IN ('MASTER','SUPERADMIN') ORDER BY role='MASTER' DESC,id");
 if(!$rows){echo "Todavía no hay cuentas de dueño.\n";exit(0);}
 foreach($rows as $r)printf("%-11s %-32s %s%s\n",$r['role'],$r['email'],$r['name'],$r['active']?'':' (desactivada)');
 exit(0);
}

$pregunta=function(string $texto,bool $oculta=false):string{
 fwrite(STDOUT,$texto);
 if($oculta&&stream_isatty(STDIN))system('stty -echo 2>/dev/null');
 $v=fgets(STDIN);
 if($oculta&&stream_isatty(STDIN)){system('stty echo 2>/dev/null');fwrite(STDOUT,"\n");}
 return trim((string)$v);
};

$hayMaster=(bool)$db->one("SELECT id FROM agora_users WHERE role='MASTER'");
$rol=$hayMaster?'SUPERADMIN':'MASTER';
echo $hayMaster?"Ya existe la cuenta Master. Vas a crear un Superadmin (otro dueño de Ágora).\n":"Vas a crear la cuenta Master (la dueña principal de Ágora).\n";

$nombre=$pregunta('Nombre y apellido: ');
$correo=strtolower($pregunta('Correo: '));
$clave=$pregunta('Contraseña (mínimo 12 caracteres, no se ve al escribir): ',true);
$repite=$pregunta('Repite la contraseña: ',true);
if($clave!==$repite){fwrite(STDERR,"Las contraseñas no coinciden. No se creó nada.\n");exit(1);}

try{
 if($rol==='MASTER'){Installer::owner($db,['nombre'=>$nombre,'correo'=>$correo,'clave'=>$clave]);}
 else{
  $db->transaction(function()use($db,$nombre,$correo,$clave){
   $b=['nombre'=>$nombre,'correo'=>$correo,'clave'=>$clave];
   $email=Http::email($b);
   if($db->one('SELECT id FROM agora_users WHERE email=?',[$email]))Http::fail('Ese correo ya tiene una cuenta.',409);
   $db->run("INSERT INTO agora_users(name,email,password_hash,role) VALUES(?,?,?,'SUPERADMIN')",[Http::text($b,'nombre'),$email,password_hash(Http::password($clave),PASSWORD_DEFAULT)]);
  });
 }
}catch(HttpError $e){fwrite(STDERR,$e->getMessage()." No se creó nada.\n");exit(1);}
echo ($rol==='MASTER'?'Cuenta Master':'Cuenta Superadmin')." creada para $correo. Ya puedes entrar en la web con ese correo y su contraseña.\n";
if($rol==='SUPERADMIN')echo "Recuerda: un Superadmin solo ve las subcuentas que la Master le asigne (Agencia → Gestión de usuarios).\n";
