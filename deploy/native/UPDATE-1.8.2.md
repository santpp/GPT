# Actualizar Ágora a 1.8.2

Pestaña «Ganadas» y alerta de ventas ganadas sin monto.

## Pasos

1. Extraer `agora-update-1.8.2.zip` sobre las carpetas existentes. La base de datos se actualiza sola (una columna nueva).
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.8.2**.

## Qué cambia

- **Oportunidades tiene dos pestañas:** **Embudos** (igual que antes) y **Ganadas**: las ventas ganadas del mes, el monto vendido y cuántas están sin monto, que salen primero con un campo para escribir el monto ahí mismo.
- **Alerta de ventas sin monto** (monto vacío o cero) del mes en curso:
  - Se consulta 4 segundos después de entrar y cada vez que se edita una oportunidad. No se consulta por sondeo.
  - Al entrar aparece una ventana con las ventas pendientes; si se cierra, queda una franja roja arriba y un contador en la pestaña Oportunidades.
  - Si una venta lleva **7 días** o más sin monto (configurable), la ventana ya no se puede cerrar hasta completarlas.
- **Quién ve qué:** cada vendedor, sus ventas; los administradores de la subcuenta y el propietario, todas, con el resumen por vendedor. En el tablero, los administradores ven «Ventas ganadas sin monto» con el desglose.
- **Para el propietario:** en **Conexiones → Comisión, moneda y avisos**, los días de la escalada por subcuenta (0 = nunca bloquea).
