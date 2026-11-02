# Actualizar Ágora a 1.9.0

Manual de uso para vendedores, tutorial de primera vez y el logo como botón de ayuda.

## Pasos

1. Extraer `agora-update-1.9.0.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.9.0**.

## Qué cambia

- **El logo de Ágora es el botón de ayuda.** Al tocarlo se abre un menú: *Ver el manual*, *Repetir el tutorial* y *Descargar el PDF*.
- **Manual de uso (sólo para vendedores)**, dentro de la app (`/ayuda`) y como PDF descargable. Incluye capturas con datos inventados y explica, entre otras cosas, que el logo es la ayuda. No nombra ningún proveedor.
- **Tutorial de primera vez:** unos 6 pasos con la pantalla resaltada, que se puede omitir. Sale solo **una vez por dispositivo** y solo a vendedores; se repite desde el logo.
- **Orden de las ventanas al entrar** (una a la vez): primera visita de un vendedor → tutorial, clave de acceso (solo si acaba de entrar con código), novedades, alerta de ventas sin monto. En las siguientes entradas: bienvenida (si falta instalar o activar avisos), clave de acceso, novedades y alerta. Una alerta urgente pasa justo después del tutorial. Quien administra no ve el tutorial.
- **El canal elegido recupera su color** en el selector (WhatsApp verde, Instagram rosa, Facebook azul).
- Corrección: el logo vuelve a verse en la cabecera del teléfono, ahora como botón.
- Pruebas: la de `mobile-ui` entiende que con una sola subcuenta el selector es solo su nombre.

## Para regenerar el manual

`python3 tools/make-manual.py` (con la app local en marcha) rehace las capturas y el PDF con datos inventados.
