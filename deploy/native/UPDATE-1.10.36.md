# Actualizar Ágora a 1.10.36

Cambio: las **plantillas de WhatsApp** se envían ahora con **flujos de trabajo** de la cuenta. La cuenta no entrega por su conexión las plantillas aprobadas por Meta, así que cada plantilla se envía desde un flujo que tiene la acción «Enviar WhatsApp». Incluye 1.10.17 a 1.10.35.

## Pasos

1. En la cuenta, en la integración privada de cada subcuenta, añade el permiso **`workflows.readonly`** (flujos de trabajo, lectura). `contacts.write` ya lo tenía.
2. Extraer `agora-update-1.10.36.zip` sobre las carpetas existentes. Las tablas nuevas (`whatsapp_flows` y `whatsapp_flow_sends`, en utf8mb4) se crean solas.
3. El pie debe decir **v1.10.36**.

## Preparar las plantillas (una vez por plantilla, en la cuenta)

1. Crea un flujo con la acción «Enviar WhatsApp» y elige la plantilla aprobada.
2. Activa **«Permitir volver a entrar»**; si no, el segundo envío al mismo contacto se ignora sin avisar.
3. Publícalo. No necesita disparador: Ágora mete al contacto en el flujo.

## Qué cambia

- **Conexiones → Plantillas de WhatsApp** (solo el propietario): «Cargar flujos de la cuenta», **buscador por nombre** y se marcan **varios**. A cada uno se le pone el nombre que verá el equipo y, si se quiere, el texto para la vista previa. Los borradores se ven pero no se pueden marcar. Hasta 40 por subcuenta. Se quitaron el número de WhatsApp y «Comprobar plantillas».
- **Bandeja**: lo envían todos los que pueden escribir. «Usar plantilla» → elegir → «Enviar plantilla». Ágora avisa «pedida a la cuenta» y, a los pocos segundos, «Plantilla enviada» cuando el mensaje aparece en la conversación.
- Sin dobles: el doble toque no la envía dos veces y la misma plantilla no se repite al mismo contacto antes de 10 minutos.
- Pruebas: `tests/whatsapp-flows.php` (18) y `tests/whatsapp-flows-ui.py` (28).

## Sin probar

- Con la cuenta real: que el flujo se dispare al meter al contacto y que el mensaje llegue. Se probó con una cuenta simulada.
