# Actualizar Ágora a 0.27.0

Icono propio de Ágora al instalarla, en la pestaña del navegador y en los avisos, e interfaz más limpia.

## Pasos

1. Extraer `agora-update-0.27.0.zip` sobre las carpetas existentes. Sin cambios de base de datos ni de configuración.
2. **Icono de la app instalada:** los teléfonos guardan el icono al instalar. Para ver el nuevo:
   - **Android (Chrome):** mantén pulsado el icono de Ágora → Desinstalar o Quitar. Abre la web en Chrome → menú ⋮ → **Instalar aplicación**. Chrome a veces lo actualiza solo en unos días, pero reinstalar es inmediato.
   - **iPhone (Safari):** mantén pulsado el icono → Eliminar app. En Safari → Compartir → **Añadir a pantalla de inicio**.
   - Al reinstalar hay que volver a entrar una vez y a activar los avisos y la huella en ese teléfono.
3. El pie debe decir **v0.27.0**.

## Iconos

- La marca de Ágora, en blanco sobre el carmesí de la marca, en todos los tamaños: Android (192 y 512), iPhone (180), pestaña del navegador (32 y 48, más `favicon.ico`).
- Versión «adaptable» para Android: la marca va con margen, para que el teléfono pueda redondear o recortar el icono sin cortarla.
- Los avisos usan el icono nuevo y, en la barra de estado de Android, la marca sola en blanco, como piden las notificaciones del sistema.
- Antes se usaba un recorte de la hoja de marca que quedaba cortado y en azul marino.

## Interfaz

- Pie de página: sólo «Ágora · versión».
- Menú lateral: sin la etiqueta «Espacio comercial» ni el nombre de la cuenta abajo (ya está arriba, en el selector).
- Tablero: sin los botones «Ir a conversaciones» y «Ver el embudo», que repetían el menú.
- Bandeja: sin el contador repetido junto a «No leídas» («5 de 5 conversaciones» ya lo dice). En el ordenador, la columna de la ficha no aparece vacía: sale al abrir una conversación.
- Chat: «WhatsApp · Texto» pasa a «Respondes por WhatsApp».
