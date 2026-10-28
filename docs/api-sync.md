# Primera lectura del inbox sin webhooks

## Alcance implementado

- `GET /conversations/search`, Version `2021-04-15`, locationId fijo de la conexión, sort desc / last_message_date y limit 20/50/100 según preferencia de conexión. Contrato revisado en la fuente oficial de GoHighLevel antes de implementar.
- Instantánea por connection_id con fecha de sincronización, número total reportado y aviso de parcialidad. No se mezclan IDs de distintas subcuentas.
- Conversaciones reales en el menú principal, separadas de la demo, sólo para el administrador en esta primera versión. No se simula pertenencia de vendedores: falta configurar sus membresías GHL por location.
- Lectura paginada en bloques de 20 mensajes de una conversación presente en esa instantánea, mediante el backend. La ruta exige empresa/conexión y valida locationId y conversationId en la respuesta. No renderiza HTML remoto como código y no marca como leído.
- `POST /api/admin/integrations/{id}/sync` con sesión/origen/CSRF, conexión previamente comprobada y limitación compartida.
- `POST /api/admin/integrations/{id}/schedule` para activar/desactivar la programación; activación requiere comprobación previa. Esto no instala cron automáticamente.
- Cron CLI `bin/sync-inbox.php`: una pasada de hasta 20 conexiones vencidas, bloqueo global y por conexión, configurado como usuario del sitio desde CyberPanel.

## Cuotas y fallos

GhlGateway centraliza la comprobación de conexión, el listado y la lectura de mensajes. Usa un bloqueo por conexión, espaciado mínimo local de un segundo y presupuesto conservador de 5.000 llamadas por día UTC. Estos números son decisiones locales de protección, **no los límites verificados del PIT**. No hay una consulta por móvil.

Al recibir 429 se persiste una pausa de al menos 30 segundos o el Retry-After recibido (hasta un día en esta primera implementación). Las cabeceras numéricas X-RateLimit-* se conservan como metadatos seguros. Si remaining llega a cero se pausa según intervalo o hasta el siguiente día UTC. Los límites diarios de reset del proveedor aún deben medirse con el PIT real. Errores transitorios producen backoff creciente, sin reintentos inmediatos. Un 401/403 desactiva el cron de esa conexión; corregir credencial/scopes y comprobar antes de reactivar.

El token nunca se registra, y los cuerpos de error de GHL no salen por nuestra API ni se guardan como diagnóstico. Una respuesta de otra location se rechaza y conserva la instantánea previa. Rotar el token desactiva el cron y elimina la instantánea antigua; mantiene contadores/pausas para que la rotación no eluda cuotas. Operaciones en curso bloquean temporalmente la rotación para evitar guardar respuestas de una credencial anterior.

## Instalación / actualización

Importar también `database/003-api-sync.sql` en una base nueva. Para instalaciones existentes, ejecutar con el PHP 8.3 del sitio `bin/migrate-integrations.php`; es repetible y no elimina datos. El ZIP nativo incluye la migración. Preservar config/local.php y var/integration.key al actualizar.

Tras comprobar una conexión con locations.readonly, habilitar conversations.readonly para sincronizar y conversations/message.readonly para mensajes. Probar el botón manual antes de activar cron. El cron se ejecuta por minuto; el intervalo por defecto es 120 segundos. No se promete actualización cada cinco segundos ni tiempo real estricto.

## Límites de esta entrega

No es una importación inicial completa ni una sincronización incremental con historial. El cursor lastMessageId de mensajes se consume bajo demanda desde 0.3.0; se valida su formato, pertenencia a la página y avance. El cursor startAfterDate de búsqueda todavía no se consume: la lista continúa limitada al tamaño seleccionado de 20, 50 o 100 conversaciones. La búsqueda local indica expresamente ese alcance. No se detectan cambios fuera de la ventana reciente. El envío de texto está implementado desde 0.4.0, pendiente de validación por canal real (ver message-sending.md). No hay confirmación de lectura, adjuntos interactivos, SSE, actualización automática de la pantalla o FCM. El cron renueva la instantánea de conversaciones; no descarga todo el historial de cada una.

No se han usado tokens reales ni accedido al VPS. Las pruebas de GHL usan transporte inyectado **sólo desde código de prueba**, nunca desde parámetros HTTP. No hay un host/base URL configurable para evitar un proxy arbitrario o SSRF.

## Verificado

- 46 comprobaciones de integración HTTP + MariaDB: login, CSRF, permisos, cifrado/rotación, aislamiento de conexiones y rechazo de consultas de mensajes desconocidos.
- 24 comprobaciones de sincronización con respuestas GHL simuladas y MariaDB real: ruta y versión, límite, parcialidad, duplicación, mezcla de locations, 429, cuota diaria, cabeceras agotadas, revocación y credencial obsoleta.
- Chromium móvil: vista conectada y mensajes, aviso de parcialidad y escape de contenido remoto; regresiones de demo, formulario de tokens y shell offline.

La validación real requiere el despliegue HTTPS en nueva.agoradigital.app y la credencial introducida por su administrador, no enviada por chat.

## Validación 0.3.0

Nueve comprobaciones de contrato y validación de historial; Chromium a 390 y 1440 px con paginación, deduplicación, error 429 recuperable, búsqueda sin consultas, escape de HTML y cambio de cuenta/conversación. Las páginas antiguas no sustituyen la conversación seleccionada. El usuario confirmó sincronización real en 0.2.3; el historial nuevo aún requiere comprobación en el VPS.

## Tamaño de bandeja 0.4.0

POST /api/admin/integrations/{id}/sync?limit=20|50|100. Se valida una lista cerrada. La preferencia se guarda en connection_preferences sólo al completar la consulta y se usa en cron. Un cambio explícito de tamaño puede adelantarse al siguiente ciclo programado, pero mantiene bloqueo, espaciado y presupuesto del gateway. La búsqueda sigue siendo local. Aplicar migración 004 desde Conexiones antes de usar esta versión.
