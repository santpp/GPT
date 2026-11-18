# Actualizar Ágora a 1.10.25

Arreglo: en el ordenador, Conversaciones, Contactos y Oportunidades ya no tienen «scroll infinito». Incluye 1.10.17 a 1.10.24.

## Pasos

1. Extraer `agora-update-1.10.25.zip` sobre las carpetas existentes. Si vienes de 1.10.20 o anterior: copiar también el `.htaccess` (ruta `/contratos`) y reiniciar OpenLiteSpeed; el campo de la base se crea solo.
2. El pie debe decir **v1.10.25**. Si la pantalla se ve igual que antes, recarga una vez.

## Qué cambia

- **Conversaciones:** la página ya no se alarga con la lista. El texto oculto de «sin leer» de cada conversación se salía de la lista y dejaba un espacio en blanco enorme debajo del chat.
- **Contactos y Oportunidades (ordenador):** ocupan la pantalla como Conversaciones. La lista se desplaza por dentro y la ficha queda a la vista; en Oportunidades, cada columna del tablero se desplaza por su cuenta.
- En el teléfono no cambia nada.
- Prueba nueva: `tests/scroll-ui.py`.
