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

## Ficha de contacto 0.8.0

`GET /contacts/{contactId}`, versión `2021-07-28`, requiere `contacts.readonly` y devuelve identidad, contacto, fuente, responsable, actividad, etiquetas y valores personalizados. `GET /locations/{locationId}/customFields`, la misma versión, requiere `locations/customFields.readonly` y permite convertir IDs de campos en nombres. Ágora deriva el Contact ID exclusivamente de una conversación autorizada, valida ID y Location ID, limita tamaños y usa cachés de 60 segundos y una hora respectivamente.

## Canal de acceso por correo 0.8.1

Se volvió a clonar la fuente oficial el 29 de octubre de 2026: HEAD sigue en `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`. El portal web (`marketplace.gohighlevel.com`, `highlevel.stoplight.io`) no resuelve bajo la política de red de la sesión, así que la verificación se hace sobre la especificación publicada en el repositorio oficial, no sobre la interfaz.

| Paso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Buscar la ficha por correo | `GET /contacts/search/duplicate?locationId=…&email=…` | `contacts.readonly` | 2021-07-28 |
| Crear la ficha interna | `POST /contacts/upsert` | `contacts.write` | 2021-07-28 |
| Marcar la ficha | `POST /contacts/{contactId}/tags` | `contacts.write` | 2021-07-28 |
| Enviar el código | `POST /conversations/messages` | `conversations/message.write` | 2021-04-15 |

`SendMessageBodyDto` publica `type` con el valor `Email` y acepta `contactId`, `subject`, `html`, `message`, `emailFrom`, `emailCc`, `emailBcc`, `emailTo`, `attachments`, `threadId`, `replyMessageId`, `templateId`, `scheduledTimestamp` y `emailReplyMode`. El destinatario documentado es el contacto: `emailTo` se describe como «email address to send to, if different from contact's primary email. This should be a valid email address associated with the contact». No es un canal para destinatarios arbitrarios, así que Ágora no lo utiliza y envía siempre con `contactId`. La discrepancia conocida permanece: el esquema marca `subType` y `status` como obligatorios junto a `type` y `contactId`, mientras que el ejemplo publicado y el adaptador de texto en producción desde 0.4.0 funcionan sin ellos. No se fabrica un `status` «delivered» para que el endpoint acepte el payload; si la cuenta real exigiera esos campos, el envío se rechaza y Ágora lo informa como canal de correo no disponible.

`GET /contacts/search/duplicate` documenta que, con contactos duplicados desactivados, busca por el identificador único global, y con duplicados activados da prioridad al correo y después al teléfono. `POST /contacts/upsert` sólo exige `locationId` y responde `new` más la ficha completa con su `id`; `createNewIfDuplicateAllowed` se deja sin enviar para conservar el comportamiento normal de upsert. `POST /contacts/{contactId}/tags` recibe `{"tags":[…]}` y añade etiquetas sin reemplazarlas, por lo que Ágora marca la ficha por esa vía y nunca mediante el campo `tags` del upsert, cuya propia documentación advierte que «this field will overwrite all current tags associated with the contact».

Límites: la [guía OAuth](https://github.com/GoHighLevel/highlevel-api-docs/blob/main/docs/oauth/Authorization.md) publica 100 peticiones/10 s y 200.000/día por app y recurso. Los límites del PIT siguen sin publicarse. Una entrega consume una única ranura de `GhlGateway` y, dentro de ella, hasta cuatro peticiones (búsqueda, creación, etiqueta y envío); una entrega posterior al mismo integrante consume una sola petición porque reutiliza la relación guardada. El presupuesto local cuenta esa ranura como una petición, de modo que subestima el consumo real en la primera entrega de cada integrante: es un tope de seguridad, no una medida del cupo. Ágora añade además límites propios por dirección, por origen y por ventana de 15 minutos antes de llegar a la API.

Requisitos del contacto y del canal: la cuenta elegida necesita un servicio de correo operativo y la ficha debe pertenecer a su `locationId`, que Ágora comprueba en cada respuesta. Permisos insuficientes (401/403) se informan como tales y no desactivan la conexión ni la bandeja; 400/422 en el envío se informan como canal de correo no disponible; un 404 borra la relación guardada para que el siguiente intento vuelva a resolver la ficha; 429 pausa la entrega con el `Retry-After` recibido.

## Contexto de oportunidad 0.9.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`, revisado el 29 de octubre de 2026.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Oportunidades de un contacto | `GET /opportunities/search?location_id=…&contact_id=…&status=all&limit=20` | `opportunities.readonly` | 2021-07-28 |
| Nombres de pipeline y etapa | `GET /opportunities/pipelines?locationId=…` | `opportunities.readonly` | 2021-07-28 |

`GET /opportunities/search` declara `location_id` como único parámetro obligatorio y admite `contact_id`, `pipeline_id`, `pipeline_stage_id`, `assigned_to`, `id`, `q`, `status`, paginación (`page`, `limit`, `startAfter`, `startAfterId`) y los añadidos opcionales `getTasks`, `getNotes` y `getCalendarEvents`. El esquema documenta `limit` con un máximo de 100 y 20 por defecto; Ágora pide 20 y lo trata como ventana, no como lista completa. `status` admite `open`, `won`, `lost`, `abandoned` y `all`; se usa `all` para poder mostrar también la última oportunidad cerrada cuando no hay ninguna abierta. No se envían `getTasks`, `getNotes` ni `getCalendarEvents`: tareas, notas y citas llegarán con su propio bloque y sus propios permisos.

`SearchOpportunitiesResponseSchema` publica `id`, `name`, `monetaryValue`, `pipelineId`, `pipelineStageId`, `assignedTo`, `status`, `source`, `lastStatusChangeAt`, `lastStageChangeAt`, `lastActionDate`, `createdAt`, `updatedAt`, `contactId`, `locationId`, `lostReasonId`, `customFields` y `followers`. No publica código de moneda, así que Ágora presenta el importe con formato español y sin símbolo en lugar de suponer una divisa. `status` se traduce a una etiqueta en español y un valor desconocido se deja vacío.

`GET /opportunities/pipelines` responde `PipelinesResponseSchema` con `id`, `name`, `stages`, `locationId`, `showInFunnel`, `showInPieChart` y `colorRenderMode`. `stages` es un array sin esquema de elemento publicado, por lo que cada etapa se normaliza de forma defensiva por `id` y `name`: una etapa sin nombre utilizable se descarta y nunca se muestra un identificador como si fuera un nombre. Un pipeline de otra `locationId` o sin nombre se descarta.

Ágora deriva el Contact ID exclusivamente de una conversación que el integrante tiene permiso para ver, comprueba `locationId` y `contactId` en cada registro recibido y descarta el resto. El permiso efectivo se evalúa con `opportunitiesEnabled` y el scope de oportunidades; con `assignedDataOnly` sólo se muestran las oportunidades cuyo `assignedTo` coincide con el usuario importado, y la oportunidad activa se elige dentro de ese subconjunto. El nombre del responsable se resuelve contra el equipo ya importado, no con una consulta adicional.

Cachés y cuota: el resultado por contacto se guarda 60 segundos y el directorio de pipelines una hora. Ambas lecturas comparten una única ranura de `GhlGateway`, porque el presupuesto espacia las ranuras un segundo y el panel necesita los nombres en la primera apertura. Si falta el permiso para pipelines, el bloque sigue funcionando sin nombres en lugar de fallar. Un rechazo 401/403 no desactiva la conexión ni la bandeja, y una respuesta con forma inesperada no sustituye el resultado ya guardado.
