# Actualizar Ágora a 0.21.0

Lo invertido en Meta llega solo al tablero, los avisos dicen quién escribe y qué, y **Conexiones te dice en qué punto se corta un aviso que no llega**.

## Pasos

1. Extraer `agora-update-0.21.0.zip` sobre las carpetas existentes.
2. **Permiso nuevo para la publicidad:** en la integración privada de la subcuenta, añade el permiso **`adPublishing.readonly`** (lectura de anuncios). Sin él todo funciona igual y el tablero dice que falta ese permiso.
3. En el teléfono, cierra Ágora del todo y vuelve a abrirla. El pie debe decir **v0.21.0**.

La base de datos se actualiza sola.

## Avisos: qué cambia y cómo encontrar el fallo

Encontré dos fallos míos:

- **El aviso no decía quién escribía.** El teléfono recibía el aviso pero lo mostraba como «Ágora / Tienes un mensaje nuevo», porque leía el bloque equivocado. Corregido, y comprobado entregando un aviso real al teléfono simulado y leyendo lo que enseña.
- **Algunos mensajes no generaban aviso.** Si en otro dispositivo tenías la bandeja abierta buscando o filtrando, esa lectura podía registrar el mensaje nuevo antes que la revisión automática, y entonces ésta ya no lo veía como nuevo. Ahora cualquier lectura que descubra un mensaje deja preparado su aviso.

Y para no tener que adivinar: en **Conexiones**, al final, la sección de avisos muestra la cadena entera, en verde lo que funciona y en ámbar lo que no:

1. **Revisión de mensajes nuevos** — cuándo corrió por última vez el cron que mira la cuenta. Si dice que no consta o que fue hace mucho, el cron de `sync-inbox.php` no está corriendo.
2. **Entrega de avisos** — cuándo corrió el cron que envía. Si dice que no encuentra las claves, el cron no puede leer la carpeta privada; si dice que no consta, el cron de `send-push.php` no está corriendo.
3. **Últimos 7 días** — avisos entregados, pendientes y fallidos, y el último error si lo hay.

Debajo está el botón **Enviar un aviso de prueba a mis dispositivos**. Lo manda en el acto, sin pasar por los crones, a los dispositivos del usuario con el que has entrado:

- Si **llega**, el teléfono, el navegador y el envío funcionan: si los mensajes reales no avisan, el fallo está en los crones, y la cadena de arriba dirá cuál.
- Si dice **«enviado» y no llega**, el aviso salió del servidor y el teléfono lo retuvo: revisa en los ajustes del sistema que las notificaciones de Ágora estén permitidas y quítale el ahorro de batería.
- Si dice otra cosa, el texto explica qué falla.

Importante: el botón usa **los dispositivos del usuario con el que entraste**. Activa los avisos en el teléfono con ese mismo usuario.

## El número en el icono

Cada aviso lleva cuántos mensajes tienes sin leer, y la app abierta lo mantiene al día. En **iPhone** (instalada) y en el **escritorio** aparece el número sobre el icono. **Android no lo admite** para aplicaciones web: allí el icono muestra el punto del sistema cuando hay un aviso sin leer, y eso depende de que el aviso llegue.

## Publicidad desde Meta

El tablero ya no pide la publicidad a mano: la lee de la **cuenta publicitaria de Meta conectada a la subcuenta**, para el mes elegido. Si esa cuenta no informa gasto, prueba con las campañas creadas en el gestor de anuncios. Debajo de la cifra se dice de dónde viene.

En *Cifras del mes* el campo de publicidad pasa a ser **«Otros gastos de publicidad (fuera de Meta)»**, para lo que no pasa por ella. Se **suma** a lo de Meta. Si habías escrito a mano lo de Meta en la versión anterior, ese valor se ignora para no contarlo dos veces.

Una advertencia honesta: la respuesta de ese informe **no tiene formato publicado**. La leo sin suponer su forma y, si no encuentro un gasto claro, no muestro cifra en lugar de inventarla. Cuando lo actives, compara la cifra con la de tu administrador de anuncios del mes y dime si coincide.
