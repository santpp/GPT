# Actualizar Ágora a 1.10.17

Adjuntos y respuestas guardadas (snippets) en la bandeja.

## Pasos

1. Extraer `agora-update-1.10.17.zip` sobre las carpetas existentes. No hay migraciones.
2. **Límite de archivos de PHP:** el paquete trae en `public_html/.user.ini` `upload_max_filesize=16M` y `post_max_size=17M`. Si el servidor no lee `.user.ini` (OpenLiteSpeed a veces no), ponlo en CyberPanel → PHP → Edit PHP Configs (lsphp83): `upload_max_filesize = 16M`, `post_max_size = 17M`, y reinicia (`systemctl restart lsws`).
3. La clave privada de cada subcuenta necesita los permisos que ya usa la bandeja: `conversations/message.write` (subir y enviar archivos) y `locations/templates.readonly` (leer los snippets; es el mismo que el de las plantillas de WhatsApp).
4. El pie debe decir **v1.10.17**.

## Qué cambia

- **Adjuntar archivos** con el clip del cuadro de respuesta: imágenes (JPG, PNG), PDF, Word, texto, audio (MP3, WAV), video (MP4) y comprimidos (ZIP, RAR). Hasta 5 por mensaje. Por WhatsApp hasta 16 MB por archivo; por los demás canales, hasta 5 MB. Se puede enviar un archivo sin texto.
- El archivo va del teléfono a nuestro servidor, que lo comprueba (tipo real, no solo la extensión) y lo sube a la cuenta. Solo se pueden enviar archivos subidos en esa misma conversación (o los de un snippet).
- **Respuestas guardadas:** botón en el cuadro de respuesta con los snippets de la subcuenta (los de Marketing → Snippets en la cuenta), con buscador. Al elegir uno se pone su texto y sus archivos.
- Los campos del snippet se rellenan con la ficha: `{{contact.first_name}}`, `{{contact.last_name}}`, `{{contact.name}}`, `{{contact.email}}`, `{{contact.phone}}`, `{{contact.company_name}}`, `{{user.name}}`, `{{user.first_name}}`, `{{location.name}}`. Lo que no se conoce (por ejemplo `{{custom_values.precio}}`) queda marcado y no deja enviar hasta escribirlo: el cliente nunca recibe llaves.
- Pruebas: `tests/attachments.php` (22) y `tests/attach-ui.py`.
