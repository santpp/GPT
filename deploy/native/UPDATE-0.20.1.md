# Actualizar Ágora a 0.20.1

Arregla la activación de avisos, que fallaba en el servidor, y la tabla del equipo en el móvil.

## Pasos

1. Extraer `agora-update-0.20.1.zip` sobre las carpetas existentes.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla, para que tome la versión nueva. El pie debe decir **v0.20.1**.

Sin cambios de base de datos ni de configuración.

## Por qué no se podían activar los avisos

Fallo mío. En tu servidor el código vive en `agora-private/src`, y el resto de la aplicación lo encuentra a través de una ruta preparada para eso. Las tres piezas nuevas de los avisos usaban la ruta de mi entorno de desarrollo, que en tu servidor no existe: el archivo no se encontraba y salía el error genérico con referencia. Eso pasaba en cualquier teléfono, no era el Pixel.

Mis pruebas no lo vieron porque corrían sobre la estructura de desarrollo. Ahora hay una prueba que descomprime el paquete **tal como lo subes**, lo ejecuta con PHP y base de datos reales y hace lo mismo que el teléfono al activar los avisos. Con el paquete 0.20.0 reproduce exactamente tu error; con 0.20.1 pasa. Además, el empaquetador se niega a generar un paquete si vuelve a aparecer una ruta de desarrollo.

## La tabla del equipo en el móvil

Los nombres salían letra por letra y las barras de avance quedaban fuera de su fila. En el teléfono cada asesora es ahora una tarjeta: el nombre arriba y debajo cada cifra con su rótulo. En el escritorio sigue siendo tabla, con las barras ya alineadas dentro de cada fila.
