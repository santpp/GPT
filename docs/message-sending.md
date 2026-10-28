# Respuestas de texto · 0.4.0

`POST /api/workspace/send?connectionId=…&conversationId=…` requiere sesión de administrador, Origin y CSRF. La conexión se busca por tenant; la conversación debe pertenecer a su instantánea validada. El backend obtiene contactId y canal de esa instantánea, sin aceptar destinatarios arbitrarios enviados desde el navegador. Requiere migración 004-messaging.sql, aplicable desde Conexiones.

Campos propios: requestId aleatorio de 16–64 caracteres, message (1–4000 caracteres), channel y subject obligatorio para Email (hasta 200). Mapeos: TYPE_SMS → SMS, TYPE_WHATSAPP → WhatsApp, TYPE_INSTAGRAM → IG, TYPE_FACEBOOK → FB, TYPE_EMAIL → Email. Otros canales no habilitan el formulario. El mensaje es texto; no se incluye HTML, adjuntos, templates, cambio de canal ni threading de email.

## Contrato contrastado y límite de validación

Se revisaron el repositorio oficial highlevel-api-docs, HEAD `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`, y el SDK oficial highlevel-api-sdk, HEAD `2b1b8e2bdf50617a330a7bfb66f9e9b49e1c01c3` (npm @gohighlevel/api-client 3.1.1). Ambos publican POST /conversations/messages, Version 2021-04-15 y scope conversations/message.write. El SDK también refleja la inconsistencia de subType (objeto cuyo ejemplo es texto) y status como campos obligatorios de entrada.

La implementación envía los campos de texto documentados type/contactId/message y subject para email. No inventa subType ni declara delivered. Esto es una decisión explícita de compatibilidad pendiente de validar por canal contra la cuenta real: si el proveedor exige campos adicionales, se mostrará rechazo, no un envío exitoso. No hubo credenciales reales ni solicitudes de envío a clientes desde desarrollo.

Las ventanas de respuesta social, plantillas de WhatsApp, consentimiento, configuración de remitentes y disponibilidad de canales siguen siendo restricciones del proveedor. Los errores no se eluden enviando por otro canal ni inventando estados. Las respuestas de error remotas no se exponen ni se guardan.

## Identidad de envíos y fallos

message_send_attempts guarda requestId, hash del contenido/destino, estado, messageId y código seguro. No guarda el cuerpo del mensaje. UNIQUE(connection_id,request_id), bloqueo de conversación y comprobación de hash impiden que una misma operación cambie de contenido o se envíe simultáneamente dos veces. Todas las llamadas pasan por GhlGateway y su presupuesto compartido.

Antes de la llamada se persiste sending. Una respuesta 2xx con messageId y la conversación esperada pasa a accepted; se conserva status sólo si es pending/delivered/read/failed, con pending como fallback. Repetir requestId devuelve el resultado registrado sin contactar al proveedor.

Una pausa local anterior a la llamada marca retryable; un reintento manual puede usar el mismo requestId. 400/401/403/404/413/415/422/429 son rechazos explícitos. Timeout, 408, 5xx, JSON inválido, respuesta incompleta o conversación discordante dejan unknown. Los procesos interrumpidos pueden dejar sending: se tratan como inciertos, no se reenvían. Tampoco se acepta otro requestId con idéntico hash/conversación en estado sending/unknown. No se promete exactly-once global: el proveedor no publica una clave de idempotencia remota para este endpoint.

No hay reconciliación automática ni botón para resolver unknown; el administrador debe revisar historial y contactar soporte para resolver el registro de forma auditada en una futura entrega. No hay reintentos automáticos de escrituras, ni una cola offline de envío.

El navegador conserva en sessionStorage sólo un identificador aleatorio indexado por conexión/conversación y hash. Permite recuperar con el mismo texto una respuesta perdida tras recargar. Los borradores quedan en memoria, separados por conversación; se pierden al recargar/cerrar, se limpian al cerrar sesión y no se almacenan mensajes ni tokens en caché. Un envío tardío no modifica el borrador de otro chat.

## Pruebas

Transportes simulados y MariaDB: petición HTTP, destino fijado por servidor, permisos, aislamiento, colisiones de clave, duplicados, cuotas, rechazo, resultado incierto y contenido remoto no registrado. Chromium móvil/escritorio: selector 20/50/100, POST+CSRF, texto preservado tras fallo, clave estable tras recarga, estados, asunto, borradores y respuestas tardías. Validación real por canal aún pendiente del despliegue.
