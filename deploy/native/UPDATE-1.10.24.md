# Actualizar Ágora a 1.10.24

Arreglo: «Contratos» salía dos veces en el teléfono. Incluye 1.10.17 a 1.10.23.

## Pasos

1. Extraer `agora-update-1.10.24.zip` sobre las carpetas existentes. Si vienes de 1.10.20 o anterior: copiar también el `.htaccess` (ruta `/contratos`) y reiniciar OpenLiteSpeed; el campo de la base se crea solo.
2. El pie debe decir **v1.10.24**.

## Qué cambia

- **Arreglado:** en el teléfono, «Contratos» aparecía en la barra de abajo además de dentro de «Más». Ahora va solo en «Más» (en el ordenador sigue en la barra lateral). La regla que lo ocultaba se perdió en 1.10.22.
- La prueba `tests/contracts-ui.py` ahora lo comprueba.
