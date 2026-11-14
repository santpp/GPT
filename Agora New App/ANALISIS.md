# Agora New App · análisis del ERP 2.1.19

## Estado (2.3.1 · 14 de noviembre de 2026)

| | |
|---|---|
| Paquete de producción | `agora-new-app-2.3.1.zip` (+ `.sha256`), con `tools/package.py`. Sin modo pruebas, datos sintéticos, demos ni logos de clientes |
| Paquete de demostración | `agora-cyberpanel-2.1.19-demo.zip` (el original, con datos inventados). Solo para enseñar |
| Instalación | `INSTALAR-CYBERPANEL.md`, paso a paso. Cuentas de dueño solo por terminal (`servidor/bin/crear-dueno.php`) |
| Cuenta en vivo | Conexión por subcuenta (token cifrado, Location ID, «Probar conexión»). Con conexión, bandeja, mensajes, envío, asignación, contactos, oportunidades, embudos, ganadas y tablero los atiende el **motor de Ágora Mobile 1.10.14** (`servidor/motor/`, puente `app/src/Core/MotorMobile.php`) |
| Consumos | Agencia → Consumos (solo dueños): token de agencia y Company ID cifrados |
| Pruebas | `tests/ghl-conexion.php` (16), `tests/motor-puente.py` (18, con la cuenta simulada `tests/fake-ghl.py`), recorrido de los 27 módulos con dueña, administradora, ejecutivo y una subcuenta sin conexión, e instalación en limpio del paquete |
| Pendiente | Probar con un token real · actualizar la interfaz de la bandeja a la de Ágora Mobile 1.10.14 (el ERP trae una versión anterior empaquetada en `agora-modulos.js`) · avisos al teléfono · anuncios y redes |

Desarrollo local: `APP_ENV=development AGORA_GHL_FAKE=http://127.0.0.1:8099 AGORA_CONFIG=/ruta/config.php php -S 127.0.0.1:8090 -t "Agora New App/public_html" "Agora New App/dev-router.php"` y `python3 "Agora New App/tests/fake-ghl.py" 8099`.

---


Análisis del paquete `agora-cyberpanel-2.1.19.zip` (13 de noviembre de 2026). El código está tal cual llegó en `public_html/` y `servidor/`. No se ha modificado nada. Instalación: `INSTALAR-CYBERPANEL.md`.

## 1. Qué es

| Parte | Cómo está hecha |
|---|---|
| Servidor | PHP 8.3 sin framework, con espacios de nombres (`Agora\Controllers`, `Core`, `Security`, `Services`). Una sola entrada `public_html/api/index.php` y un enrutador (`Controllers/Api.php`). |
| Base de datos | MySQL 8 / MariaDB 10.11 (`utf8mb4`, InnoDB). Tres migraciones con control de checksum. Además, un **modo pruebas con SQLite** que arranca solo si no existe `config.php`. |
| Almacenamiento | Pocas tablas fijas (`agora_tenants`, `agora_users`, `agora_assignments`, `agora_sessions`, `agora_audit`, `agora_jobs`, `agora_signatures`, `agora_idempotency`). Casi todo lo demás vive en dos tablas genéricas con JSON: `agora_records` (tipo + id + datos) y `agora_settings`. |
| Interfaz | Sin framework ni compilación. `agora-app.js` (800 KB, 8.600 líneas) + `agora-modulos.js` (280 KB) + 5 hojas de estilo (~400 KB). Fuentes Author e Inter. Asistente visual «Athena». |
| Despliegue | CyberPanel + OpenLiteSpeed, sin Docker. `servidor/` va al lado de `public_html/`, nunca dentro. Cron cada minuto (`bin/worker.php`). |

**Organización:** una agencia con subcuentas. Roles: MASTER (dueño), SUPERADMIN, ADMIN, SUPERVISOR, EJECUTIVO y otros. Hay una matriz de permisos por acción (`Security/Auth.php`) y asignaciones explícitas de usuarios a subcuentas.

**Módulos (22):**
- **Ingresos:** Gestión Comercial (reporte del mes), ARP Comercial, Ventas, Facturación, Catálogo, Productos y servicios.
- **Clientes:** Bandeja de conversaciones, Contactos, Oportunidades, Tareas.
- **Gestión:** Equipo (ventas, cobranza, comisiones), Embudo y publicidad, Costos, Tendencias, Calidad de datos.
- **Contratos:** Contratos (convenios de pago, PDF, firma por enlace), Con convenio, Registro.
- **Administración:** Auditoría, Configuración mensual, Usuarios y roles, Ajustes, Personalizar, Migración (Excel y carga masiva), Ayuda.

## 2. Cómo está la conexión con GoHighLevel

Casi no existe:
- El **token** de cada subcuenta se escribe **en texto plano** en `servidor/config/config.php` (`ghl.tenants[ID]`).
- Solo **envía** mensajes, mediante una cola (`agora_jobs`) que procesa el cron. Antes de enviar comprueba que el contacto es de esa subcuenta, y si el resultado es dudoso pasa a revisión manual y no reenvía.
- **No sincroniza nada**: la bandeja, los contactos y las oportunidades se leen de la base local, cargada a mano por importación JSON o Excel. El propio manual lo dice: «La sincronización automática … no está implementada».
- Plantillas de WhatsApp, avisos al teléfono, huella (passkeys), entrada por código y webhooks responden «requiere configuración adicional».

Dato importante: **la API del ERP ya usa los mismos nombres de ruta que Ágora Mobile** para la bandeja (`workspace/inbox`, `workspace/messages`, `workspace/send`, `workspace/assign`, `workspace/pulse`, `workspace/won-alert`, `workspace/board`…). Su bandeja se copió del flujo de Ágora 1.x. Por eso el motor de Ágora Mobile puede ponerse detrás de esas rutas sin rehacer la interfaz.

## 3. Qué mantener, qué sustituir y qué quitar

### Mantener del ERP
- **Agencia → subcuentas**, asignaciones, roles y matriz de permisos.
- **Migraciones con checksum**, tabla de idempotencia (pulsar dos veces no repite la acción), auditoría y cola de trabajos con revisión manual.
- **Módulos de gestión** que no dependen de GoHighLevel: Gestión Comercial, Ventas, Costos, Tendencias, Calidad de datos, Configuración mensual, Equipo, Catálogo y Productos, Personalizar, tablas editables, Excel y Migración.
- **Contratos:** cálculo en centavos, PDF, firma por enlace con hash y caducidad, y revocación.
- **Diagnóstico de arranque** (`/api/status` dice qué falla) y la guía de instalación en CyberPanel.
- **Datos sintéticos de 48 meses**, solo para pruebas.

### Sustituir por el motor de Ágora Mobile (lo que ya funciona en nueva.agoradigital.app)

| Pieza de Ágora Mobile | Para qué |
|---|---|
| `TokenVault` | Tokens **cifrados** por subcuenta, escritos desde la pantalla y nunca mostrados (en vez de texto plano en `config.php`) |
| `GhlClient` + `GhlGateway` | Llamadas a la cuenta con control de cuota, esperas y reintentos |
| `InboxFeed`, `MessageFeed`, `MessageHistory`, `MessageSender`, `ConversationAssignment`, `ConversationNetworks`, `PushQueue`/`PushDelivery`, `Pulse`, `Sweep` | **Flujo completo de Conversaciones**: lista en vivo, filtros (Todas, No leídas, Sin respuesta, Mías), canal, búsqueda por nombre/teléfono/correo, historial, envío por el canal correcto, plantillas de WhatsApp, asignar, responsable visible, avisos al teléfono, recordatorios |
| `ContactProfile`, `ContactActions`, `ContactActivity`, `ContactDirectory`, `ContactFieldDirectory` | Ficha en vivo, edición, notas, tareas, citas, campos personalizados |
| `OpportunityBoard`, `OpportunityActions`, `OpportunityContext`, `PipelineDirectory`, `WonSales`, `Dashboard`, `Board` | Embudos, «Mover a…», ventas ganadas y su alerta, tablero |
| `AgencyWallet` | Consumos de la agencia |
| `Passkeys`, `DeviceSession`, `ExternalAuth`, `ViewAs` | Huella, sesiones por dispositivo, entrada por código, «Entrar como» |
| Reglas de asignación | Cada vendedor ve solo lo asignado, con las listas pedidas a la cuenta ya filtradas |

### Revisar o quitar antes de producción
- **Modo pruebas SQLite**: entra con `admin` / `123456789` y `/api/status` **devuelve esa clave**. Solo se activa sin `config.php`, pero un servidor que pierda ese archivo queda abierto. Propuesta: quitarlo del paquete de producción.
- **`demo.js` y `demo-capas.js`** (170 KB de datos inventados) se publican en `public_html/`.
- **Logos y nombres de clientes reales** (ASOCIM, StreamingNet) en los datos de prueba y en `assets/`.
- **Inter** (875 KB en TTF) junto a Author: la guía de diseño usa solo Author.
- **Un solo JS de 800 KB**: difícil de mantener. Conviene dividirlo por módulos, como en Ágora Mobile.
- **Cookie de sesión `SameSite=Lax`** (en Ágora Mobile es `Strict`).
- **Cabeceras de seguridad:** la guía ya explica que OpenLiteSpeed ignora `Header` en `.htaccess` y que van en el vHost. Encaja con lo previsto para la V2.1.

## 4. Qué datos puede dar GoHighLevel a cada módulo

Según la documentación local de la API v2 (alcances y webhooks). Antes de construir cada módulo hay que confirmarlo con el token real de una subcuenta.

| Módulo del ERP | Qué ofrece la cuenta | Alcances |
|---|---|---|
| Bandeja | Conversaciones y mensajes de todos los canales, envío, no leídos, asignación. Webhooks de mensaje entrante y saliente | `conversations.*`, `conversations/message.*` |
| Contactos | Contactos, campos personalizados, etiquetas, notas, tareas, citas, «no molestar» | `contacts.*`, `locations/tags.*`, `locations.readonly` |
| Oportunidades / Ventas | Embudos, etapas, oportunidades (estado, monto, responsable, fecha de cambio de estado) | `opportunities.*` |
| Tareas | Tareas por contacto y de la subcuenta | `contacts.*`, `locations/tasks.readonly` |
| Facturación / cobranza | Facturas, facturas recurrentes, pagos, órdenes, transacciones, suscripciones | `invoices.*`, `invoices/schedule.*`, `payments/*` |
| Catálogo / Productos | Productos y precios (cobro único o recurrente) | `products.*`, `products/prices.*` |
| Equipo | Usuarios de la subcuenta y su rol; ventas y conversaciones por responsable | `users.readonly` |
| Citas | Calendarios y eventos | `calendars.*`, `calendars/events.*` |
| Embudo de captación | Formularios, encuestas, fuente y atribución del contacto | `forms.readonly`, `surveys.readonly` |
| Consumos | Saldo y movimientos de la agencia (ya hecho en Ágora Mobile) | token de agencia |
| Objetos personalizados | Esquemas y registros propios de la subcuenta | `objects/*` |

**Corrección (13 de noviembre): anuncios y redes sí tienen API**
- **Ad Manager** (Facebook, Google y LinkedIn): rutas `ad-publishing/.../reporting` con métricas por cuenta y por campaña (impresiones, clics, gasto…). Requisito: la cuenta publicitaria conectada en el Ad Manager de esa subcuenta. Google solo cubre campañas de Búsqueda.
- **Social Planner**: `POST /social-media-posting/statistics` con estadísticas de las redes conectadas (LinkedIn solo páginas, TikTok solo cuentas Business).
- Los **widgets** del panel de la cuenta no se exponen como tales; sus datos salen de esas mismas rutas.
- Pendiente: confirmar con un token real las versiones de cabecera y los campos exactos. «Probar conexión» ya dice si el token puede leer redes y anuncios.
- `campaigns.readonly` son campañas de correo y SMS, no publicidad.
- **Costos, tendencias, configuración mensual y contratos**: son datos propios del ERP.

## 5. Plan propuesto

1. **Estructura** (hecho): `Agora Mobile/` (release 1.10.14) y `Agora New App/` (este ERP).
2. **Base**: arrancar el ERP en local con MySQL (sin SQLite) y medir su estado actual. Quitar el modo pruebas, las demos y los datos reales del paquete de producción.
3. **Motor de la cuenta**: traer `TokenVault`, `GhlClient` y `GhlGateway` al ERP. Token cifrado por subcuenta escrito desde Ajustes.
4. **Conversaciones**: poner el motor de Ágora Mobile detrás de las rutas `workspace/inbox|messages|send|assign|pulse|…` que ya usa el ERP, con el mismo flujo de Ágora Mobile.
5. **Contactos, Oportunidades, Ganadas y Tablero** en vivo, con las reglas de asignación.
6. **Facturación y Productos** desde facturas, pagos y productos de la cuenta.
7. **Avisos, huella, sesiones por dispositivo, «Entrar como» y Consumos**.
8. **Diseño**: aplicar `docs/DESIGN.md` (paleta, escala, toque de 44 px) a la interfaz del ERP.

### Dos caminos posibles

| | **A. Backend del ERP + motor de Ágora Mobile** (recomendado) | **B. Backend de Ágora Mobile + interfaz del ERP** |
|---|---|---|
| Base | El servidor del ERP (MySQL, roles, contratos, tablas, migración…) | El servidor de Ágora Mobile, ampliado con las rutas que pide la interfaz del ERP |
| Qué se trae | Las clases de GoHighLevel de Ágora Mobile (tabla del punto 3) | Los módulos de gestión y contratos del ERP |
| Ventaja | Conserva los 22 módulos del ERP; lo que se porta es pequeño y está probado | Ya existe trabajo hecho: la 1.11.x (commit `b98d8ae`, solo en el historial) añadió a Ágora Mobile rutas de esta misma interfaz (tareas, recordatorios, auditoría, resumen, día, eficacia, anuncios, usuarios, identidad) |
| Riesgo | Unir dos estilos de código | Rehacer en Ágora Mobile los módulos de gestión y contratos, que son lo más grande |

El trabajo de la 1.11.x sirve de referencia en cualquiera de los dos caminos.

### Decisiones pendientes
- Dominio y base de datos de la nueva app.
- Si la interfaz del ERP se queda tal cual o adopta el diseño de Ágora Mobile.
- Orden de los módulos tras Conversaciones.
- Si Athena se queda.
