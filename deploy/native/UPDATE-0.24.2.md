# Actualizar Ágora a 0.24.2

Averiguar por qué no aparecen las plantillas de WhatsApp, y leerlas con más tolerancia.

## Pasos

1. Extraer `agora-update-0.24.2.zip` sobre las carpetas existentes. La base de datos se actualiza sola: añade un campo opcional a las conexiones.
2. **Conexiones → Plantillas de WhatsApp → Comprobar plantillas.** Ágora pide la lista a la cuenta en ese momento y dice:
   - cuántas plantillas devolvió la cuenta y cuántas se muestran en el chat;
   - las que se quedan fuera y por qué (en revisión, rechazadas, sin idioma…);
   - en «Detalle para soporte», los nombres de los campos de la respuesta, nunca su contenido.
3. Si dice **«La cuenta no devolvió ninguna plantilla»**, escribe en el segundo campo el **WABA ID** (identificador de la cuenta de WhatsApp Business, en la configuración de WhatsApp de la cuenta o en el Business Manager de Meta), guarda y vuelve a comprobar. Las plantillas se piden entonces con ese identificador. El envío sigue saliendo del número.
4. Si sigue sin aparecer ninguna, mándame una captura del resultado con el «Detalle para soporte» abierto.

## Qué cambia

- Se aceptan más formas de la respuesta: lista anidada, idioma con otros nombres (`languageCode`, `locale`, `es-ES`) y plantillas que llegan sin su texto. Estas últimas se ofrecen sin vista previa y se envían tal como están aprobadas.
- El chat, si no hay plantillas, indica que el administrador puede revisarlo con «Comprobar plantillas».
