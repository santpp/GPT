# Rutas del servidor para las pantallas nuevas (1.11.0)

El paquete de pantallas (Agora Digital App) pide nueve rutas. Aquí está lo que hace cada una **de verdad** en el servidor
de Ágora y dónde se aparta de lo que decía `API.md`. Todas cuelgan de `/api/workspace/…`, llevan sesión y (las que
escriben) `X-CSRF-Token`. El código está en `src/workspace-extra.php` (las de todo el espacio) y `src/live-inbox.php`
(las de una subcuenta); la lógica, en `src/Audit.php`, `WorkspaceTasks.php`, `Reminders.php`, `OperationsReport.php`,
`TeamDirectory.php`, `Branding.php`, `BusinessSummary.php` y `Roles.php`.

| Ruta | Qué devuelve | De dónde sale |
|---|---|---|
| `GET tareas` · `POST tareas` · `POST tareas/{id}/estado` | Tareas del mes y las vencidas | Base de Ágora (`workspace_tasks`). Quien sólo ve lo suyo, lo suyo; quien supervisa una subcuenta, las de las suyas. |
| `POST/DELETE reminders` | El recordatorio de una conversación | Base de Ágora. Crea o actualiza **su tarea**; al mandar un mensaje se cumple solo. La bandeja devuelve `reminders` (los de quien mira). |
| `GET dia` | Operación del día | Conversaciones que Ágora ya guarda (sin gastar cuota) + una lectura de los 100 contactos más recientes, guardada 5 minutos. |
| `GET eficacia` | Tiempo de cierre, ticket, velocidad, por ejecutivo | Las ventas ganadas del mes (`WonSales`), con la fecha de creación y el embudo. |
| `GET ads` | Inversión en anuncios | La cuenta publicitaria conectada (la misma lectura del tablero), guardada 20 minutos. Sin cuenta: `not_connected`; sin permiso: `permission`. |
| `board-month` → `team[].leads` | Leads del mes por persona | Un conteo por persona, guardado 15 minutos. |
| `GET auditoria` | Quién tocó qué | `audit_log`, que **sólo se agrega**. |
| `GET/POST usuarios`, `usuarios/rol`, `usuarios/estado` | La lista única de personas | Cuentas con clave (se crean y cambian aquí) + personas de las subcuentas (se ven, no se editan). |
| `GET/POST branding`, `GET branding/logo` | Nombre y logo del espacio | `workspace_branding` (el logo, en la base, como imagen validada). |
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
- **Roles:** cinco, con la matriz de la pantalla. Las cuentas con clave se guardan con su rol (`administrator`→SuperAdmin,
  `admin`, `supervisor`, `seller`→Ejecutivo, `viewer`→Consulta). Quien entra con el correo de su subcuenta no trae rol guardado:
  se **deduce** (administra la subcuenta → Administración; sólo ve lo suyo y responde → Ejecutivo; ve todo → Supervisión; no responde → Consulta).
  Sólo la persona propietaria crea cuentas y cambia roles; siempre queda al menos una propietaria activa.
- **Clave de una cuenta nueva:** 12 caracteres como mínimo (la regla de siempre en Ágora), no 10.
- **Logo:** PNG, JPG o WebP de hasta 400 KB; **no SVG** (puede llevar código).
- **Cuentas con clave que no son propietarias** siguen sin acceso al espacio de trabajo (como antes): el rol queda guardado, pero
  hoy sólo la propietaria trabaja con esa clave; el resto del equipo entra con el correo de su subcuenta.

## Auditoría: qué se anota

Inicio y cierre de sesión (cuenta con clave y correo de subcuenta), mensajes enviados, edición de la ficha de un contacto (con lo que
tenía y lo que quedó), oportunidades creadas y editadas, asignación de conversaciones, recordatorios, creación y cambios de cuentas,
identidad del espacio y el modo de mantenimiento. Un fallo al anotar nunca estropea lo que la persona hacía.

## Lo que no existe en este servidor

`/api/yo`, `/api/contratos`, `/api/registro` y el resto del panel de **Contratos**: son de otro sistema. Las pantallas de Contratos
dan error hasta que ese servidor esté detrás del mismo dominio.
