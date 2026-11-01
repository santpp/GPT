# Actualizar Ágora a 1.7.0

Identidad visual: tipografía propia y una interfaz más sobria.

## Pasos

1. Extraer `agora-update-1.7.0.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. **Instalar la tipografía Author** (una sola vez):
   - Descargar la familia en https://www.fontshare.com/fonts/author («Download family»).
   - Del ZIP, subir a `public_html/assets/fonts/` los archivos `Author-Variable.woff2` (y, si existe, `Author-VariableItalic.woff2`). Los nombres deben ser exactamente esos.
   - Sin los archivos todo funciona igual, con la fuente del sistema.
3. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.7.0**.

## Qué cambia

- Tipografía Author en toda la app (cuando los archivos están en el servidor).
- Burbujas de chat y etiquetas sin rosa; el color de marca queda para lo seleccionado y la acción principal.
- Esquinas con tres radios (6, 10 y 14 px) en vez de siete.
- Iconos de trazo más fino y de puntas rectas.
- Los avisos (24 horas de WhatsApp, comentario de origen, advertencias del tablero) son una línea de texto con un filo fino, no un recuadro amarillo.
- Los datos del tablero aparecen sin animación; lo demás se anima sólo donde hay un cambio.
- Selector de periodo: o un botón rápido, o un mes/rango de fechas; nunca los dos a la vez.
- El selector de canal y las tarjetas con borde no cambian.
