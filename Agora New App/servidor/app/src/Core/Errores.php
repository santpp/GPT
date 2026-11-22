<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Core;

/**
 * 2.19.0 · Diccionario de errores (pedido del usuario): cada error que ve una persona lleva un código y cada código tiene una razón
 * y qué hacer. Forma del código: AG-ÁREA-NNN[letra].
 *  - ÁREA: la parte de Ágora donde pasó (ver AREAS), según la ruta de la petición.
 *  - NNN: el tipo de fallo (como los códigos de la web: 401 sesión, 403 permiso, 409 conflicto, 500 interno…), con su razón en TIPOS.
 *  - letra: los errores concretos más importantes tienen su propia razón (ESPECIFICOS), por ejemplo AG-ACC-401A «código equivocado».
 * La pantalla agrega los suyos (área NAV para errores de la pantalla, RED para la conexión). Todo se lista en Agencia → Errores.
 * Nunca se nombra al proveedor de la cuenta.
 */
final class Errores {
 public const AREAS=[
  'ACC'=>'Acceso: entrar, código por correo, clave y sesión',
  'AGE'=>'Agencia: subcuentas, usuarios de la agencia, correo de Ágora, consumos',
  'CTA'=>'La cuenta conectada: bandeja, contactos, oportunidades, tareas, redes',
  'VEN'=>'Ventas y venta ganada',
  'FAC'=>'Facturación: cobros, facturas, libro de ventas, datos fiscales, tasas',
  'GES'=>'Gestión: Dashboard, cifras del mes, costos, reporte mensual, equipo',
  'WID'=>'Widgets: tableros, fórmulas, variables, plantillas, avisos',
  'USR'=>'Usuarios de la subcuenta y perfil',
  'CON'=>'Contratos y firmas',
  'SIS'=>'Sistema: instalación, base de datos, rutas',
  'NAV'=>'La pantalla (el navegador)',
  'RED'=>'La conexión entre el navegador y el servidor',
 ];
 /** Razón y qué hacer de cada tipo de fallo. */
 public const TIPOS=[
  '000'=>['Sin conexión','El navegador no pudo llegar al servidor: no hay internet o el servidor no respondió.','Revisa la conexión y vuelve a intentarlo. Si a todos les pasa, el servidor puede estar caído.'],
  '400'=>['Petición incompleta','Al servidor le llegó una petición mal formada o incompleta.','Recarga la página y repite. Si sigue, repórtalo.'],
  '401'=>['Sesión o código no válidos','La sesión venció, la clave o el código no coinciden, o hay que volver a entrar.','Vuelve a entrar. Si es un código, pide uno nuevo.'],
  '403'=>['Sin permiso','Tu rol no permite esta acción o el dato es de otra subcuenta.','Pide a quien administra tu subcuenta que revise tu rol.'],
  '404'=>['No existe','Lo que se buscó no existe o ya se borró.','Recarga la página. Si venías de un enlace, puede estar viejo.'],
  '405'=>['Acción no permitida','La pantalla pidió algo de una forma que el servidor no acepta.','Recarga la página. Si sigue, repórtalo (suele ser un error nuestro).'],
  '408'=>['Tardó demasiado','El servidor o la cuenta tardaron más de lo esperado en responder.','Vuelve a intentarlo en un momento.'],
  '409'=>['Conflicto','Otra persona cambió el mismo dato, ya existe, o depende de algo que falta (por ejemplo, la subcuenta no está conectada).','Recarga para ver la versión actual y repite el cambio.'],
  '410'=>['Vencido','El enlace o la operación ya no están disponibles.','Pide un enlace nuevo.'],
  '413'=>['Demasiado grande','El archivo o los datos enviados pasan del tamaño permitido.','Usa un archivo más pequeño o divide la carga.'],
  '422'=>['Dato no válido','Un campo tiene un valor que no se acepta (fecha, monto, moneda, estado…).','Revisa el formulario: el mensaje dice qué campo.'],
  '424'=>['Falta algo previo','La acción necesita algo que todavía no está listo (datos fiscales, tasa, conexión…).','Completa lo que indica el mensaje y repite.'],
  '429'=>['Demasiados intentos','Se hicieron muchas peticiones seguidas y el servidor (o la cuenta) pidió esperar.','Espera unos minutos y vuelve a intentarlo.'],
  '500'=>['Error interno','Algo falló dentro del servidor. Queda guardado con su referencia.','Repórtalo con la referencia: con ella se encuentra el detalle exacto.'],
  '501'=>['No disponible todavía','Esa función todavía no está activa en esta instalación.','Nada que hacer por ahora.'],
  '502'=>['La cuenta respondió mal','La cuenta conectada devolvió una respuesta que no se entiende.','Vuelve a intentarlo. Si sigue, revisa la conexión de la subcuenta.'],
  '503'=>['Servicio no disponible','Un servicio externo (la cuenta o el correo) no respondió.','Vuelve a intentarlo en unos minutos.'],
  '900'=>['Error en la pantalla','La pantalla tuvo un fallo de programación al dibujar o al responder a un toque.','Recarga la página. Repórtalo para que se corrija.'],
 ];
 /** Errores concretos: [área|null, tipo, patrón del mensaje, letra, título, razón, qué hacer]. */
 public const ESPECIFICOS=[
  ['ACC','401','/^Correo o contraseña incorrectos/u','A','Clave incorrecta','El correo no existe, la clave no coincide o el usuario está suspendido.','Revisa el correo y la clave, o usa «¿Olvidaste tu clave?».'],
  ['ACC','401','/^El código no coincide/u','B','Código equivocado','El código de 6 dígitos no es el último que se envió.','Revisa el último correo. Después de 5 intentos hay que pedir otro.'],
  ['ACC','401','/^El código no es válido o ya venció/u','C','Código vencido o agotado','El código pasó sus 10 minutos, ya se usó o se equivocó 5 veces.','Pide un código nuevo.'],
  ['ACC','401','/^Vuelve a escribir tu clave/u','D','Pedido de código vencido','Pasaron más de 30 minutos desde que se escribió la clave.','Vuelve a escribir tu clave.'],
  ['ACC','403','/CSRF|Actualiza la página/u','E','Página vieja','La página abierta tiene una protección vencida (estuvo abierta mucho tiempo o se abrió en otra pestaña).','Recarga la página.'],
  ['ACC','403','/^La clave de una cuenta de dueño/u','F','Clave de dueño','La clave de un dueño solo se cambia desde la terminal del servidor.','Usa servidor/bin/cambiar-clave.php en el servidor.'],
  ['ACC','429','/^Espera un minuto/u','G','Espera para otro código','Se pidió otro código antes de un minuto.','Espera un minuto.'],
  [null,'429','/^Demasiados intentos/u','H','Freno por intentos','La misma IP o el mismo correo hizo demasiados intentos en 15 minutos.','Espera 15 minutos.'],
  ['ACC','503','/^No pudimos enviar el código/u','I','El correo no salió','La subcuenta elegida para el correo de Ágora no pudo enviar el mensaje.','Revisa en Agencia → Correo de Ágora que la subcuenta siga conectada y pruébalo.'],
  [null,'409','/^Esta subcuenta todavía no está conectada/u','J','Subcuenta sin conectar','La subcuenta no tiene su conexión con la cuenta lista.','En Agencia, abre la subcuenta, revisa el token y pruébalo.'],
  [null,'429','/^La cuenta pidió esperar/u','K','La cuenta pidió esperar','La cuenta conectada limitó las consultas por un momento.','Espera unos segundos y repite.'],
  [null,'403','/no está enlazado con la cuenta/u','L','Usuario sin enlazar','Tu usuario de Ágora no está unido a tu usuario de la cuenta.','Pide que te enlacen en Usuarios.'],
  [null,'403','/^Selecciona una subcuenta/u','M','Falta elegir subcuenta','Se pidió algo de una subcuenta sin tener una abierta.','Elige la subcuenta arriba y repite.'],
  [null,'409','/^Esta oportunidad ya tiene una venta/u','N','Venta repetida','Esa oportunidad ya tiene su venta registrada.','Edítala desde Ventas.'],
  [null,'409','/versión|Versión|otra persona/u','O','Cambio de otra persona','Alguien guardó el mismo dato mientras lo editabas.','Recarga y repite tu cambio.'],
  [null,'409','/^El registro ya existe o está relacionado/u','P','Dato repetido o en uso','Ya existe un registro igual, o el que se quiere borrar lo usan otros datos.','Revisa si ya estaba creado.'],
  ['SIS','500','/^La migración aplicada cambió/u','Q','Migración cambiada','Un archivo de la base de datos ya instalado fue modificado; el servidor no arranca para no dañar datos.','Vuelve a subir los archivos de la versión correcta.'],
  [null,'404','/^Ruta (no (disponible|encontrada)|o método no implementado)/u','R','Ruta desconocida','La pantalla pidió una dirección que el servidor no tiene (versiones mezcladas).','Recarga la página. Si sigue, sube otra vez la versión completa.'],
  [null,'403','/^Tu rol/u','S','Rol sin permiso','Tu rol no tiene esta función.','Pide a la administración que revise tu rol.'],
 ];

 /** Área según la ruta de la petición. */
 public static function area(string $ruta):string{
  $r=strtolower(trim($ruta,'/'));
  $es=static fn(string ...$p):bool=>array_reduce($p,fn($c,$x)=>$c||$r===$x||str_starts_with($r,$x.'/')||str_starts_with($r,$x),false);
  if($es('login','clave/','session','logout','yo','me'))return 'ACC';
  if($es('agency/','admin/consumption','admin/access-channel','admin/integrations'))return 'AGE';
  if($es('workspace/fiscal','workspace/facturas','workspace/libro-ventas','workspace/cobros','workspace/tasas','facturas','cobros'))return 'FAC';
  if($es('workspace/metricas','workspace/formulas','workspace/variables','workspace/tableros','workspace/alertas','workspace/plantillas'))return 'WID';
  if($es('workspace/ventas','ventas','workspace/ganadas','workspace/won'))return 'VEN';
  if($es('workspace/usuarios','usuarios','perfil','workspace/perfil','workspace/permisos','workspace/enlaces'))return 'USR';
  if($es('contracts','contratos','sign','registro','workspace/contratos'))return 'CON';
  // 2.19.2: todas las rutas que hablan con la cuenta (las atiende el motor).
  if($es('ghl/','workspace/contact','workspace/oportunidad','workspace/opportunit','workspace/tareas','workspace/bandeja','workspace/conversation','workspace/inbox','workspace/messages','workspace/send','workspace/assign','workspace/read','workspace/record','workspace/sync','workspace/tags','workspace/templates','workspace/activity','workspace/pipelines','workspace/embudos','workspace/redes','workspace/campanas','workspace/self','workspace/connections','conversations','contacts','opportunities','inbox','tasks'))return 'CTA';
  if($es('workspace/','admin/'))return 'GES';
  return 'SIS';
 }
 /** Código y razón de un error. */
 public static function codigo(string $ruta,int $status,string $mensaje):array{
  $tipo=isset(self::TIPOS[(string)$status])?(string)$status:($status>=500?'500':($status>=400?'400':'500'));
  $area=self::area($ruta);
  foreach(self::ESPECIFICOS as [$a,$t,$re,$letra,$titulo,$razon,$hacer]){
   if($t===$tipo&&($a===null||$a===$area)&&preg_match($re,$mensaje))return ['codigo'=>"AG-$area-$tipo$letra",'titulo'=>$titulo,'razon'=>$razon,'hacer'=>$hacer];
  }
  [$titulo,$razon,$hacer]=self::TIPOS[$tipo];
  return ['codigo'=>"AG-$area-$tipo",'titulo'=>$titulo,'razon'=>$razon,'hacer'=>$hacer];
 }
 /** Busca la razón de un código ya armado (para los reportes y la pantalla de Agencia). */
 public static function explicar(string $codigo):?array{
  if(!preg_match('/^AG-([A-Z]{3})-(\d{3})([A-Z]?)$/D',$codigo,$m)||!isset(self::AREAS[$m[1]]))return null;
  if($m[3]!==''){foreach(self::ESPECIFICOS as [$a,$t,,$letra,$titulo,$razon,$hacer])if($t===$m[2]&&$letra===$m[3]&&($a===null||$a===$m[1]))return ['codigo'=>$codigo,'area'=>self::AREAS[$m[1]],'titulo'=>$titulo,'razon'=>$razon,'hacer'=>$hacer];}
  if(!isset(self::TIPOS[$m[2]]))return null;
  [$titulo,$razon,$hacer]=self::TIPOS[$m[2]];
  return ['codigo'=>$codigo,'area'=>self::AREAS[$m[1]],'titulo'=>$titulo,'razon'=>$razon,'hacer'=>$hacer];
 }
 /** El diccionario completo, para Agencia → Errores. */
 public static function diccionario():array{
  $tipos=[];foreach(self::TIPOS as $k=>[$t,$r,$h])$tipos[]=['codigo'=>"AG-ÁREA-$k",'titulo'=>$t,'razon'=>$r,'hacer'=>$h];
  $esp=[];foreach(self::ESPECIFICOS as [$a,$t,,$l,$ti,$r,$h])$esp[]=['codigo'=>'AG-'.($a??'ÁREA').'-'.$t.$l,'titulo'=>$ti,'razon'=>$r,'hacer'=>$h];
  return ['areas'=>array_map(fn($k,$v)=>['area'=>$k,'nombre'=>$v],array_keys(self::AREAS),self::AREAS),'tipos'=>$tipos,'especificos'=>$esp];
 }
}
