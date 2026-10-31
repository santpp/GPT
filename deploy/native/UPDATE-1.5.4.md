# Actualizar Ágora a 1.5.4

Entrada de los usuarios de subcuenta.

## Pasos

1. Extraer `agora-update-1.5.4.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.5.4**.

## Qué cambia

- El selector de subcuenta sólo se muestra cuando la persona tiene más de una. Con una, se ve su nombre y entra directo.
- Si un usuario de subcuenta no tiene ninguna disponible, el mensaje dice por qué: «Tu correo todavía no está asociado a ninguna subcuenta» (hay que sincronizar el equipo en Conexiones) o «Tu usuario no tiene activadas las conversaciones en su subcuenta» (hay que activarlas en los permisos del usuario en la subcuenta).
- El código de acceso por correo se envía desde la subcuenta del usuario. Si no se puede, se usa la cuenta elegida como canal de acceso.
