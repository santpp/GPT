# Actualizar Ágora a 0.21.1

Con esta versión deberían llegar los avisos de los mensajes reales. Tu captura dejaba ver tres fallos míos.

## Pasos

1. Extraer `agora-update-0.21.1.zip` sobre las carpetas existentes.
2. En el teléfono, **cierra Ágora del todo** (deslízala fuera de las apps recientes) y vuelve a abrirla **dos veces**: la primera descarga la versión nueva y la segunda la pone en marcha. El pie debe decir **v0.21.1**.
3. En **Conexiones**, pulsa **Enviar un aviso de prueba**. Ahora tiene que decir **«Aviso de prueba: si lo ves, este dispositivo recibe los avisos»**. Si sigue diciendo «Tienes un mensaje nuevo», el teléfono todavía no tiene la versión nueva: repite el paso 2.

Sin cambios de base de datos que tengas que hacer a mano.

## Qué pasaba

**1. Tu teléfono seguía con el service worker antiguo.** Por eso el aviso de prueba decía «Tienes un mensaje nuevo» y no «Aviso de prueba». Antes de cambiar de versión, la app espera a que no estés escribiendo, para no perder nada. Pero contaba como «escribiendo» cualquier campo con algo dentro, también los ocultos: el formulario de entrada, que después de entrar sigue guardando tu correo y tu contraseña, y el selector de mes del tablero. Para la app siempre estabas escribiendo, así que la versión nueva no llegaba nunca. Ahora sólo cuenta lo que ves y has cambiado: un mensaje a medias sí retiene la actualización, el resto no.

**2. Los avisos dependían de un segundo cron que no corría.** Tu panel lo decía: el cron de entrega no había corrido nunca, y había 5 avisos esperando. Ahora **el cron de revisión, el que ya tienes funcionando, también entrega**, en la misma pasada. Basta con ese cron. El de `send-push.php` puedes dejarlo o quitarlo: si están los dos, nunca envían el mismo aviso dos veces. Además, si hay una pantalla de Ágora abierta en cualquier dispositivo, los avisos salen en el acto.

**3. Un rechazo del servicio borraba el teléfono.** Si el servicio de avisos rechazaba un envío por cualquier motivo, Ágora daba el teléfono por desaparecido y lo quitaba de la lista. Ahora sólo lo quita cuando el servicio dice expresamente que esa suscripción ya no existe.

## Cómo comprobarlo

1. Haz el paso 3 de arriba.
2. Cierra Ágora y bloquea el teléfono.
3. Manda un WhatsApp a la cuenta desde otro número.
4. Tiene que llegar en **un minuto como mucho**, con el nombre del contacto y el texto del mensaje.

Si no llega, vuelve a **Conexiones** y mándame una captura de la sección de avisos. Ahora la revisión debe aparecer en verde («activa, hace X min»), y si algún aviso falla, al lado sale el motivo.

## Un detalle sobre el cron

La revisión de mensajes tiene que estar en verde. Si en tu panel sigue diciendo que **el cron de revisión no se está ejecutando**, el cron que pusiste en CyberPanel no está llamando a `sync-inbox.php`. Comprueba en *Cron Jobs* que el comando sea exactamente:

```
/usr/local/lsws/lsphp83/bin/php /home/nueva.agoradigital.app/agora-private/bin/sync-inbox.php
```

con tus rutas reales.
