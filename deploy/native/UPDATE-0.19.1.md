# Actualizar Ágora a 0.19.1

Corrige el fallo al activar los avisos y quita ruido de la pantalla.

## Pasos

1. Extraer `agora-update-0.19.1.zip` sobre las carpetas existentes.
2. Entrar. Nada más: no hay cambios de base de datos ni de configuración.

## El fallo de los avisos

Activar los avisos daba «No se ha podido completar la operación». La causa: la dirección que entrega el navegador para recibir avisos es una **URL completa**, con su host y sus barras, y yo la estaba validando como si fuera un identificador corto tipo Android. El servidor la rechazaba antes de guardarla.

Corregido: ahora la forma del dato decide el tipo de dispositivo, de modo que un navegador se registra con su dirección y un teléfono Android con su identificador, y darse de baja funciona igual en los dos casos. Comprobado de extremo a extremo contra el servidor real, incluido el alta repetida, la baja y el rechazo de una dirección que no sea `https`.

De paso, estos dos caminos ya **nunca** devuelven un error genérico con referencia: si algo falla dicen qué falta (la línea de configuración, la carpeta `var` sin permiso de escritura, o una actualización de base de datos pendiente), y el navegador muestra ese texto.

También había un error en el propio navegador que impedía completar la suscripción aunque el servidor respondiera bien. Corregido y cubierto con una prueba.

## Menos ruido en pantalla

- Fuera la línea **«Actualización automática · en vivo»**. Ahora esa línea sólo aparece cuando hay algo que decir: espera por cuota, reintento, sin conexión o pantalla oculta. Mientras todo funciona, no dice nada.
- Fuera el **«● En línea»** de la esquina. Sólo aparece si te quedas sin conexión.
- Fuera la nota «La búsqueda y los filtros se resuelven en la cuenta», que ya no hacía falta.
- El panel de la derecha deja de repetir «Selecciona una conversación» cuando no hay ninguna abierta.
- Barra superior más compacta, el selector de cantidad más discreto, y el botón de avisos con un punto de color que indica su estado (ámbar sin activar, verde activado, gris si está bloqueado o falta instalar) en lugar de texto en rojo.

## Diagnóstico

Códigos nuevos si algo va mal al preparar los avisos: `push_not_configured`, `push_key_unwritable`, `push_key_failed`, `push_schema_pending` e `invalid_device`. Cada uno lleva su explicación en castellano.
