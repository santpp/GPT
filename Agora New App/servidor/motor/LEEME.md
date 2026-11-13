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
