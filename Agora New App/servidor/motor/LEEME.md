# Motor de Ágora Mobile dentro de Agora New App

Copia del servidor de Ágora Mobile **1.10.14** (`src/` y `database/` de la raíz del repositorio).
Agora New App lo usa, a través de `app/src/Core/MotorMobile.php`, para todo lo que habla en vivo con la cuenta
cuando una subcuenta tiene su conexión guardada: bandeja, mensajes, envío, asignación, contactos,
oportunidades, embudos, ventas ganadas, tablero y consumos de la agencia.

- Las tablas del motor (`tenants`, `ghl_connections`, `ghl_sync_state`, `conversation_index`, cachés…) viven en la
  **misma base MySQL** que el ERP; no chocan con las del ERP (que empiezan por `agora_`).
- Todas las subcuentas del ERP son conexiones de un único «tenant» del motor (id 1, la agencia).
  El id de cada conexión es el id de la subcuenta del ERP.
- Cambios respecto al original (buscar «Agora New App» en el código): `src/live-inbox.php` acepta la subcuenta,
  la persona y sus permisos que le pasa el puente, en lugar de calcularlos con las tablas de Ágora Mobile.
- Para actualizar el motor: copiar de nuevo `src/` y `database/` desde Ágora Mobile y volver a aplicar ese cambio.
- 2.4.0: `src/live-inbox.php` avisa al puente cuando se crea o completa una tarea desde la ficha (gancho `alActuar`),
  y `src/GhlClient.php` tiene `updateContactTask` para corregir una tarea. Al actualizar el motor, volver a aplicar los dos.
- 2.6.0: `src/GhlClient.php` tiene `createContact` (crea un contacto nuevo sin tocar los existentes). Volver a aplicarlo al actualizar el motor.
- 2.6.1: `src/OperationsReport.php` (de la línea 1.11 de Ágora Mobile) y su bloque de rutas `workspace/dia|eficacia|ads` en `src/live-inbox.php`. La tabla `daily_metrics` la crea el puente al instalar el motor.
- 2.7.0–2.7.1: `src/GhlClient.php` tiene los métodos `social*` de solo lectura (cuentas, publicaciones y cifras) y `src/GhlGateway.php::execute` acepta `$soft` (un 4xx de una lectura opcional no pausa la subcuenta). Volver a aplicarlos al actualizar el motor.
- 2.8.0: `src/GhlClient.php` tiene `adReportingList` (lista de campañas de Meta del administrador de anuncios). Volver a aplicarlo al actualizar el motor.
- 2.8.17 · `ContactDirectory::recientes()` y parámetro `since`/`tz` en `GET /api/workspace/contacts` (live-inbox.php): contactos dados de alta hoy, en 7 o en 30 días (filtro `dateAdded` range de la búsqueda de contactos).
- 2.9.0 · `src/bootstrap.php`: `respond()` y `respondThen()` llaman a `motorEspejo($body,$status)`, que pasa cada respuesta correcta al puente (`$GLOBALS['motorPuente']['alResponder']`) para guardar el espejo de contactos y oportunidades. Volver a aplicarlo al actualizar el motor.
- 2.9.6 · `src/bootstrap.php`: `respond()` pasa la respuesta por `$GLOBALS['motorPuente']['transformar']` si existe (el ERP fija `canEdit` de las cifras del mes con sus roles). `src/Board.php::team()`: quien administra la cuenta sale en el equipo del mes solo si cerró algo ese mes (así coincide con «Por ejecutivo»). Volver a aplicar ambos al actualizar el motor.
- 2.11.8 · `src/AgencyWallet.php`: `flat()`, `label()`, `normalize($tx, $known)` (busca el identificador de una subcuenta conectada en cualquier dato del movimiento, separa «ref:» de la descripción y guarda `campos` de lo que no tiene subcuenta), `applyRules()` y `hints()`. `src/consumption-routes.php`: rutas `admin/consumption/reglas` (GET, POST, DELETE) y el informe devuelve `pistas`, `porRegla` y `reglas`. Volver a aplicarlo al actualizar el motor.
- 2.11.8 (después del paquete) · `AgencyWallet::label()`: «whatsapp conversation» se leía con doble espacio; ahora «WhatsApp · conversation». Va en el próximo paquete del ERP.

- Agora New App 2.12.13 (pantallas en `public_html/assets/agora-modulos.js`): «¿Venta ganada?» de la Bandeja y de la ficha del contacto llaman a `window.agoraGanar` (mismo paso, luego el formulario de venta del ERP); sin la ventana y la franja de «venta ganada sin monto» cuando existe `window.agoraVentaGanada`.

- Agora New App 2.13.0: `AccessChannel::sendMail()` (correo general por la subcuenta elegida como canal; `deliver(..., onlyChannel)`). Lo usa `MotorMobile::correo()`.
- Agora New App 2.14.0: `respond()` con `$GLOBALS['motorCaptura']` lanza `MotorCaptura` (respuesta capturable) para que los widgets del ERP lean las rutas del motor sin cortar la petición.
- 2.18.3/2.18.4 (Agora New App): agora-modulos.js · «Leads asignados a mí» lleva su nota también como ayuda (title); la nota sigue visible. El filtro del Dashboard (2.18.2) quedó en Ayer, Hoy y Otro periodo.
- 2.18.5 (Agora New App): agora-modulos.js · sin la nota «Los que tienes a tu nombre en la cuenta» bajo «Leads asignados a mí» (pedido del usuario).
- 2.18.6 (Agora New App): agora-modulos.js · «Leads asignados a mí» pasa a ser su propia tarjeta (metric) en el Dashboard; la tarjeta grande muestra solo «Leads de hoy».

- **2.18.11 · `OperationsReport::operationDay()`:** la caché `dia-leads` guarda también `id` y `name` de cada contacto; para quien ve todo el equipo, `sinLeerPorAsesor`, `leadsPorFuente` y `leadsPorUsuario` traen `detalle` (hasta 50 por grupo). A quien solo ve lo suyo (`assignedDataOnly`) no se le manda.

- **2.18.14 · `AccessChannel::template()`:** plantilla nueva de los correos: logo PNG (`public_html/assets/correo/agora-logo-correo.png`, dirección https pública que pasa `MotorMobile::correo()` desde `app_url`; sin https se escribe «Ágora» en texto), texto oculto de vista previa con el código, «Tu código de acceso es» justo antes del código y el código en un bloque que se selecciona entero con un toque. `sendMail()` recibe `$logo` opcional.

- **2.19.1 · Quién envió cada mensaje:** tabla `message_authors` (la crea `MotorMobile::instalar`, versión `motor-1.10.14-dia-autores`). `live-inbox.php` la llena al enviar desde Ágora (`workspace/send`) con el nombre de la persona (de `motorPuente['autor']`) y, al leer `workspace/messages`, devuelve `autores` (id del mensaje → nombre) y `equipo` (usuario de la cuenta → nombre).

- **2.19.2 · Errores con código:** `respond()` pasa todo error (≥ 400 con `error`) por `$GLOBALS['agoraError']` del ERP: agrega `codigo` (diccionario) y `requestId` y lo anota en la bitácora (no en modo captura). `body()` acepta `{}` como sin datos y responde 400 «Los datos enviados no son válidos.» a un JSON roto o una lista, en vez de lanzar una excepción (500).

- **2.20.0 · Correo a clientes:** `AccessChannel::enviarDocumento()` (convenios y facturas): por la conexión indicada, busca o crea el contacto del cliente (etiqueta `agora-documentos`) y envía con la plantilla, ahora con botón opcional (`template(..., $boton)`). El ERP corre `Sweep::run('cron-erp')` desde `servidor/bin/cron.php`.

- **2.22.0 · Personas en la cuenta:** `GhlClient::location()`, `createUser()` y `updateUser()` (permiso `users.write`); `AccessChannel::sendMail()` acepta un botón opcional (`[texto, url]`) para la invitación. Las usa `app/src/Services/UsuariosCuenta.php` con `GhlGateway::execute(..., soft: true)`.
