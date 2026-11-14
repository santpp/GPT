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
