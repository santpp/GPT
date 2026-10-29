# Verificación de API GoHighLevel

Revisión: 28 de octubre de 2026. Fuente oficial descargada desde [GoHighLevel/highlevel-api-docs](https://github.com/GoHighLevel/highlevel-api-docs), commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`. El portal web no pudo consultarse por la política de red. Esta es verificación de contratos publicados, no una prueba contra la subcuenta.

Base HTTPS: `https://services.leadconnectorhq.com`. Bearer sólo en backend. La documentación declara Private Integration Token de subcuenta como autenticación admitida. Respetar la cabecera Version por endpoint, no una única versión global.

| Área | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| conversations | `GET /conversations/search` | `conversations.readonly` | 2021-04-15 |
| conversations | `GET /conversations/{conversationId}/messages` | `conversations/message.readonly` | 2021-04-15 |
| conversations | `POST /conversations/messages` | `conversations/message.write` | 2021-04-15 |
| contacts | `POST /contacts/search` | `contacts.readonly` | 2021-07-28 |
| contacts | `GET /contacts/{contactId}` | `contacts.readonly` | 2021-07-28 |
| contacts | `PUT /contacts/{contactId}` | `contacts.write` | 2021-07-28 |
| contacts | `GET /contacts/{contactId}/notes` | `contacts.readonly` | 2021-07-28 |
| contacts | `POST /contacts/{contactId}/notes` | `contacts.write` | 2021-07-28 |
| contacts | `GET /contacts/{contactId}/tasks` | `contacts.readonly` | 2021-07-28 |
| contacts | `POST /contacts/{contactId}/tasks` | `contacts.write` | 2021-07-28 |
| contacts | `POST /contacts/{contactId}/tags` | `contacts.write` | 2021-07-28 |
| contacts | `POST /contacts/{contactId}/workflow/{workflowId}` | `contacts.write` | 2021-07-28 |
| opportunities | `GET /opportunities/search` | `opportunities.readonly` | 2021-07-28 |
| opportunities | `POST /opportunities/search` | `opportunities.readonly` | 2021-07-28 |
| opportunities | `GET /opportunities/pipelines` | `opportunities.readonly` | 2021-07-28 |
| opportunities | `GET /opportunities/{id}` | `opportunities.readonly` | 2021-07-28 |
| opportunities | `PUT /opportunities/{id}` | `opportunities.write` | 2021-07-28 |
| calendars | `GET /calendars/` | `calendars.readonly` | 2021-04-15 |
| calendars | `POST /calendars/` | `calendars.write` | 2021-04-15 |
| calendars | `GET /calendars/events` | `calendars/events.readonly` | 2021-04-15 |
| calendars | `POST /calendars/events/appointments` | `calendars/events.write` | 2021-04-15 |
| locations | `GET /locations/{locationId}/customFields` | `locations/customFields.readonly` | 2021-07-28 |
| locations | `POST /locations/{locationId}/customFields` | `locations/customFields.write` | 2021-07-28 |
| locations | `GET /locations/{locationId}/tags` | `locations/tags.readonly` | 2021-07-28 |
| locations | `POST /locations/{locationId}/tags` | `locations/tags.write` | 2021-07-28 |
| users | `GET /users/` | `users.readonly` | 2021-07-28 |
| users | `POST /users/` | `users.write, users.write` | 2021-07-28 |

## Webhooks: alternativa opcional, desactivada por decisión del usuario

La [FAQ oficial](https://github.com/GoHighLevel/highlevel-api-docs/blob/main/docs/oauth/Faqs.md) documenta app Marketplace, URL, scopes e instalación OAuth para empezar a recibir eventos. Un PIT por sí solo no demuestra suscripción. No se ha confirmado una suscripción universal vía PIT: verificar si el cliente dispone de una app privada instalable o si se necesita un relay de workflows que cubra sólo determinados triggers. No equiparar un webhook de workflow con los eventos de Marketplace ni aplicar su firma por suposición. Si se usa relay debe tener contrato y autenticación propios.

Eventos publicados relevantes: `InboundMessage`, `OutboundMessage`, `ConversationUnreadUpdate` (nombre real del payload, aunque el archivo se llama ConversationUnreadWebhook), `ContactCreate/Update/Delete`, `ContactTagUpdate`, oportunidades Create/Update/Delete y eventos de etapa/estado/valor/asignación, `AppointmentCreate/Update/Delete`, `TaskCreate/Complete/Delete` y `NoteCreate/Update/Delete`. No hay que inventar TaskUpdate si no se documenta: usar reconciliación.

La [guía de autenticación](https://github.com/GoHighLevel/highlevel-api-docs/blob/main/docs/oauth/WebhookAuthentication.md) publica `x-wh-signature`, RSA-SHA256 y clave pública, más timestamp y webhookId. La clave guardada es pública; no es un secreto. Verificar sobre el cuerpo crudo. No aceptar sin firma ni mezclar un HMAC propio con este protocolo.

## Límites y comprobaciones pendientes

La [guía OAuth](https://github.com/GoHighLevel/highlevel-api-docs/blob/main/docs/oauth/Authorization.md) publica 100 peticiones/10 s y 200.000/día por app/recurso para OAuth. No se han comprobado límites específicos del PIT. Medir cabeceras `X-RateLimit-*`, manejar 429 y Retry-After, backoff con jitter y presupuesto por location. Reintentar lecturas transitorias de forma limitada; escrituras requieren reconciliación ante resultado incierto. GhlGateway incorpora ahora presupuesto local, pausas y lectura de cabeceras; los límites reales del PIT siguen pendientes de medición.

La especificación actual de SendMessageBodyDto marca campos adicionales como `subType` y `status` obligatorios junto a type/contactId. Hay que contrastar el contrato con solicitudes controladas por canal antes de implementar envíos; no forzar un estado delivered para conseguir que el endpoint acepte el payload.

Scopes mínimos de lectura inicial: `conversations.readonly`, `conversations/message.readonly`, `contacts.readonly`, `opportunities.readonly`, `calendars.readonly`, `calendars/events.readonly`, `users.readonly`, `locations/customFields.readonly` y `locations/tags.readonly` cuando los módulos correspondientes se activen. Confirmar en el selector PIT los scopes realmente disponibles. Añadir escrituras únicamente al habilitar y probar esas acciones.

No se han solicitado ni almacenado credenciales reales. Se necesitan locationId, PIT y acceso al mecanismo de eventos para validar el flujo del cliente. El PIT debe añadirse por el administrador exclusivamente al backend, mediante el gestor de secretos del despliegue. Nunca enviarlo en chat. Firebase requiere proyecto, identidad del servidor y configuración Android pública; todavía no configurados.

## Configuración PIT desde el administrador

La decisión actual usa PIT por subcuenta y no necesita suscripción webhook. La API de configuración cifra la credencial en el servidor; el token no se devuelve en consultas. El botón de comprobación llama a `GET /locations/{locationId}` con `Version: 2021-07-28` y scope `locations.readonly`, según `apps/locations.json` de la fuente oficial revisada. Se verifica que `location.id` coincida con la configuración. Eso sólo valida esa lectura; no el resto de permisos ni los canales.

Sin webhooks se requiere sincronización REST programada para detectar cambios. FCM no puede avisar de un mensaje que el backend aún no ha descubierto. La primera tarea cron renueva una ventana de 20 conversaciones; requiere activación y cron en CyberPanel. No hay aún push, actualización automática de pantalla ni importación completa. Ver api-sync.md.

## Revisión para 0.3.0

Se volvió a consultar HEAD del repositorio oficial el 28 de octubre de 2026: permanece en `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`. `apps/conversations.json`, GetMessagesByConversationResponseDto, publica messages, nextPage y lastMessageId; GET admite lastMessageId y limit. Se incorpora paginación de mensajes con el scope de lectura existente. SendMessageBodyDto sigue marcando subType/status obligatorios; esta entrega no habilita envío ni presupone restricciones específicas de cada canal.

## Revisión 0.4.0

Se contrastó además el SDK oficial highlevel-api-sdk (`2b1b8e2bdf50617a330a7bfb66f9e9b49e1c01c3`, npm 3.1.1). GET /conversations/search publica limit numérico, sin máximo en el esquema consultado; Ágora permite sólo 20/50/100 y conserva el resultado anterior ante rechazo. POST /conversations/messages y conversations/message.write permiten implementar un adaptador de texto; la discrepancia subType/status permanece y el payload mínimo debe validarse con la cuenta real. Ver message-sending.md para la decisión, estados e incertidumbre; no se declara éxito por canal a partir de mocks.

## Usuarios y permisos 0.6.0

`GET /users/?locationId=…`, Version `2021-07-28`, publica acceso de ubicación con `users.readonly` y devuelve `UserSchema`: ID, email, rol, `PermissionsDto`, scopes y subcuentas. Se usa para proyectar el equipo de cada conexión. La propia documentación lo marca deprecado y recomienda `GET /users/search`; el reemplazo exige `companyId`, por lo que no debe inventarse a partir de Location ID. La migración se hará cuando el ID de agencia esté disponible y validado.

El PIT no ofrece un endpoint para verificar la contraseña de un usuario. La importación de usuarios no se considera autenticación ni crea sesiones. Ágora mantiene una sola cuenta local administrativa y deja preparada una vinculación posterior con un proveedor de identidad verificado. Los permisos importados se filtran a claves booleanas conocidas y scopes de formato seguro; un usuario ausente queda inactivo.

## Acceso sincronizado 0.7.0

La revisión del mismo commit oficial confirma que Authorization Code identifica a quien instala/autoriza una app y que la guía pide intervención de un administrador de agencia o ubicación. No se utiliza como login cotidiano de empleados. El Shared Secret documenta contexto autenticado para Custom JavaScript y Custom Pages ejecutadas dentro de la interfaz original; tampoco autentica una visita directa a Ágora. No hay endpoint público para verificar usuario y contraseña.

Ágora vincula por correo exacto normalizado a los usuarios importados y verifica la posesión mediante código temporal enviado por el backend. Esto permite sincronizar alta, baja, rol, permisos y asignaciones sin almacenar credenciales externas ni presentar el proveedor técnico en la interfaz del cliente.
