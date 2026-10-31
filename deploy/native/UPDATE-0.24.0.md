# Actualizar Ágora a 0.24.0

Plantillas de WhatsApp en el chat, para escribir a un contacto pasadas 24 horas desde su último mensaje.

## Pasos

1. Extraer `agora-update-0.24.0.zip` sobre las carpetas existentes. La base de datos se actualiza sola: añade un campo a las conexiones.
2. **Permiso de la clave de la cuenta:** la clave privada de integración de cada subcuenta necesita también el permiso de **lectura de plantillas** (`locations/templates.readonly`). Sin él, el chat dirá que la clave no permite leer plantillas.
3. **Conexiones → Plantillas de WhatsApp**, en cada cuenta: escribir el identificador del número de WhatsApp («Phone number ID» de Meta), por ejemplo `920050454518509`. Es el mismo que usa la cuenta como origen de sus plantillas.
4. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v0.24.0**.

## En el chat

- En una conversación de WhatsApp, si han pasado más de 24 horas desde el último mensaje del contacto, sale un aviso: «WhatsApp sólo permite escribir con una plantilla aprobada».
- **Usar plantilla** abre la lista de plantillas aprobadas por Meta, por nombre y tipo (Promoción, Servicio, Verificación). Las que están en revisión o rechazadas no aparecen.
- La vista previa enseña el texto como lo verá el contacto, con el pie y los botones.
  - Los datos de la ficha, como el nombre del contacto, salen en gris. Los completa la cuenta.
  - Las variables libres (fecha, hora…) se escriben en casillas y aparecen en la vista previa según se escriben. «Enviar plantilla» no se activa hasta rellenarlas todas.
- Una plantilla con imagen o con un enlace que cambia se muestra, pero por ahora se envía desde la cuenta.
- El número que envía es el configurado en Conexiones, y el destino es el teléfono de la ficha del contacto. El navegador no puede cambiar ninguno de los dos.
- La protección contra envíos duplicados es la misma que la de cualquier mensaje.
- La lista de plantillas se guarda 10 minutos para no consultar la cuenta en cada chat.

## A tener en cuenta

El listado de plantillas usa el endpoint publicado por la plataforma. La forma exacta de una plantilla de WhatsApp y el envío con `whatsapp.type=template` no están descritos en su referencia pública: Ágora reproduce lo que hace la propia cuenta al enviar una plantilla (el ejemplo que capturaste) y lee la lista con tolerancia. Si el primer envío real lo rechaza, el chat lo dirá con el código del canal. Con ese dato se ajusta.
