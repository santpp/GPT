# Actualizar Ágora a 0.22.2

Ágora ya no se queda sin consultas a media mañana, y la interfaz queda más limpia para el despliegue.

## Pasos

1. Extraer `agora-update-0.22.2.zip` sobre las carpetas existentes.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v0.22.2**.

Sin cambios de base de datos ni de configuración. El cron o la dirección externa siguen igual.

## Tope diario de consultas

Ágora tenía un tope propio de 5.000 consultas al día por subcuenta. La revisión cada 10 segundos hace unas 8.640, así que el tope se agotaba entre las 9:00 y las 13:00 de Nueva York y Ágora no leía la cuenta hasta las 19:00 o 20:00. El tope pasa a 50.000, una cuarta parte del límite oficial de 200.000 al día. Ágora sigue obedeciendo las cabeceras de cuota que manda la cuenta.

## Interfaz

- La «Á» de la cabecera es ahora la marca de Ágora, en el mismo color que el logo.
- El menú lateral y la barra de abajo del teléfono usan iconos dibujados, no caracteres de texto.
- Bandeja: el recuento y el selector «Mostrar» ocupan una sola línea; la hora va junto al nombre y es corta («9:46», «Ayer», «lun», «28 oct»). La fecha completa sale al dejar el puntero encima.
- Chat: los mensajes recibidos ya no llevan «Entregado» o «Leído», que sólo tienen sentido en los enviados. Un fallo sí se sigue mostrando. El canal no se repite encima y debajo del cuadro de respuesta.
- Tablero en el teléfono: sin los dos botones grandes del final, que repetían el menú de abajo.
- Contactos: el buscador ocupa todo el ancho y se lee completo.
- Ya no sale el aviso «La caché offline no está disponible en este navegador».
- Cuando algo falla en el navegador (sin red, o un dato que no se puede pintar), se dice en castellano claro en vez de mostrar el error técnico en inglés.
