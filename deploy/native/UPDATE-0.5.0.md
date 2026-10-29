# Ágora 0.5.0 · chat activo y corrección de historial

1. Extraer `agora-update-0.5.0.zip` en la carpeta que contiene `public_html` y `agora-private`, reemplazando el código.
2. Abrir `/conexiones.html` como administrador y pulsar **Actualizar base de datos**. La migración 005 crea únicamente la caché compartida de mensajes; conserva claves, conexiones y conversaciones.
3. Abrir Conversaciones. `/version.json` debe indicar `0.5.0`.

El chat abierto consulta mensajes recientes cada 10 segundos mientras la pestaña está visible y en línea. Conserva el borrador, el historial ya cargado y la posición cuando el usuario está leyendo mensajes anteriores. Al ocultar la pestaña, salir de Conversaciones o perder conexión, deja de consultar. Las pausas y cuotas del backend siguen teniendo prioridad.

La caché del servidor dura 8 segundos. Varias pestañas o dispositivos que abran la misma conversación comparten el resultado reciente y un bloqueo evita consultas simultáneas. Sin webhooks, esto es actualización casi en tiempo real: el retraso normal puede ser de unos 10 segundos más la latencia o una pausa de cuota. El cron continúa actualizando la lista cuando la app está cerrada; no puede refrescar un chat abierto si el navegador no está ejecutándose.

Se corrige el fallo observado en mensajes reales que omiten `conversationId` o `locationId`: esos campos sólo se comparan cuando existen; un valor discordante sigue rechazándose. Los errores de dependencia salen ahora como JSON HTTP 424 para impedir que OpenLiteSpeed sustituya el cuerpo por su página HTML de 502. No se relaja el aislamiento por cuenta: la conversación debe existir en la instantánea de esa conexión.

No hay cambios en el token. La validación local usa respuestas simuladas; después de instalar hay que abrir un chat real. Si falla, la pantalla mostrará `messages_upstream_NNN` o `messages_invalid_response`, que puede compartirse sin incluir credenciales.
