# Actualizar Ágora a 1.10.7

«Entrar como», «Mis leads» por periodo y un cierre de acceso para vendedores.

## Pasos

1. Extraer `agora-update-1.10.7.zip` sobre las carpetas existentes.
2. Abrir Ágora: la migración `031-view-as.sql` (tabla `view_as_log`) se aplica sola. Si la pantalla de instalación la pide, aplicarla desde Conexiones.
3. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.7**.

## Qué cambia

- **Entrar como** (Mi cuenta → Entrar como, sólo la propietaria): ves Ágora como cualquier usuario de una subcuenta, con sus pestañas, sus permisos y sólo lo que tiene asignado. Es sólo para mirar: no se puede enviar, editar ni marcar como leído en su nombre. Una franja arriba lo recuerda, «Volver a mi vista» lo termina y caduca sola a la hora. Cada entrada y salida queda anotada en `view_as_log`.
- **Tablero:** «Leads asignados a mí» pasa a ser «Mis leads de hoy / de ayer / nuevos» y cuenta sólo los del periodo elegido. Antes contaba todos los que tenías asignados desde siempre.
- **Acceso:** un vendedor que pedía abrir la conversación de un contacto ajeno sin conversación hacía que se creara una vacía en la cuenta antes de rechazarlo. Ahora se comprueba antes y no se crea nada.
- Pruebas nuevas: `tests/view-as.php`, `tests/view-as-ui.py`; ampliadas `tests/dashboard.php` y `tests/inbox-feed.php`.
