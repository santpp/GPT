# Actualizar Ágora a 0.21.2

Para que los avisos lleguen con Ágora cerrada, aunque el cron del servidor no funcione.

## Qué pasaba

Tu panel lo decía: **la revisión de mensajes no ha corrido nunca en el servidor**. La entrega salía en verde porque, desde la versión anterior, también la hace la app cuando está abierta. Por eso los avisos llegaban sólo al abrir Ágora: era la propia app la que descubría el mensaje y lo enviaba. Con todo cerrado nadie miraba la cuenta.

El cron que pusiste en CyberPanel no se está ejecutando, o falla sin avisar. Ahora tienes dos caminos, y el segundo no depende de tu servidor.

## Pasos

1. Extraer `agora-update-0.21.2.zip` sobre las carpetas existentes.
2. Entrar en **Conexiones** y bajar a **Avisos en dispositivos**.
3. Elegir **una** de las dos opciones de abajo.

## Opción A (la recomendada si el cron te da guerra): revisión desde fuera

1. En Conexiones verás el recuadro «La revisión no está corriendo en el servidor». Pulsa **Crear dirección de revisión**.
2. Copia la dirección que aparece. **Sólo se muestra una vez.** Si la pierdes, crea otra y la anterior deja de funcionar.
3. Entra en **cron-job.org** (es gratis; también vale cualquier otro servicio de cron por web) y crea una tarea nueva:
   - URL: la dirección que copiaste.
   - Frecuencia: **cada minuto**.
   - Guarda.
4. En un par de minutos, en Conexiones la revisión pasará a verde: «activa, hace un momento (desde la dirección de revisión)».

Es segura: la dirección es un secreto largo y aleatorio, el servidor sólo guarda su huella, no devuelve ningún dato de la cuenta, e ignora las llamadas que lleguen con menos de 20 segundos de diferencia. Puedes cambiarla o desactivarla cuando quieras desde el mismo recuadro.

## Opción B: arreglar el cron del servidor

Para ver por qué no corre, cambia el comando del cron en CyberPanel por este, con tus rutas reales:

```
/usr/local/lsws/lsphp83/bin/php /home/nueva.agoradigital.app/agora-private/bin/sync-inbox.php >> /home/nueva.agoradigital.app/agora-private/var/cron.log 2>&1
```

Espera dos minutos y abre `agora-private/var/cron.log` con el administrador de archivos:

- **El archivo no existe:** el cron no se está ejecutando, o la ruta de PHP es incorrecta. Comprueba en el administrador de archivos que `/usr/local/lsws/lsphp83/bin/php` existe. Si tu sitio usa otra versión, será `lsphp82` o `lsphp84`.
- **Dice «Swept…» o «Already current»:** funciona. En Conexiones la revisión aparecerá en verde, «desde el servidor».
- **Contiene un error:** mándamelo tal cual.

Las dos opciones pueden convivir. Comparten candado, así que nunca hacen la revisión dos veces a la vez.

## Cómo comprobarlo

Con la revisión en verde: cierra Ágora, bloquea el teléfono y manda un WhatsApp a la cuenta desde otro número. Debe llegar el aviso en **un minuto como mucho**, con el nombre y el texto.
