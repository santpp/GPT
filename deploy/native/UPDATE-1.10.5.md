# Actualizar Ágora a 1.10.5

Arreglos de pantalla en el teléfono: nada se sale del ancho.

## Pasos

1. Extraer `agora-update-1.10.5.zip` sobre las carpetas existentes. No hay migraciones.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.5**.

## Qué cambia

- **Filtros de la bandeja:** «Todas · No leídas · Sin respuesta» caben en una línea y el selector de canales baja a la suya, a todo el ancho. Antes se cortaba («Todos los canale…») y ensanchaba la página, que se corría de lado.
- **Barra de arriba:** título, subcuenta, avisos y cuenta en una sola línea (antes, con dos subcuentas, bajaban a una segunda fila).
- **Menú de abajo:** con siete pestañas (la propietaria con Consumos y Conexiones) las etiquetas se pisaban; ahora se desplaza de lado y la pestaña activa queda centrada.
- **Oportunidades:** el selector de embudo ocupa el ancho (antes se cortaba), la tarjeta no repite el nombre del contacto cuando es igual al de la oportunidad, y los totales de cada etapa se leen mejor.
- Textos más cortos: «20 de 3775 conversaciones · las más recientes» y «100 oportunidades abiertas · las más recientes».
- Nota: «Martin HernáNdez Rangel» viene así desde la cuenta de origen (una automatización pone la mayúscula tras la «á»); Ágora lo muestra tal cual.
- Prueba nueva: `tests/phone-fit-ui.py` mide, a 360, 372, 390 y 412 px, que nada se sale del ancho.
