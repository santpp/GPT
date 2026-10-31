# Actualizar Ágora a 0.22.1

El aviso del teléfono dice lo que escribió el contacto.

## Pasos

1. Extraer `agora-update-0.22.1.zip` sobre las carpetas existentes.
2. Nada más. El cron o la dirección externa que ya tienes siguen igual. El pie debe decir **v0.22.1**.

Sin cambios de base de datos ni de configuración.

## Qué cambia

- Antes, el texto del aviso salía del resumen de la conversación. Ese resumen a veces llega más tarde que el propio mensaje, y por eso el aviso decía «Envió un archivo o un mensaje sin texto».
- Ahora, justo antes de enviar el aviso, Ágora lee el último mensaje que entró en esa conversación y pone su texto: «Raissa Moreno · WhatsApp — Hola, ¿tenéis cita el jueves?».
- Si el mensaje es una foto, una nota de voz, un vídeo o un documento, el aviso lo dice así («Envió una foto», «Envió una nota de voz»…).
- Si hay varios sin leer, se añade «· 3 mensajes sin leer».
- Si en ese momento la cuenta no responde, el aviso sale igualmente con el resumen. No se retrasa.
- Es una lectura más por mensaje nuevo, dentro del mismo límite de consultas de siempre. La notificación de prueba no lee nada.
