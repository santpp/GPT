# Actualizar Ágora a 0.24.1

Filtro de canales en un menú desplegable, botón «Enviar mensaje» en la ficha del contacto y logo en PNG transparente.

## Pasos

1. Extraer `agora-update-0.24.1.zip` sobre las carpetas existentes.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v0.24.1**.

Sin cambios de base de datos ni de configuración.

## Qué cambia

- **Filtro de canales:** la fila de botones (Todos los canales, SMS, WhatsApp…) pasa a ser un menú desplegable en la misma línea que «Todas / No leídas». Con un canal elegido, el menú se marca en color.
- **Enviar mensaje desde Contactos:** la ficha tiene un botón **Enviar mensaje** que abre directamente la conversación de esa persona en la bandeja, lista para escribir.
  - Si ya tenía una conversación, se abre esa, aunque no esté entre las últimas de la lista.
  - Si no tenía ninguna, Ágora la crea en la cuenta (endpoint publicado de creación de conversaciones, que usa el permiso de escritura de conversaciones que ya tiene la clave). Los canales disponibles salen de la ficha: WhatsApp y SMS si tiene teléfono, Email si tiene correo.
  - Si la conversación está asignada a otra persona y tu usuario solo ve lo suyo, te lo dice.
- **Logo:** el de la barra lateral, el de la cabecera, el de la pantalla de acceso y el de bloqueo son ahora PNG con fondo transparente, sin la tarjeta blanca de fondo.
- **Corrección:** guardar la ficha desde la pantalla de Contactos se rechazaba por un token de seguridad que no se enviaba. Ya se envía.
