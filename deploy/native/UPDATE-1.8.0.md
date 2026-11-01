# Actualizar Ágora a 1.8.0

Conversaciones sin respuesta y nuevos avisos.

## Pasos

1. Extraer `agora-update-1.8.0.zip` sobre las carpetas existentes. La base de datos se actualiza sola (columnas nuevas).
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.8.0**.

## Qué cambia

- **Pestaña «Sin respuesta»** en Conversaciones: las que esperan respuesta (lo último lo escribió el cliente), las más antiguas primero, con cuánto llevan esperando. En el **tablero**, la tarjeta «Esperando respuesta» abre esa lista.
- **Aviso «Te asignaron: …»** a la persona a la que se le asigna una conversación (desde Ágora o desde la cuenta).
- **Recordatorio de mensaje sin leer:** a los 10 minutos (por defecto) de un mensaje sin leer se avisa otra vez a quien tiene la conversación asignada; si no tiene a nadie, a los administradores de la subcuenta y al propietario.
- **Para el propietario:** el tiempo del recordatorio se cambia por subcuenta en **Conexiones → Comisión, moneda y avisos** (0 lo desactiva).
- Los avisos siguen llegando sólo a quien corresponde: cada persona recibe lo de sus conversaciones asignadas; los administradores de una subcuenta, lo de su subcuenta; el propietario, todo.
