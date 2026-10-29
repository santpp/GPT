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

## Tareas, notas, citas y acciones rápidas 0.10.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`, revisado el 29 de octubre de 2026.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Tareas del contacto | `GET /contacts/{contactId}/tasks` | `contacts.readonly` | 2021-07-28 |
| Notas del contacto | `GET /contacts/{contactId}/notes` | `contacts.readonly` | 2021-07-28 |
| Citas del contacto | `GET /contacts/{contactId}/appointments` | `contacts.readonly` | 2021-07-28 |
| Crear tarea | `POST /contacts/{contactId}/tasks` | `contacts.write` | 2021-07-28 |
| Crear nota | `POST /contacts/{contactId}/notes` | `contacts.write` | 2021-07-28 |
| Completar o reabrir tarea | `PUT /contacts/{contactId}/tasks/{taskId}/completed` | `contacts.write` | 2021-07-28 |

Las tres lecturas se publican bajo `contacts.readonly`, incluidas las citas: el endpoint de citas del contacto no pide un scope de calendarios. Aun así, Ágora sólo muestra las citas a un integrante cuyo módulo de citas esté activo, porque el permiso del módulo en la cuenta es lo que debe decidir qué ve esa persona; el administrador nativo las ve siempre.

`CreateTaskParams` marca `title`, `dueDate` y `completed` como obligatorios, y admite `body` y `assignedTo`. Ágora envía los tres obligatorios, con `completed` en `false`, y añade `body` sólo si hay detalle. No se envía `assignedTo`: asignar a otra persona es una decisión que requiere elegir usuario y todavía no hay esa interfaz, y no debe deducirse del integrante que pulsa el botón. La fecha se normaliza a una marca UTC explícita (`YYYY-MM-DDTHH:MM:SSZ`); una fecha de calendario sin hora se fija al mediodía UTC para que no cambie de día según la zona del servidor.

`NotesDTO` marca sólo `body` como obligatorio y admite `userId`, `title`, `color` y `pinned`. Ágora envía únicamente `body`: no se inventa un `userId`, porque el PIT no identifica a la persona que actúa y atribuir la nota a un usuario concreto sería falsear la autoría. `UpdateTaskStatusParams` exige `completed`, que es lo único que se envía al completar o reabrir.

`TaskSchema` publica `id`, `title`, `body`, `assignedTo`, `dueDate`, `completed` y `contactId`. `GetNoteSchema` publica `id`, `body`, `userId`, `dateAdded`, `contactId`, `title`, `color` y `pinned`. `GetEventSchema` publica `id`, `calendarId`, `status`, `title`, `assignedUserId`, `notes`, `startTime`, `endTime`, `address`, `locationId`, `contactId`, `groupId`, `appointmentStatus`, `users`, `dateAdded`, `dateUpdated` y `assignedResources`. Ágora comprueba `contactId` en cada registro y descarta el que nombre otro contacto; un registro sin `contactId` se acepta porque el endpoint ya está dirigido a ese contacto. El estado de la cita se lee de `appointmentStatus` y, si falta, de `status`, y se traduce a una etiqueta en español; un valor desconocido se deja vacío.

Las tres lecturas comparten una única ranura de `GhlGateway` y se guardan 60 segundos por contacto. Se piden como máximo 25 registros de cada tipo y, al alcanzar ese límite, se informa en lugar de dar la lista por completa.

Ninguno de los tres endpoints de escritura es idempotente: repetir la petición crea otra nota u otra tarea. La API tampoco acepta una clave de idempotencia. Ágora registra cada intento bajo una clave generada por el navegador y derivada del contenido, igual que el envío de mensajes: una repetición con la misma clave devuelve el primer resultado sin volver a escribir, la misma clave con contenido distinto se rechaza, y un contenido idéntico con una clave nueva se bloquea mientras un intento anterior siga sin resultado confirmado. Un resultado incierto no se declara correcto. Un rechazo 401/403 no desactiva la conexión ni la bandeja.

## Pantallas de contactos y oportunidades 0.11.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`, revisado el 29 de octubre de 2026.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Lista de contactos | `GET /contacts/?locationId=…&query=…&limit=50` | `contacts.readonly` | 2021-07-28 |
| Tablero de oportunidades | `GET /opportunities/search?location_id=…&status=open&limit=100` | `opportunities.readonly` | 2021-07-28 |

`GET /contacts/` declara `locationId` obligatorio y admite `query`, `limit` (máximo publicado 100, por defecto 20), `startAfter` y `startAfterId`. Responde `contacts` y `count`. Ágora pide 50 y trata el resultado como ventana, no como lista completa; el término de búsqueda se reduce a letras, números y los signos de una dirección o un teléfono antes de enviarlo, de modo que no se reenvía ningún operador. `POST /contacts/search` existe con el mismo scope, pero su `SearchBodyV2DTO` se publica sin propiedades, así que no se usa: no se puede construir un cuerpo contra un contrato vacío sin suponer.

El tablero reutiliza `GET /opportunities/search` con `status=open` y sin `contact_id`, y agrupa en el servidor por `pipelineId` y `pipelineStageId` contra el directorio de embudos. Una oportunidad cuya etapa no figura en el embudo se recoge en una columna «Sin etapa» explícita, en lugar de desaparecer o de mostrar un identificador. Las dos lecturas del tablero comparten una sola ranura de `GhlGateway`.

Ambas pantallas respetan `assignedDataOnly` filtrando por `assignedTo` después de leer, y lo indican en la propia pantalla para que una lista corta no se confunda con la totalidad de la cuenta. El filtro se aplica sobre la ventana leída: un integrante con muchos registros ajenos por delante puede ver menos de los suyos de los que existen, y por eso la pantalla avisa de que muestra los más recientes.

## Presupuesto local revisado en 0.11.0

Hasta 0.10.0 el gateway imponía un hueco fijo de un segundo entre peticiones de una misma conexión. La [guía OAuth](https://github.com/GoHighLevel/highlevel-api-docs/blob/main/docs/oauth/Authorization.md) publica 100 peticiones por 10 segundos y 200.000 al día por app y recurso, de modo que ese hueco era diez veces más restrictivo que el límite publicado y hacía que una pantalla con varias lecturas se bloqueara a sí misma. Se sustituye por una ventana deslizante de 20 peticiones cada 10 segundos por conexión: una quinta parte del límite publicado, que deja margen para cualquier otro uso de la misma credencial y permite que una pantalla haga sus lecturas sin esperar. Se conservan el presupuesto diario, la pausa derivada de las cabeceras `X-RateLimit-*` y el tratamiento de 429 con `Retry-After`. Los límites reales del PIT siguen sin publicarse.

## Tolerancia del historial revisada en 0.11.0

`GetMessagesByConversationResponseDto` publica `messages`, `nextPage` y `lastMessageId`. La validación anterior descartaba la página entera ante tres diferencias que no son un problema de seguridad: una página mayor que el límite pedido, un registro sin `id`, y un `lastMessageId` que no aparece entre los mensajes devueltos. Una cuenta real puede producir cualquiera de las tres, y el resultado era `messages_invalid_response` con el historial vacío. Ahora una página mayor se acepta hasta un tamaño implausible, un registro sin identificador se omite y el resto se muestra, y un cursor inservible detiene la paginación en vez de tirar la página: la protección contra bucles se mantiene, porque ese cursor nunca se usa. Las comprobaciones de cuenta y de conversación siguen siendo estrictas y rechazan la respuesta. Cada rechazo publica ahora su motivo (`messages_page_too_large`, `messages_conversation_mismatch`, `messages_location_mismatch`, `messages_messages_missing`, `messages_messages_not_a_list`) para poder diagnosticar sin exponer datos.

## Correcciones observadas en producción 0.11.1

**Envoltorio del historial.** `GetMessagesByConversationResponseDto` publica `lastMessageId`, `nextPage` y `messages` en el primer nivel, y la especificación no incluye ningún ejemplo de respuesta. En la cuenta real, el historial seguía rechazándose en 0.11.0 con el mensaje nuevo, lo que descarta las tres tolerancias añadidas entonces y deja como causa probable que la cuenta responda con los mismos campos envueltos un nivel más abajo (`{"messages":{"messages":[…],"nextPage":…,"lastMessageId":…}}`). Ágora acepta ahora las dos formas y no inventa ninguna otra: si el contenido no es una lista de mensajes en ninguno de los dos niveles, se rechaza igual. Las comprobaciones de cuenta y conversación siguen siendo estrictas en ambas formas. El motivo concreto viaja ahora en el texto visible, no sólo en el campo `code`, para poder confirmarlo contra la cuenta en lugar de deducirlo.

**Contención del bloqueo por conexión.** `GhlGateway` tomaba su cerrojo con `GET_LOCK(clave, 0)`, que falla en el acto si otra petición de la misma conexión lo tiene. Al pedir la ficha sus cuatro bloques a la vez, cada pantalla competía consigo misma y con el sondeo del chat, y el resultado era «Espera 2 segundos» aunque hubiera presupuesto de sobra: no era la cuota, era la cola. El cerrojo espera ahora hasta seis segundos (cinco en la caché de mensajes) y la ficha pide sus bloques uno detrás de otro. La ventana de 20 peticiones cada 10 segundos no cambia.

## Edición de ficha y selector de canal 0.12.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`, revisado el 29 de octubre de 2026.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Editar la ficha | `PUT /contacts/{contactId}` | `contacts.write` | 2021-07-28 |
| Añadir etiquetas | `POST /contacts/{contactId}/tags` | `contacts.write` | 2021-07-28 |
| Quitar etiquetas | `DELETE /contacts/{contactId}/tags` | `contacts.write` | 2021-07-28 |

`UpdateContactDto` no marca ningún campo obligatorio y admite `firstName`, `lastName`, `name`, `email`, `phone`, `address1`, `city`, `state`, `postalCode`, `country`, `website`, `timezone`, `source`, `dnd`, `dateOfBirth`, `assignedTo`, `customFields` y `tags`. Ágora envía únicamente los campos que la persona cambió, de modo que una edición nunca reescribe lo que no tocó, y **nunca envía `tags` por esta vía**: la propia documentación advierte que ese campo «will overwrite all current tags associated with the contact». Las etiquetas viajan como altas y bajas por los dos endpoints dedicados, que son aditivos.

Hay una asimetría publicada que conviene recordar: al leer, un campo personalizado llega como `{id, value}`; al escribir, `TextField` y sus hermanos exigen `{id, field_value}`. Ágora traduce entre las dos formas y descarta identificadores que no encajen en el formato esperado. `dnd`, `dateOfBirth` y `assignedTo` no se exponen todavía: el primero necesita su propia explicación al usuario, el segundo admite seis formatos distintos y el tercero requiere un selector de usuario.

**Selector de canal.** `SendMessageBodyDto` publica `type` con `SMS`, `RCS`, `Email`, `WhatsApp`, `IG`, `FB`, `Custom`, `Live_Chat` y `TIKTOK`, y el destinatario documentado sigue siendo el contacto. Ágora calcula en el servidor qué canales admite cada contacto y no acepta otro: el canal del propio hilo siempre; `SMS` y `WhatsApp` si la ficha guardada tiene teléfono; `Email` si tiene correo. Instagram, Messenger y el chat web no se ofrecen salvo que sean el canal del hilo, porque no hay forma documentada de iniciar una conversación por ahí. El cálculo se hace sobre la ficha ya almacenada en caché, sin ninguna petición adicional, y si no hay ficha conocida sólo se ofrece el canal del hilo: falla cerrado. La elección del navegador se vuelve a comprobar contra ese mismo cálculo antes de enviar; el destino sigue saliendo del servidor y nunca de la petición.

**Texto enriquecido en tareas y notas.** La cuenta devuelve el cuerpo de tareas y notas como HTML. Las pantallas de Ágora muestran texto de lectura: las etiquetas de salto se convierten en saltos, el resto se elimina, las entidades se decodifican una sola vez y el largo se mide después de limpiar. Nunca se interpreta marcado recibido de la cuenta.

## Ficha completa 0.13.0

`CustomFieldSchema`, en `GET /locations/{locationId}/customFields`, publica además de `id` y `name` los campos `dataType`, `picklistOptions`, `picklistImageOptions`, `placeholder`, `fieldKey`, `position`, `isAllowedCustomOption`, `isMultiFileAllowed`, `maxFileLimit`, `locationId` y `model`. `dataType` se publica como cadena libre, sin enumeración, de modo que Ágora reconoce los tipos que sabe editar (`TEXT`, `LARGE_TEXT`, `NUMERICAL`, `MONETORY`, `PHONE`, `EMAIL`, `DATE`, `CHECKBOX`, `SINGLE_OPTIONS`, `RADIO`, `MULTIPLE_OPTIONS`, `TEXTBOX_LIST`) y presenta cualquier otro como texto de sólo lectura, en lugar de suponer un control. Los campos se ordenan por `position`.

La distinción importante es que el directorio son las **definiciones** y la ficha son los **valores**: `GET /contacts/{contactId}` sólo devuelve los campos personalizados que tienen valor. Ágora combina los dos, así que un campo definido y vacío aparece y puede rellenarse, algo imposible leyendo sólo la ficha.

La ficha publica además `address1`, `city`, `state`, `postalCode`, `country`, `website`, `timezone`, `dnd`, `dateOfBirth` y `assignedTo`, que antes se perdían o se mostraban unidos en una sola línea de dirección. Ahora cada parte viaja por separado para poder editarse y la línea unida se conserva sólo para leer.

Al escribir: `UpdateContactDto` admite `dnd` booleano y `assignedTo` con el identificador de un usuario. Ágora sólo acepta como responsable a un integrante importado y activo de esa misma cuenta, comprobado contra el equipo ya sincronizado; cualquier otro identificador se rechaza antes de salir. `dateOfBirth` se documenta con seis formatos admitidos (`YYYY/MM/DD`, `MM/DD/YYYY`, `YYYY-MM-DD`, `MM-DD-YYYY`, `YYYY.MM.DD` y otro más); Ágora acepta únicamente una fecha de calendario real en `YYYY-MM-DD` y la envía así, en vez de adivinar cuál interpreta la cuenta.

`field_value` no es siempre una cadena: `TextField` y `LargeTextField` lo declaran string y `NumericField` lo declara object. Ágora conserva la forma de cada control: un número se envía como número, una casilla como booleano, una selección múltiple como lista y el resto como texto.

## Fila completa de canales 0.14.0

`availableChannels` pasa a publicar **todos** los canales de texto que `SendMessageBodyDto` enumera y que Ágora sabe manejar (`WhatsApp`, `SMS`, `IG`, `FB`, `Email`), cada uno con `available` y, cuando no lo está, el motivo: `needs_phone`, `needs_email` o `needs_thread`. La interfaz los muestra todos, como hace el producto original, y sólo deja elegir los utilizables. El criterio no cambia: el canal del propio hilo siempre; teléfono en la ficha abre SMS y WhatsApp; correo abre Email; Instagram y Messenger sólo si son el hilo, porque la especificación no documenta forma de iniciarlos. El envío sigue comprobando contra el subconjunto utilizable, de modo que un canal deshabilitado no puede forzarse desde el navegador.

Los iconos de canal son dibujos propios en SVG, no los archivos de marca de cada servicio: identifican el canal sin redistribuir material de terceros.

## Directorio de campos: caché fría y caché antigua 0.14.0

El panel recibía el directorio de campos sólo si estaba en caché. Con la caché fría o caducada llegaba vacío, y como `GET /contacts/{contactId}` sólo devuelve los campos personalizados **con valor**, el formulario de edición se quedaba sin ninguno: justamente el caso que la versión anterior pretendía resolver. Ahora, si falta, el directorio se pide en ese momento (una petición por hora como mucho) y sólo un fallo real lo deja vacío. Además, una caché guardada por versiones anteriores a 0.13.0, sin `dataType`, se considera caducada, de modo que al actualizar se vuelve a pedir en lugar de ofrecer todos los campos como texto plano.

## Bandeja en vivo, filtros y leído 0.15.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Bandeja y filtros | `GET /conversations/search` | `conversations.readonly` | 2021-04-15 |
| Marcar como leída | `PUT /conversations/{conversationId}` | `conversations.write` | 2021-04-15 |

`GET /conversations/search` publica, además de `locationId` y `limit`, los parámetros `status` (`all`, `read`, `unread`, `starred`, `recents`), `lastMessageType` (con la lista completa de canales), `lastMessageDirection` (`inbound`, `outbound`), `assignedTo`, `contactId`, `query`, `followers`, `mentions`, `sortBy` y rangos de fecha. Ágora traslada a la cuenta los filtros de estado, canal, asignación y búsqueda, de modo que «no leídas» significa todas las que la cuenta tiene sin leer y no sólo las que caen dentro de la ventana cargada. Cualquier valor que no figure en la enumeración publicada se descarta antes de salir, y «mías» sólo se envía si el integrante tiene identidad importada.

`UpdateConversationDto` exige `locationId` y admite `unreadCount`, `starred` y `feedback`. Marcar como leída es enviar `unreadCount: 0` con el `locationId` de la conexión. Requiere `conversations.write`, que hasta ahora no hacía falta: sin ese permiso la lectura sigue funcionando y sólo falla el marcado, con su propio código.

**Lecturas siempre frescas.** Por decisión de producto, toda pantalla pide a la cuenta en cada carga. Lo guardado deja de ser la fuente y pasa a ser un respaldo que sólo entra cuando el presupuesto está en pausa o la lectura falla, y la respuesta lo marca (`stale`) junto con la fecha de la copia, para que nunca pase por actual. Esto multiplica las peticiones frente al diseño anterior: con la ventana de 20 cada 10 segundos por conexión hay margen para un uso normal, pero varios integrantes sobre una misma subcuenta pueden toparse con pausas, y entonces verán la última lista con su aviso en lugar de un error.

**Autorización separada de la lista.** Como la lista mostrada depende ahora de los filtros, no puede ser la base de la autorización: una conversación abierta dejaría de estar permitida al cambiar un filtro. Se guarda un índice por conexión con cada conversación que la cuenta ha mostrado (hasta 400, las más recientes), y es ese índice el que autoriza leer mensajes, responder, ver la ficha o actuar. Una conversación que la cuenta nunca mostró no se autoriza.

**Adjuntos.** `GetMessageResponseDto` publica `attachments` como array. Ágora muestra los que llegan: sólo enlaces `https`, el nombre tomado de la ruta del archivo y nunca de texto del remitente, y las imágenes en línea. El envío de adjuntos necesitaría almacenamiento propio y no se incluye.

**Plantillas de WhatsApp: no implementadas.** `GET /locations/{locationId}/templates` acepta `type=whatsapp` y declara el scope `locations/templates.readonly`, pero exige un parámetro `originId` cuyo significado no se documenta en ninguna parte del esquema, y la respuesta sólo publica estructuras para SMS y correo (`GetSmsTemplateResponseSchema`, `GetEmailTemplateResponseSchema`), sin esquema para WhatsApp. No se implementa a partir de un parámetro obligatorio adivinado; queda pendiente de comprobar contra una cuenta real qué valor espera.
