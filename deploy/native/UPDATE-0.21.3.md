# Actualizar Ágora a 0.21.3

Los avisos llegan en segundos, no en minutos, y dicen quién escribe, por qué canal y qué.

## Pasos

1. Extraer `agora-update-0.21.3.zip` sobre las carpetas existentes.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla.

No hay que tocar la tarea de cron-job.org.

## Por qué tardaba

La revisión leía la cuenta según el «intervalo» de la conexión, que en la tuya es **120 segundos** (lo ves en Conexiones). Ese ajuste venía de la sincronización antigua. Aunque cron-job.org llamara cada minuto, la cuenta sólo se miraba cada dos.

Ahora:

- La cuenta se lee **cada 10 segundos**, sea cual sea ese ajuste. Cabe de sobra en la cuota.
- Cada llamada de cron-job.org responde enseguida y **se queda revisando cada 10 segundos** hasta la siguiente llamada.

Resultado esperado con la app cerrada: el aviso en **10 a 15 segundos**.

## Qué dice el aviso

- **Título:** quién escribe y por qué canal, por ejemplo «Raissa Moreno · WhatsApp».
- **Texto:** lo que escribió y, si hay varios sin leer, cuántos.
- Si el contacto no tiene nombre, sale su teléfono o su correo. Si mandó una foto o un audio, dice «Envió un archivo o un mensaje sin texto».

## Si el pie dice «avisos en v…»

Si al pie aparece **«avisos en v0.21.2: cierra y vuelve a abrir la app»**, el teléfono sigue pintando los avisos con la versión anterior. Ciérrala del todo y vuelve a abrirla hasta que el pie diga sólo **v0.21.3**.

## Para comprobarlo a mano en el servidor

Si alguna vez quieres ver la revisión funcionando desde la consola del servidor:

```
/usr/local/lsws/lsphp83/bin/php /home/nueva.agoradigital.app/agora-private/bin/sync-inbox.php --once
```

Hace una sola pasada y dice qué hizo.
