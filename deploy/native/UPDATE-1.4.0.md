# Actualizar Ágora a 1.4.0

Conversaciones que vienen de un comentario en una publicación. Se quita la cita de mensajes (WhatsApp no la mostraba al cliente) y se descartan las notas de voz (la cuenta rechazaba el envío).

## Pasos

1. Extraer `agora-update-1.4.0.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.4.0**.

## También en esta versión

- **Comentarios:** si el contacto llegó por un comentario en una publicación de Instagram o Facebook, la conversación lo dice arriba y marca cada comentario. Responder sale como **mensaje directo**. La API no documenta la respuesta privada a un comentario, así que hay que probarla; Meta sólo la admite durante unos días.
- **Errores de envío:** el aviso incluye el motivo que da la cuenta.
