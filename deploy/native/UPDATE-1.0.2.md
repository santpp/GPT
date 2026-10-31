# Actualizar Ágora a 1.0.2

Al tocar un aviso, se abre la app instalada y no Chrome.

## Pasos

1. Extraer `agora-update-1.0.2.zip` sobre las carpetas existentes. Sin cambios de base de datos ni de configuración.
2. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.0.2**.
3. **Reinstalar la app** (quitar el icono y, en Chrome, ⋮ → Instalar aplicación). Android lee el manifiesto al instalar, y esta versión añade a ese manifiesto cómo debe abrirse la app desde un aviso. Después hay que volver a entrar y a activar los avisos.

## Qué cambia

- El aviso abre ahora la **dirección completa** de Ágora, no sólo la ruta. Así Android sabe a qué app instalada pertenece y no la abre en una pestaña de Chrome.
- Si la app ya está abierta, se reutiliza esa ventana y se lleva a la conversación, en primer plano.
- El manifiesto declara `launch_handler: navigate-existing` y `handle_links: preferred`, que piden a Android abrir los enlaces de Ágora en la app y no en el navegador.

## Si aún se abre en Chrome

En Chrome, ⋮ → Información de la aplicación (o Ajustes de Android → Apps → Ágora → Abrir de forma predeterminada) debe estar activado «Abrir enlaces admitidos». Si sigue igual, mándame el modelo y la versión de Chrome.
