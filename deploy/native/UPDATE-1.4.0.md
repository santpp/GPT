# Actualizar Ágora a 1.4.0

Notas de voz por WhatsApp. Se quita la cita de mensajes: WhatsApp no la mostraba al cliente.

## Pasos

1. Extraer `agora-update-1.4.0.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.4.0**.

## Cómo se usa

- En una conversación de WhatsApp (con la ventana de 24 h abierta) aparece un micrófono junto a «Usar plantilla».
- Al pulsarlo el navegador pide permiso para el micrófono (una vez). Graba hasta 5 minutos; **Detener** o **Cancelar**.
- Puedes escucharla antes de **Enviar** o **Descartar**.
- Si se pierde la conexión al enviar, repetir no duplica la nota.

## Lo que hay que saber

- Es lo primero que se prueba: enviar una nota a tu propio WhatsApp. No se ha podido comprobar contra la cuenta real que acepte el audio ni cómo lo muestra WhatsApp.
- WhatsApp sólo la muestra como nota de voz si es OGG con Opus. Chrome/Android la graba así (Ágora la convierte). iPhone/Safari graba AAC: llegaría como archivo de audio, no como nota de voz.
- Sólo WhatsApp: en otros canales no aparece el micrófono.
