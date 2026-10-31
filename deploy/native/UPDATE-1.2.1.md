# Actualizar Ágora a 1.2.1

Los avisos dicen de qué subcuenta son y abren la conversación correcta.

## Pasos

1. Extraer `agora-update-1.2.1.zip` sobre las carpetas existentes. Sin cambios de base de datos ni de configuración.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.2.1**. Si dice «avisos en vX: cierra y vuelve a abrir la app», hazlo: los avisos los pinta un componente que se actualiza aparte.

## Qué cambia

- **El aviso lleva la subcuenta** en el título cuando tu empresa tiene más de una: «ASOCIM · María Cliente · WhatsApp». Con una sola subcuenta no se añade, porque sería ruido.
- **El aviso abre la conversación, también entre subcuentas:**
  - si el aviso trae su subcuenta, Ágora cambia a ella y abre esa conversación;
  - si no la trae, por ejemplo un aviso que ya estaba en el teléfono antes de actualizar, Ágora la **busca en las demás subcuentas** y abre la que la tenga;
  - si la recarga de la app por una actualización interrumpe la apertura, no se pierde: se recuerda durante un minuto;
  - si la conversación ya no existe o no es tuya, lo dice.

## Si sigue sin abrir la conversación

Dime exactamente qué ves al pulsar el aviso: si abre la bandeja sin más, si sale un mensaje, o si abre Chrome en vez de la app. Con eso se ve en qué eslabón se corta.
