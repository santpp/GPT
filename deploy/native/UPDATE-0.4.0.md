# Ágora 0.4.0 · tamaño de bandeja y respuestas

1. Descargar `agora-update-0.4.0.zip` y extraer en la carpeta que contiene `public_html` y `agora-private`, reemplazando el código.
2. Abrir **https://nueva.agoradigital.app/conexiones.html** como administrador. Pulsar **Actualizar base de datos** cuando aparezca: crea las tablas de preferencias e identificadores de envío. No cambia las claves ni los datos anteriores.
3. En **Conversaciones**, el selector **Mostrar** permite 20, 50 o 100 conversaciones recientes. La selección consulta el servidor y se guarda por cuenta después de sincronizar correctamente. El cron utiliza esa preferencia. Las pausas de cuota se mantienen.
4. Abrir una conversación y escribir en el formulario. El canal se toma de esa conversación; esta entrega admite texto para WhatsApp, SMS, Instagram, Messenger y Email (con asunto), sujeto a disponibilidad, permisos y reglas del canal. No incluye plantillas, adjuntos ni agrupación de hilos de email.
5. La conexión debe disponer del permiso **conversations/message.write**. Si falta, el administrador debe habilitarlo en la integración de origen y actualizar la credencial sólo si el proveedor emite una nueva. No introducir el token en el login ni enviarlo por chat.
6. Verificar **version.json**: `0.4.0`. Comprobar primero el envío a un contacto de prueba propio; las pruebas automatizadas usan respuestas simuladas y no certifican el canal real.

**Resultados del envío:** «Aceptado» no significa entregado. Si el canal rechaza, se conserva el texto. Si no hay confirmación, no hay reenvío automático: consultar el historial. El servidor bloquea repetir idéntico contenido cuyo resultado siga incierto, incluso con otro identificador. La resolución administrativa de esos casos no está implementada todavía.

La clave y su propietario se conservan: el ZIP no contiene config/local.php ni var. No se requieren comandos de terminal. No se incorporan webhooks, push ni permisos de vendedor en esta entrega. Las búsquedas siguen limitadas a las conversaciones cargadas.
