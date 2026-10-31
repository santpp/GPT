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

## Tablero y ficha completa de contacto 0.16.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Totales de conversaciones y pendientes | `GET /conversations/search` | `conversations.readonly` | 2021-04-15 |
| Número de contactos | `GET /contacts/` | `contacts.readonly` | 2021-07-28 |
| Oportunidades abiertas | `GET /opportunities/search` | `opportunities.readonly` | 2021-07-28 |
| Nombres de embudo y etapa | `GET /opportunities/pipelines` | `opportunities.readonly` | 2021-07-28 |
| Ficha del contacto | `GET /contacts/{contactId}` | `contacts.readonly` | 2021-07-28 |
| Guardar la ficha | `PUT /contacts/{contactId}` | `contacts.write` | 2021-07-28 |
| Etiquetas | `POST` y `DELETE /contacts/{contactId}/tags` | `contacts.write` | 2021-07-28 |

El tablero no usa ningún endpoint nuevo: son lecturas que Ágora ya hacía, agrupadas en una sola ranura de cuota. `SendConversationResponseDto` documenta `total` como «total de resultados encontrados para la consulta», así que el número de conversaciones y el de pendientes se piden con `limit=1` y se lee ese campo, sin traer listas que no se van a mostrar. Los pendientes son `status=unread`, valor que figura en la enumeración publicada.

**Contactos y `count`.** `ContactsSearchSuccessfulResponseDto` publica `contacts` y `count`, pero **no documenta si `count` es el total encontrado o el tamaño de la página**. La pantalla de contactos lo viene tratando como total desde 0.11.0 y en cuentas reales se comporta como tal; el tablero hace lo mismo. Si alguna cuenta devolviera el tamaño de página, la cifra quedaría en 1 y habría que cambiarla por un recuento propio.

**Sin alcance por responsable en contactos.** `GET /contacts/` publica `locationId`, `query`, `limit`, `startAfter` y `startAfterId`, pero ningún parámetro de asignación. A un integrante limitado a sus datos no se le muestra cifra de contactos, en lugar de mostrarle el total de la cuenta como si fuera suyo. Conversaciones y oportunidades sí admiten `assignedTo` y se piden ya filtradas.

**Ficha editable fuera del chat.** La pantalla de contactos abre la ficha por `contactId`, sin pasar por una conversación. La autorización no puede apoyarse entonces en el índice de conversaciones: se lee primero el registro en la cuenta de la conexión elegida, de modo que un `contactId` de otra cuenta no existe para esa clave, y a un integrante limitado a sus datos se le niega un contacto que no tenga asignado, tanto al abrir como al guardar. El guardado reutiliza el mismo editor que la conversación: sólo viajan los campos cambiados, las etiquetas por sus endpoints propios (nunca por el campo `tags` del update, que según su propia documentación reemplaza todas), y cada intento lleva su clave de petición.

**Calendario y workflows: fuera de alcance.** Por decisión de producto no se replican. Los workflows se siguen gestionando directamente en la cuenta.

## Oportunidades: crear, editar, mover y reasignar 0.17.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Abrir una oportunidad | `POST /opportunities/` | `opportunities.write` | 2021-07-28 |
| Leerla antes de escribirla | `GET /opportunities/{id}` | `opportunities.readonly` | 2021-07-28 |
| Editarla, moverla de etapa o reasignarla | `PUT /opportunities/{id}` | `opportunities.write` | 2021-07-28 |
| Cambiar sólo el estado | `PUT /opportunities/{id}/status` | `opportunities.write` | 2021-07-28 |
| Embudos y etapas que se pueden nombrar | `GET /opportunities/pipelines` | `opportunities.readonly` | 2021-07-28 |

`CreateDto` exige `pipelineId`, `locationId`, `name`, `status` y `contactId`, y admite `pipelineStageId`, `monetaryValue`, `assignedTo` y `customFields`. `UpdateOpportunityDto` no exige nada y admite los mismos campos menos `contactId` y `locationId`: una oportunidad no cambia de cuenta ni de contacto, y Ágora tampoco lo ofrece. Sólo viajan los campos que la persona cambió.

**Permiso nuevo: `opportunities.write`.** Hasta ahora bastaba `opportunities.readonly`. Ver el embudo y cambiarlo pasan a ser capacidades distintas (`viewOpportunities` y `editOpportunities`): sin el permiso de escritura en la clave de la cuenta, el tablero y la ficha no ofrecen ningún botón de edición, en lugar de ofrecerlo y fallar al guardar.

**El estado, por su endpoint.** `status` aparece en los tres esquemas, pero existe además `PUT /opportunities/{id}/status`, el único que publica `lostReasonId`. Cambiar sólo el estado usa ese endpoint; un cambio junto con otros campos va en el update general.

**Enumeración de estado.** `open`, `won`, `lost`, `abandoned` y `all`. `all` es un valor de búsqueda, no un estado real, así que no se acepta al escribir: la interfaz ofrece abierta, ganada, perdida y abandonada.

**Motivos de pérdida: no implementados.** `GET /opportunities/lost-reason` existe y `UpdateStatusDto` admite `lostReasonId`, pero `LostReasonSchema` **no publica ninguna propiedad**, de modo que no hay forma documentada de leer el id ni el texto de cada motivo. Marcar como perdida funciona sin motivo, que el esquema declara opcional. Queda pendiente de comprobar contra una cuenta real qué forma tienen esos registros.

**Embudo y etapa siempre reales.** Toda escritura nombra un embudo y una etapa del directorio de la cuenta: si el navegador manda otra, se rechaza antes de salir. Si no se indica etapa al crear, se usa la primera del embudo. El responsable sólo puede ser un integrante activo importado de esa misma cuenta, y un integrante limitado a sus datos sólo puede asignarse a sí mismo (lo que abre queda a su nombre, para que no deje de verlo).

**Se lee antes de escribir.** Editar una oportunidad empieza por `GET /opportunities/{id}`: así un identificador de otra cuenta simplemente no existe para esa clave, y a un integrante limitado a sus datos se le niega una oportunidad que no tenga asignada. Comparar con el registro recibido es también lo que permite enviar sólo lo que cambió.

**Repeticiones.** Crear no es idempotente: la misma petición dos veces abriría dos oportunidades. Cada intento se guarda con la clave que genera el navegador (`opportunity_action_attempts`), con los mismos estados que las acciones de contacto y el envío de mensajes: una repetición con la misma clave devuelve la primera respuesta, un contenido idéntico mientras el resultado es incierto se bloquea, y un rechazo queda registrado como tal.

**Citas: fuera de alcance.** Agendar cita sale del roadmap por decisión de producto, junto con calendario y workflows. Las citas existentes se siguen viendo en la ficha del contacto, que las lee de los endpoints de contacto, no de los de calendario.

## Ventana, actualización en vivo y notificaciones 0.18.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17` para la parte de la cuenta.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Página siguiente de la bandeja | `GET /conversations/search` con `startAfterDate` | `conversations.readonly` | 2021-04-15 |
| Pase compartido que mantiene la bandeja al día | `GET /conversations/search` | `conversations.readonly` | 2021-04-15 |

**La ventana.** `GET /conversations/search` publica `startAfterDate`, descrito como «debe contener el valor de ordenación del último documento». Con `sortBy=last_message_date` ese valor es el `lastMessageDate` de la última conversación de la página. Aquí hay una discrepancia que conviene anotar: **`ConversationSchema` no publica `lastMessageDate`**, aunque la cuenta real sí lo devuelve y la lista lo viene usando para la hora de cada conversación desde la primera versión. Ágora continúa la lista con ese valor cuando llega, y cuando no llega no ofrece continuar en lugar de inventar una posición. Dos conversaciones pueden compartir el mismo valor: la página siguiente se compara con lo ya cargado y, si no aporta ninguna conversación nueva, la lista se cierra ahí en lugar de pedir la misma página otra vez. La copia de respaldo sigue siendo sólo la primera página sin filtros.

**En vivo sin webhooks, y sin multiplicar peticiones.** El navegador pregunta a Ágora, nunca a la cuenta: un *pulso* devuelve un token calculado sobre lo que la cuenta mostró la última vez, y ese token sólo cambia cuando cambia el contenido (fecha, texto, canal, pendientes o responsable de alguna conversación). Mientras no cambia, ninguna pantalla se recarga y la cuenta no recibe nada.

La cuenta la lee **un solo pase compartido por conexión**, espaciado por el intervalo de esa conexión (mínimo 10 segundos), lo pida quien lo pida. Diez personas sobre la misma subcuenta cuestan lo que una. El pase se programa *antes* de leer, de modo que una cuenta que falla no convierte el pulso en un bucle de peticiones, y si el presupuesto está en pausa el pulso lo dice con cuántos segundos faltan.

Esto **reduce** las peticiones respecto a 0.17.0: el chat abierto preguntaba a la cuenta cada 10 segundos por navegador; ahora pregunta cuando hay algo nuevo, y la consulta periódica queda como red de seguridad cada 60 segundos. La latencia real es el intervalo del pase más el del pulso: entre 10 y 15 segundos.

**Marcar como leída** también mueve el token, así que lo que una persona lee desaparece de los pendientes de las demás sin que nadie recargue.

**Notificaciones (Firebase Cloud Messaging, HTTP v1).** Es un servicio ajeno a la cuenta y su contrato es el de Google, no el de la cuenta: `POST https://fcm.googleapis.com/v1/projects/{project}/messages:send` con un token de acceso obtenido en `https://oauth2.googleapis.com/token` mediante una aserción JWT RS256 firmada con la cuenta de servicio, alcance `https://www.googleapis.com/auth/firebase.messaging`. El aviso lleva `notification.title`, `notification.body`, `data.path` para abrir la conversación y `android.priority: high`.

El pase que descubre el mensaje deja el aviso en cola con una huella del propio mensaje, de modo que el mismo mensaje no se notifica dos veces aunque lo vean varios pases. Quién lo recibe se resuelve al entregarlo, con la misma regla que las pantallas: el propietario recibe todo lo de su espacio, un integrante sin restricción recibe lo de su cuenta, y un integrante limitado a sus datos sólo lo que tiene asignado. Un dispositivo que el servicio declara desconocido (`UNREGISTERED`, 404, `SENDER_ID_MISMATCH`) se borra; un 5xx reintenta con espera creciente y se rinde tras cinco intentos.

La cuenta de servicio vive fuera de `public_html`, su ruta está en la configuración privada y nunca entra en Git ni llega al navegador. Sin ella Ágora no notifica y lo dice.

**Lo que no se ha podido comprobar aquí.** La entrega está probada contra un servicio simulado con esa forma de petición y un JWT real firmado con una clave generada en la prueba, pero **no contra Firebase ni contra un teléfono**: este entorno no tiene acceso a `googleapis.com` ni el SDK de Android. Queda pendiente una prueba de extremo a extremo con el proyecto real.

## Avisos en el teléfono sin tienda de aplicaciones 0.19.0

Esta versión no añade ningún uso nuevo de la cuenta: los endpoints y scopes son los mismos de 0.18.0. Lo que cambia es quién dispara el pase compartido y por dónde viaja el aviso.

**El pase, también desde el reloj.** Hasta ahora el pase que lee la cuenta lo disparaba el pulso del navegador, de modo que con todas las aplicaciones cerradas nadie leía nada y ningún aviso llegaba a existir: justo el caso para el que sirven las notificaciones. El cron de cada minuto ejecuta ahora ese mismo pase (`Pulse::sweep`), con el mismo bloqueo, el mismo intervalo mínimo y el mismo respeto del presupuesto. Peor caso con todo cerrado: unos 60 segundos entre el mensaje y el aviso.

**Web Push (RFC 8291 y RFC 8292), sin Firebase ni APK.** El navegador se suscribe a su propio servicio —Google en Android, Apple en iPhone, Mozilla en Firefox— y entrega una dirección y dos claves. El servidor guarda una pareja de claves propia (P-256), creada sola la primera vez y fuera de `public_html`: la mitad pública es la que el navegador necesita para suscribirse, la privada firma cada envío.

Cada aviso se cifra para esa suscripción concreta: clave efímera por mensaje, secreto compartido ECDH con la clave del navegador, derivación HKDF-SHA256 y AES-128-GCM, con la cabecera `aes128gcm` que indica la especificación. El servicio que lo reenvía no puede leer ni el nombre ni el texto. La prueba automática lo verifica desde el lado receptor: descifra el aviso con la clave de la suscripción y comprueba que sale exactamente lo enviado, acentos incluidos, y además valida la firma del envío contra la clave pública.

Una suscripción que el servicio declara desconocida (400, 401, 403, 404 o 410) se borra; un 5xx se reintenta con la misma espera creciente que ya había.

**iOS.** Funciona desde iOS 16.4 y **sólo** con la aplicación añadida a la pantalla de inicio desde Safari. El botón detecta ese caso y explica cómo instalarla en lugar de pedir el permiso: en iPhone un permiso denegado no se puede volver a pedir, así que gastarlo sería el peor resultado posible. Todo aviso se muestra —no hay envíos silenciosos—, que es además lo que Apple exige.

**Android.** Funciona instalada o como pestaña. Las capas de ahorro de batería de algunos fabricantes pueden retrasar la entrega, igual que a cualquier aplicación.

**Lo que no se ha podido comprobar aquí.** La criptografía y la firma están verificadas de extremo a extremo contra un receptor real implementado en la propia prueba, pero el envío no ha salido a un servicio real ni ha llegado a un teléfono: este entorno no tiene salida a esos servicios. Queda la comprobación con dos teléfonos, que es corta.

## Rentabilidad del mes y total de contactos 0.20.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Lo cerrado del mes | `GET /opportunities/search` con `status=won`, `date` y `endDate` | `opportunities.readonly` | 2021-07-28 |
| Total de contactos | `POST /contacts/search` | `contacts.readonly` | 2021-07-28 |

**Lo cerrado del mes.** La búsqueda de oportunidades publica `status` (`open`, `won`, `lost`, `abandoned`), `date` y `endDate` en formato `mm-dd-yyyy`, `page`, `limit` (máximo 100) y, en `meta.total`, **un total real**. Ágora pide el mes completo página a página hasta un tope de diez, suma el valor de cada oportunidad y agrupa por responsable. Si se alcanza el tope, el tablero lo dice en lugar de presentar la suma como completa.

**El total de contactos estaba mal, y ya está corregido.** `GET /contacts/` publica un campo `count` cuyo significado no documenta; en la cuenta real devuelve **el tamaño de la página**, no el total, de modo que pidiendo una sola fila la cifra salía 1. Ahora se pregunta a `POST /contacts/search`, cuya respuesta tampoco está documentada pero sí trae un total; se acepta el primer valor numérico utilizable de `total`, `meta.total` o `count`, y se descarta cualquier valor que coincida con el tamaño de página pedido. Si no llega ninguno aprovechable **no se muestra cifra**, en lugar de enseñar un número falso.

**Lo que la cuenta no sabe.** Publicidad, costos fijos, porcentaje de comisión y metas no existen en ninguna API: los escribe el propietario por subcuenta y por mes, y se guardan en `board_settings`. Sin ellos el tablero no calcula ganancia ni avance: dice qué falta. Las comisiones se derivan del porcentaje guardado, nunca de un campo remoto.

**Modo foco.** Corregido un fallo propio, sin relación con la cuenta: la preferencia de modo foco se recuperaba al entrar aunque no hubiera conversación abierta, y en un teléfono eso dejaba la pantalla sin lista y sin menú, sin forma de salir. Ahora sólo se aplica al abrir una conversación.

## Avisos sobre la estructura desplegada 0.20.1

Sin cambios en el uso de la cuenta. Fallo propio, documentado porque explica por qué las pruebas no lo vieron.

En el servidor el código vive en `agora-private/src` y el router lo localiza con `$appRoot`. Las tres rutas de avisos añadidas en 0.19.0 cargaban sus clases con la ruta del árbol de desarrollo (`dirname(__DIR__).'/src'`), que sólo existe en este entorno. En producción el archivo no se encontraba y la petición acababa en el error genérico con referencia: activar los avisos era imposible en cualquier teléfono.

Las pruebas HTTP corrían contra el árbol de desarrollo, y la del paquete servía la interfaz con las respuestas de la API simuladas, de modo que ninguna ejecutó PHP con la estructura desplegada. Hay ahora dos defensas: el empaquetador rechaza cualquier router que construya rutas con la estructura de desarrollo, y `tests/deployed-layout.sh` descomprime el paquete tal cual, lo sirve con PHP y base de datos reales y recorre lo que hace un teléfono al activar los avisos. Esa prueba falla con el paquete 0.20.0 —con el mismo error que vio el usuario— y pasa con 0.20.1.

## Publicidad desde la cuenta de Meta y diagnóstico de avisos 0.21.0

Misma fuente oficial y mismo commit `0af86a4cbd48c66a4071c7e509d1079f9f10ed17`.

| Uso | Método y endpoint | Scope publicado | Version |
| --- | --- | --- | --- |
| Lo invertido en Meta en el mes | `GET /ad-publishing/facebook/reporting` | `adPublishing.readonly` | 2021-07-28 |

**Lo que publica.** «Retrieve aggregated Facebook ad reporting metrics for a location». Parámetros obligatorios: `locationId`, `groupBy` (`day`, `week`, `month`), `startDate` y `endDate` en `AAAA-MM-DD`, `type` (`AD_MANAGER` o `INTEGRATION`) y `fields`, una lista separada por comas de `impressions`, `clicks`, `spend`, `cpc`, `cost_per_conversion`, `conversions`, `cpm`, `reach` y `frequency`. El esquema de seguridad dice expresamente que acepta la **clave privada de integración de subcuenta**, que es la que usa Ágora.

**Lo que no publica: la respuesta.** El 200 no tiene esquema. Ágora no supone una forma: recorre la respuesta buscando valores numéricos bajo la clave `spend` y suma los del nivel más alto en que aparecen, de modo que un total junto a sus filas nunca se cuente dos veces. Si no aparece ningún gasto numérico, no hay cifra; nunca un cero. Las pruebas cubren un total suelto, filas sueltas, un total junto a sus filas, y respuestas sin gasto.

**Qué fuente.** Primero `INTEGRATION`, la cuenta publicitaria de Meta conectada a la subcuenta, que es lo que el usuario pidió. Si no informa gasto, `AD_MANAGER`, las campañas creadas desde el gestor de anuncios. Un 401 o 403 significa que la clave no tiene `adPublishing.readonly`, y entonces no se insiste con la otra fuente porque tampoco respondería. Cada caso se nombra en el tablero en palabras, sin nombres técnicos: sin permiso, sin cuenta conectada, sin gasto en el mes, o respuesta ilegible.

**Lo manual pasa a ser lo de fuera.** El campo que antes recogía toda la publicidad a mano recoge ahora sólo «otros gastos de publicidad», lo que no pasa por Meta, y se suma a lo que informa la cuenta. El valor guardado con el nombre anterior se ignora, para que lo escrito a mano antes no se sume dos veces a lo que ahora llega de Meta.

Lo invertido es una cifra de la empresa: a un integrante limitado a sus datos no se le pide ni se le muestra.

**Avisos: dos fallos propios y diagnóstico.** Sin cambios en el uso de la cuenta.

1. El service worker leía el bloque de navegación del aviso como si fuera el aviso, así que mostraba «Ágora / Tienes un mensaje nuevo» en lugar de quién escribía y qué. Ahora una prueba entrega un aviso real al service worker real mediante el protocolo de depuración del navegador y lee lo que mostró; con el service worker de 0.20.1 esa prueba falla.
2. Sólo el pase compartido preparaba avisos. Cualquier otra lectura de la bandeja —una búsqueda o un filtro en otro dispositivo— podía registrar el mensaje nuevo antes que él, y entonces el pase ya no lo veía como nuevo. Ahora el aviso se prepara en el mismo punto en que cualquier lectura registra una conversación.

Para que un aviso que no llega diga dónde se corta, la entrega anota el motivo de cada fallo, los dos crones dejan constancia de cuándo corrieron, y *Conexiones* lo enseña eslabón a eslabón, con un botón que manda un aviso de prueba a los dispositivos de quien lo pulsa, en el acto y sin pasar por los crones.

**Contador en el icono.** Cada aviso lleva lo pendiente de quien lo recibe, y la aplicación abierta lo actualiza con cada pulso, mediante la API de contadores del navegador. Donde existe —iPhone con la aplicación instalada, escritorio— aparece el número. Android no implementa esa API: allí el icono muestra el punto del sistema cuando hay un aviso sin leer, que depende de que el aviso llegue.

## Por qué los avisos no salían con mensajes reales 0.21.1

Sin cambios en el uso de la cuenta. Tres fallos propios, encontrados con el diagnóstico de 0.21.0 y la captura del usuario: el aviso de prueba llegaba, pero con el texto genérico, y el panel decía que ninguno de los dos crones había dejado rastro y que había cinco avisos pendientes.

**1. El service worker nuevo no llegaba a tomar el relevo.** Antes de activar una versión nueva, la aplicación espera a que no haya texto sin enviar. La comprobación contaba cualquier campo con contenido, incluidos los ocultos: el formulario de entrada, que después de iniciar sesión sigue guardando el correo y la contraseña, y el selector de mes del tablero, que siempre tiene un valor. En la práctica la aplicación creía que siempre se estaba escribiendo, y el teléfono se quedaba con el service worker de 0.20.1, que mostraba el texto genérico. Ahora sólo cuenta el texto visible que la persona cambió. Una prueba reproduce el caso —entrar con usuario y contraseña, ir al tablero— y comprueba que la versión nueva ya no queda bloqueada, mientras que un mensaje a medias sí la sigue reteniendo.

**2. Los avisos dependían de un segundo cron.** La entrega la hacía `send-push.php`, y el panel mostraba que nunca había corrido. Ahora `sync-inbox.php`, el cron que ya revisa los mensajes, entrega en la misma pasada lo que encuentra. Basta con un cron. El otro sigue funcionando si está puesto, y comparten candado, así que nunca envían el mismo aviso dos veces. Además, cuando hay avisos esperando, cualquier pantalla abierta los entrega en el acto después de responder, si el servidor permite cerrar la conexión antes de terminar (`litespeed_finish_request` en LiteSpeed). Si no lo permite, no se hace ahí y nunca se retrasa la respuesta.

Los dos crones aplican también las actualizaciones pendientes de la base de datos antes de empezar, y dejan constancia de su paso antes de cualquier salida anticipada. Así el rastro no puede faltar porque una tabla todavía no exista.

**3. Un rechazo se tomaba por un teléfono desaparecido.** Ante un 400, 401 o 403 Ágora borraba el dispositivo. Según RFC 8030, sólo 404 y 410 dicen que una suscripción ya no existe. Los otros son rechazos de la petición o de la firma, y el teléfono sigue siendo válido. Ahora sólo esos dos lo quitan. Los demás se anotan como envío rechazado y se reintentan. Lo destapó la prueba de la estructura desplegada, cuyo proxy responde 403.

## Revisión desde fuera cuando el cron del servidor no corre 0.21.2

Sin cambios en el uso de la cuenta.

El diagnóstico de 0.21.1 lo dejó claro: en el servidor del usuario el cron de revisión **no ha corrido nunca**, y la entrega aparecía activa sólo porque desde 0.21.1 la hace también una pantalla abierta. De ahí el síntoma: los avisos llegaban únicamente al abrir Ágora, porque era la propia app abierta la que descubría el mensaje y lo enviaba.

No hay forma de corregir desde aquí un cron de CyberPanel que no se ejecuta, así que la revisión ya no depende de él. Ahora existe una **dirección secreta** que hace la misma pasada al visitarla. Un servicio de cron externo, por ejemplo cron-job.org, la llama cada minuto. Funcionamiento:

- La crea el propietario desde Conexiones. Son 256 bits aleatorios. Se muestra una sola vez y en el servidor sólo queda su huella SHA-256, que se compara en tiempo constante.
- Si nadie la ha creado, o si se llama con otra, responde 404, como si no existiera.
- No devuelve nada de la cuenta, sólo si la pasada se hizo. No necesita sesión, y por eso el router la atiende antes de abrirla.
- Una llamada a menos de 20 segundos de la anterior no repite el trabajo, de modo que no se puede usar para multiplicar las lecturas de la cuenta.
- Comparte candado con el cron del servidor, así que si los dos funcionan nunca corren a la vez.
- Cambiarla retira la anterior. Desactivarla la borra.

La pasada está ahora en una sola clase, `Sweep`, que llaman tanto `bin/sync-inbox.php` como la dirección. El rastro indica cuál de los dos la hizo, y el panel lo muestra («desde el servidor» o «desde la dirección de revisión»).

## Avisos en segundos y con contenido 0.21.3

Sin cambios en el uso de la cuenta: la misma búsqueda de conversaciones, más a menudo.

**Por qué tardaba.** La revisión respetaba el «intervalo» de la conexión, un ajuste heredado de la sincronización antigua que vale 120 segundos por defecto, y la conexión del usuario lo tenía en 120. Aunque el servicio externo llamara cada minuto, la cuenta sólo se leía cada dos. Ahora la revisión lee cada 10 segundos, sea cual sea ese ajuste. Es una búsqueda por conexión cada 10 segundos, frente a las 20 cada 10 que admite el presupuesto interno, que ya es la quinta parte del límite publicado.

**Por qué un minuto seguía siendo mucho.** Un servicio de cron por web llama como mucho una vez por minuto. Ahora cada llamada responde enseguida y se queda revisando cada 10 segundos durante 50, hasta la siguiente. Para eso cierra la conexión antes de seguir trabajando (`litespeed_finish_request` en LiteSpeed). Si el servidor no permite cerrarla, se hace una sola pasada, como antes. Un candado impide que dos bucles corran a la vez, y el cron del servidor hace lo mismo. Latencia esperada con la app cerrada: unos 10 a 15 segundos.

**Qué dice el aviso.** El título lleva quién escribe y por qué canal («Raissa Moreno · WhatsApp»), y el cuerpo, lo que escribió, con cuántos mensajes hay sin leer si son varios. Si a la conversación le falta el nombre, se usa el siguiente dato que haya: nombre completo, teléfono o correo. Un mensaje sin texto dice que llegó un adjunto, y a los correos se les quitan las etiquetas. Antes, cualquier dato ausente producía el texto genérico.

**Service worker atrasado.** La página pregunta al service worker qué versión pinta los avisos. Si no coincide con la de la página, el pie lo dice y pide cerrar y volver a abrir la app. Uno de los fallos anteriores fue justo un service worker que se quedó atrás sin que nada lo mostrara.

## Adjuntos, motivos de fallo e iconos del chat 0.22.0

Sin cambios en el uso de la cuenta.

**Motivo de un envío fallido.** `GetMessageResponseDto` publica `status` con `failed`, `undelivered` y `opt_out` entre sus valores, pero **ningún campo con el motivo**. `MessageMeta` sólo publica datos de llamadas y de correo. Así que, ante un mensaje que no salió, el chat:

- explica el estado en palabras («No enviado», «No entregado», «Se dio de baja»);
- si el mensaje trae un motivo en alguno de los campos que las cuentas reales suelen añadir (`error`, `errorMessage`, `meta.error`, `meta.errorMessage`, `meta.reason`), lo enseña tal cual como «motivo indicado por el canal», recortado y escapado;
- si no trae ninguno, lo dice («la cuenta no indica el motivo») y lista las causas habituales de ese canal, presentadas como habituales y no como la causa cierta. En WhatsApp, la primera es la ventana de 24 horas sin plantilla.

«Volver a escribirlo» devuelve el texto al cuadro de respuesta para revisarlo. No envía nada. A quien se dio de baja no se le ofrece.

**Adjuntos.** Se siguen aceptando sólo enlaces `https`, y el nombre sale de la ruta del archivo. Las imágenes se ven en miniatura con su proporción, con un estado de carga, y al tocarlas se abren en un visor a pantalla completa, con enlace al original. Si no cargan, se sustituyen por un aviso y un enlace en vez de dejar un hueco. Audio y vídeo se reproducen en el propio chat; los documentos muestran su nombre y un botón para abrirlos.

## Texto del aviso desde el propio mensaje 0.22.1

**Síntoma.** El aviso llegaba con quién y por qué canal, pero el cuerpo decía «Envió un archivo o un mensaje sin texto» aunque el contacto hubiera escrito texto.

**Causa.** El cuerpo salía de `lastMessageBody` de la búsqueda de conversaciones (`GET /conversations/search`). Leída cada 10 segundos, la conversación aparece ya con `unreadCount` subido mientras ese resumen todavía no trae el texto: el índice de búsqueda va por detrás del mensaje.

**Arreglo.** Antes de enviar cada aviso, la entrega lee la primera página de `GET /conversations/{conversationId}/messages` (versión `2021-04-15`, el mismo endpoint y alcance `conversations/message.readonly` que ya usa el historial) y toma el mensaje entrante (`direction: inbound`) más reciente por `dateAdded`. Se normaliza con el mismo lector del historial, que rechaza mensajes de otra conversación o de otra cuenta. Del texto se quitan etiquetas HTML y entidades; sin texto, el adjunto se describe por la extensión de su enlace (foto, nota de voz, vídeo, documento, archivo). Se conserva «· N mensajes sin leer» y se guarda el cuerpo final en la cola, para que el diagnóstico muestre lo que vio el teléfono.

**Coste y fallos.** Una lectura por aviso, por el gateway de siempre (bloqueo por conexión, 20 peticiones por 10 segundos, pausas por cuota) y sin desactivar la conexión ante un 403. Si falla o hay pausa, el aviso sale con el resumen; nunca se retrasa por esto. La notificación de prueba no lee nada.
