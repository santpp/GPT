# Actualizar Ágora a 1.10.26

Arreglo: el ícono de la app instalada (logo centrado) se actualiza. Incluye 1.10.17 a 1.10.25.

## Pasos

1. Extraer `agora-update-1.10.26.zip` sobre las carpetas existentes. Si vienes de 1.10.20 o anterior: copiar también el `.htaccess` (ruta `/contratos`) y reiniciar OpenLiteSpeed; el campo de la base se crea solo.
2. El pie debe decir **v1.10.26**.

## Qué cambia

- **Íconos de la app instalada:** el manifiesto ahora apunta a los íconos de la carpeta de cada versión. Cuando el ícono cambia, cambia su dirección y el teléfono lo vuelve a pedir. Antes apuntaba siempre a la misma dirección y se quedaba el viejo.
- Los avisos usan también los íconos de la versión.
- El empaquetador comprueba que el manifiesto y la copia sin conexión sean de la versión que se empaqueta.

## Qué esperar en cada equipo

- **Navegador (favicon de la pestaña) y la app abierta:** al abrirla con la 1.10.26, recarga sola una vez y ya.
- **Android, app instalada desde Chrome:** Chrome revisa el manifiesto al abrir la app (más o menos una vez al día) y cambia el ícono; puede pedir confirmación.
- **iPhone, app agregada a la pantalla de inicio:** iOS no cambia ese ícono nunca. Hay que quitarla y volver a agregarla desde Safari.
- **App nativa de Android:** el ícono va dentro de la app; hace falta instalar una compilación nueva.
