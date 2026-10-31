# Actualizar Ágora a 1.5.0

Pulido de la interfaz: pantallas de carga con esqueletos animados.

## Pasos

1. Extraer `agora-update-1.5.0.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.5.0**.

## Qué cambia

- En lugar de «Cargando…», se ve la forma de lo que va a aparecer (filas, burbujas, tarjetas) con un brillo suave.
- El tablero dice «actualizado a las 20:00» con un punto verde.
- Las listas y los mensajes entran con una transición corta, y los botones responden al pulsarlos.
- Si el dispositivo tiene activado «reducir movimiento», no hay animaciones.

## Aspecto más sobrio

- Títulos de sección normales (antes en mayúsculas separadas) y sin el punto verde del tablero.
- Las cifras del tablero van en una franja con separadores finos, sin cajas, y sin los textos de relleno bajo cada número.

## Animaciones más pausadas

- Las entradas y los esqueletos duran más (medio segundo o algo más) y se escalonan. Los refrescos automáticos no repiten la animación.

## Movimiento dentro de la conversación

- En el teléfono, al abrir una conversación o su ficha, el panel entra deslizando; al volver, la lista entra por el otro lado.
- Al recibir o enviar un mensaje, la pantalla baja suavemente hasta él. Al abrir una conversación va directo al último mensaje.
- Con «reducir movimiento» activado en el dispositivo, todo sigue quieto.

## Ficha del contacto

- Los campos personalizados se ven y se editan agrupados por las carpetas de la cuenta, en su orden. Los que no tienen carpeta van al final, en «Otros campos». Si la cuenta no entrega las carpetas, el editor lo avisa y los campos salen sin agrupar.
- Al pulsar «Añadir» en Etiquetas aparecen las etiquetas que ya existen; se filtran al escribir y se añaden con un toque. Para una nueva, se escribe y se pulsa «Añadir».
