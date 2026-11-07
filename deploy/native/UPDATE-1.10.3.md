# Actualizar Ágora a 1.10.3

Consumos sin depuración, con reporte bonito y exportación. Sólo para tu usuario.

## Pasos

1. Extraer `agora-update-1.10.3.zip` sobre las carpetas existentes. No hay migraciones.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.3**. Tu Company ID y tu token siguen guardados.

## Qué cambia

- **Fuera la depuración:** ya no están los botones de prueba, ni GET, ni el saldo (daban 404). Se usa POST con `Version: v3`.
- **Arreglo importante:** la cuenta contesta **201** (no 200) a varias consultas; antes un 201 se contaba como fallo. Ahora cualquier 2xx vale.
- **Vista más cuidada:** tarjetas por subcuenta con plan, gasto, recargas y los tipos principales; la conexión de agencia queda recogida cuando ya está conectada.
- **Reporte de cada subcuenta:** hoja con logo, totales, en qué se gasta (con barras), día por día y todos los movimientos del mes (hasta 1000).
- **Exportar:** «Descargar .txt» y «Guardar como PDF» (abre el diálogo de impresión y se elige «Guardar como PDF»; siempre sale en claro, aunque uses el modo oscuro). Se puede exportar una subcuenta o todas.
