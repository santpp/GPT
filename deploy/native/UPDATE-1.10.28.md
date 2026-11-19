# Actualizar Ágora a 1.10.28

Arreglo: publicar una novedad (u otro texto) con caracteres como «→» o emojis daba «No se ha podido completar la operación». Incluye 1.10.17 a 1.10.27 (con «Nuevo contacto»).

## Pasos

1. Extraer `agora-update-1.10.28.zip` sobre las carpetas existentes. Si vienes de 1.10.20 o anterior: copiar también el `.htaccess` (ruta `/contratos`) y reiniciar OpenLiteSpeed.
2. Abrir Ágora una vez como administradora: la base se ajusta sola (tarda un instante).
3. El pie debe decir **v1.10.28**. Vuelve a publicar la novedad.

## Qué cambia

- **Causa:** las tablas de Ágora se creaban con el juego de caracteres por defecto de la base del servidor. Si era el antiguo (latin1), aceptaba «á» o «ñ», pero no «→» ni emojis, y guardar fallaba con el error genérico.
- **Arreglo:** la conexión con la base habla siempre en utf8mb4, y al abrir la app las tablas con texto escrito por personas (novedades, respuestas propias, avisos, citas de mensajes, cifras del mes) se pasan a utf8mb4 una sola vez, sin perder nada. Si el servidor no deja hacerlo, se reintenta como mucho una vez por hora, sin trabar la app.
- **Comprobarlo (opcional):** en phpMyAdmin, la tabla `announcements` debe decir `utf8mb4_unicode_ci` en «Cotejamiento».
