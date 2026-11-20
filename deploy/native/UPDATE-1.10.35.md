# Actualizar Ágora a 1.10.35

Cambio: al cargar los mensajes anteriores de una conversación ya no se ven mensajes repetidos. Incluye 1.10.17 a 1.10.34 (la 1.10.34 arregló los avisos en Android).

## Pasos

1. Extraer `agora-update-1.10.35.zip` sobre las carpetas existentes.
2. El pie debe decir **v1.10.35**.

## Qué estaba pasando

- La cuenta a veces entrega el mismo mensaje dos veces, con distinto identificador (pasó con «Le quitaste los datos?» de María Daniels). Ágora lo mostraba dos veces al cargar los mensajes anteriores.

## Qué cambia

- Un mensaje se muestra una sola vez si tiene el mismo identificador del canal (por ejemplo, el de WhatsApp) o si es el mismo envío exacto: misma dirección, tipo, texto, adjuntos y hora al milisegundo.
- Dos mensajes iguales enviados en momentos distintos se siguen viendo los dos.
- Prueba: `tests/live-ui.py` incluye el caso (falla con la versión anterior).
