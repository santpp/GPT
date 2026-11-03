# Rutas del servidor para las pantallas nuevas (1.11.1)

El paquete de pantallas (Agora Digital App) pide estas rutas (las de usuarios y roles **no existen**: el equipo se maneja en GoHighLevel, como siempre). Aquí está lo que hace cada una **de verdad** en el servidor
de Ágora y dónde se aparta de lo que decía `API.md`. Todas cuelgan de `/api/workspace/…`, llevan sesión y (las que
escriben) `X-CSRF-Token`. El código está en `src/workspace-extra.php` (las de todo el espacio) y `src/live-inbox.php`
(las de una subcuenta); la lógica, en `src/Audit.php`, `WorkspaceTasks.php`, `Reminders.php`, `OperationsReport.php`,
`Branding.php`, `BusinessSummary.php` y `Roles.php`.

| Ruta | Qué devuelve | De dónde sale |
|---|---|---|
| `GET tareas` · `POST tareas` · `POST tareas/{id}/estado` | Tareas del mes y las vencidas | Base de Ágora (`workspace_tasks`). Quien sólo ve lo suyo, lo suyo; quien supervisa una subcuenta, las de las suyas. |
| `POST/DELETE reminders` | El recordatorio de una conversación | Base de Ágora. Crea o actualiza **su tarea**; al mandar un mensaje se cumple solo. La bandeja devuelve `reminders` (los de quien mira). |
| `GET dia` | Operación del día | Conversaciones que Ágora ya guarda (sin gastar cuota) + una lectura de los 100 contactos más recientes, guardada 5 minutos. |
| `GET eficacia` | Tiempo de cierre, ticket, velocidad, por ejecutivo | Las ventas ganadas del mes (`WonSales`), con la fecha de creación y el embudo. |
| `GET ads` | Inversión en anuncios | La cuenta publicitaria conectada (la misma lectura del tablero), guardada 20 minutos. Sin cuenta: `not_connected`; sin permiso: `permission`. |
| `board-month` → `team[].leads` | Leads del mes por persona | Un conteo por persona, guardado 15 minutos. |
| `GET auditoria` · `POST auditoria/depurar` | Quién tocó qué; y borrar lo de hace 6 meses o más | `audit_log`. **Sólo la persona propietaria.** Se agrega; lo único que se borra es lo de seis meses o más (la fecha de corte la pone el servidor). |
| `GET/POST branding`, `GET branding/logo` | Nombre y logo del espacio | `workspace_branding`. El logo admite PNG, JPG, WebP y **SVG** (el SVG se reescribe sólo con lo que dibuja y se sirve en un contenedor sin permisos). |
| `POST admin/demo/reset` | Reinicia la subcuenta de demostración | Sólo la persona propietaria. |
| `GET resumen` | El pulso del negocio | Espacios y propietarios reales; **cobros, planes y egresos en cero** (viven en el sistema de contratos). |

## Dónde se aparta de `API.md`

- **Tiempo de respuesta (SLA):** se mide sobre lo que está **esperando respuesta ahora** (la conversación cuyo último mensaje es del
  cliente), no sobre la primera respuesta ya dada, porque ésa exige leer los mensajes de cada conversación. El informe lo dice
  (`slaBase: "pendientes"`).
- **«Frente a ayer»:** sale de la foto del día anterior que Ágora guarda cada vez que se mira el día (`daily_metrics`). El primer día
  no hay comparación y el campo viene `null`.
- **Leads por fuente y persona del día:** de los 100 contactos más recientes; un día con más de 100 leads queda corto.
- **Leads del mes por persona:** cuentan por fecha de **entrada**, no de asignación (la cuenta de origen no publica esa fecha).
- **Meta de cierre y tasa de cierre:** `null` (no hay dónde fijarlas todavía); la pantalla muestra «—».
- **Anuncios:** impresiones, clics y conversiones salen de la cuenta publicitaria si las informa; presupuesto, coste por lead,
  retorno y campañas van `null` / vacíos.
- **Día:** se cuenta en la zona `APP_TIMEZONE` (por defecto `America/Caracas`), no en UTC.
- **Roles:** no se gestionan aquí. Quien entra con el correo de su subcuenta trae el rol que le da esa subcuenta; el servidor lo **deduce** (administra la subcuenta → Administración; sólo ve lo suyo y responde → Ejecutivo; ve todo → Supervisión; no responde → Consulta) y lo usa para las tareas.
- **Logo:** hasta 400 KB. SVG como siempre, pero reescrito: se quitan scripts, controladores de eventos, enlaces de afuera y referencias externas.
## Auditoría: qué se anota

Inicio y cierre de sesión (cuenta con clave y correo de subcuenta), mensajes enviados, edición de la ficha de un contacto (con lo que
tenía y lo que quedó), oportunidades creadas y editadas, asignación de conversaciones, recordatorios, identidad del espacio, el modo de mantenimiento y el borrado de registros antiguos. Un fallo al anotar nunca estropea lo que la persona hacía.

## Subcuenta de demostración

«Demo · Academia Horizonte» se crea sola al aplicar las migraciones (una vez, en el primer espacio). Es una conexión marcada `is_demo`: la
pasarela le da un cliente que **no sale a la red** (`src/DemoSpace.php`), con datos ficticios: 4 personas de equipo, 8 conversaciones por
WhatsApp, Instagram, Facebook, SMS y correo, un embudo con 6 oportunidades abiertas, 5 ventas ganadas del mes (2 sin monto, para enseñar la
alerta) y gasto de anuncios inventado. Lo que se haga en ella (mandar un mensaje, editar una ficha, cerrar una venta) se recuerda, y
`POST admin/demo/reset` o `php bin/reset-demo.php` la dejan como al empezar. Sólo la ve la persona propietaria (el resto del equipo ve
sólo las subcuentas a las que pertenece).

## Cuánto consulta Ágora a la cuenta de origen (y por qué respeta sus límites)

La cuenta de origen permite 100 peticiones cada 10 segundos y 200 000 al día por subcuenta. Ágora usa como tope propio 20 cada 10 s y 50 000 al día,
y se detiene al leer sus cabeceras de cuota. Las pantallas nuevas casi no consultan: el día usa lo que ya está guardado; los contactos recientes
se leen a lo sumo cada 10 minutos, los anuncios cada 30 y los leads por persona cada 15; las ventas del mes y los embudos ya tenían su caché.

## Lo que no existe en este servidor

`/api/yo`, `/api/contratos`, `/api/registro` y el resto del panel de **Contratos**: son de otro sistema. Las pantallas de Contratos
dan error hasta que ese servidor esté detrás del mismo dominio.
