# Actualizar Ágora a 1.8.3

Alerta de ventas sin monto rediseñada y fecha de las ventas ganadas.

## Pasos

1. Extraer `agora-update-1.8.3.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.8.3**.

## Qué cambia

- **La alerta es más clara:** etiqueta «Pendiente» o «Urgente», barra de progreso, una tarjeta por venta con los días que lleva, campo de monto con su símbolo de moneda, el cursor ya puesto en el primer monto y Enter para guardar. Con varias rellenas aparece «Guardar N ventas». En el móvil la ventana sube desde abajo.
- **Fecha de las ventas ganadas:** una venta cuenta en el mes en que **cambió a ganada**, aunque la oportunidad se hubiera creado antes. Esto afecta a la lista de Ganadas, a la alerta y también al resumen del mes del tablero (ventas, comisiones, ganancia). Si los números de un mes cambian un poco respecto a antes, es por esto.
