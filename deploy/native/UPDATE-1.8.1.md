# Actualizar Ágora a 1.8.1

Corrección: contactos que escribieron por Instagram o Messenger y después por WhatsApp.

## Pasos

1. Extraer `agora-update-1.8.1.zip` sobre las carpetas existentes. La base de datos se actualiza sola (una tabla nueva).
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.8.1**.

## Qué cambia

- Si un contacto escribió (o comentó) por Instagram o Messenger y luego la conversación siguió por WhatsApp, Ágora ofrecía sólo WhatsApp. Ahora recuerda por qué redes ha escrito el contacto y ofrece también Instagram o Messenger. Se actualiza al abrir la conversación.
- El botón «Responder como DM» de un comentario ya funciona en esos casos.
- Meta sólo permite escribir por Instagram o Messenger durante unas 24 horas desde el último mensaje del contacto (algo más tras un comentario). Pasado ese plazo, la cuenta puede rechazar el envío; Ágora mostrará el motivo.
