<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,MotorMobile};
/**
 * 2.24.0 · Ágora nunca pone ni genera claves (decisión del usuario). Las personas crean la suya con un código por correo:
 *  - invitacion: persona nueva («Te invitaron a Ágora», botón «Crear mi clave»).
 *  - clave: persona que ya entra y necesita una clave nueva («Crea una clave nueva», mismo enlace). No la deja fuera: su clave
 *    actual sigue sirviendo hasta que cree la nueva.
 * El enlace abre «Crea tu clave» en la entrada (clave/olvido + clave/restablecer). Las claves de los dueños solo por terminal.
 * Nunca lanza: devuelve ['enviada','mensaje'].
 */
final class Invitaciones{
 public static function enviar(DB $db,array $config,array $u,string $tipo='invitacion'):array{
  $url=rtrim((string)($config['app_url']??''),'/').'/entrar?invitacion='.rawurlencode((string)$u['email']);
  $empresa=(string)($db->one('SELECT name FROM agora_tenants WHERE id=?',[(int)$u['local_tenant_id']])['name']??'tu empresa');
  [$asunto,$titulo,$intro,$texto]=$tipo==='clave'
   ?['Crea una clave nueva para Ágora · '.$empresa,'Crea una clave nueva','Quien administra '.$empresa.' te envió este enlace para que crees una clave nueva. Toca el botón, escribe tu correo y te enviamos un código.',"Crea una clave nueva para Ágora ($empresa): $url\n"]
   :['Te invitaron a Ágora · '.$empresa,'Te damos la bienvenida a Ágora','Te invitaron al espacio de trabajo de '.$empresa.'. Toca el botón, escribe tu correo y te enviamos un código para crear tu clave.',"Te invitaron a Ágora ($empresa). Crea tu clave aquí: $url\n"];
  try{$ok=MotorMobile::correo($db,$config,(string)$u['email'],$asunto,$titulo,$intro,null,'Si no esperabas este correo, ignóralo.',$texto,[$tipo==='clave'?'Crear una clave nueva':'Crear mi clave',$url]);}
  catch(\Throwable){return ['enviada'=>false,'mensaje'=>'No se pudo enviar el correo ahora. Inténtalo de nuevo en unos minutos.'];}
  if(!$ok)return ['enviada'=>false,'mensaje'=>'Falta configurar el Correo de Ágora (lo hace el dueño en Agencia → Correo de Ágora). Sin él no salen las invitaciones ni los enlaces para crear la clave.'];
  return ['enviada'=>true,'mensaje'=>($tipo==='clave'?'Enlace para crear una clave nueva enviado a ':'Invitación enviada a ').$u['email'].'.'];
 }
}
