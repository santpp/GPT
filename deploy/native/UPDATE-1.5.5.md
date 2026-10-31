# Actualizar Ágora a 1.5.5

Administradores de subcuenta.

## Pasos

1. Extraer `agora-update-1.5.5.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.5.5**.

## Qué cambia

- Quien administra una subcuenta (rol administrador) ya entra con acceso a conversaciones, contactos, oportunidades y tablero de esa subcuenta, aunque la cuenta no liste sus permisos. Si a un administrador se le desactiva expresamente un módulo en la cuenta, sigue desactivado.
- Los administradores de subcuenta no configuran conexiones: eso sigue siendo del propietario de Ágora.
- Si el usuario ya tenía la sesión abierta, debe salir y volver a entrar (o esperar a que se actualice su acceso).
