# Actualizar Ágora a 1.10.14

Responsable de cada conversación en la bandeja.

## Pasos

1. Extraer `agora-update-1.10.14.zip` sobre las carpetas existentes. No hay migraciones.
2. El pie debe decir **v1.10.14**.

## Qué cambia

- **Conversaciones:** quien ve toda la subcuenta (la propietaria y los administradores de la subcuenta) ve debajo de cada conversación una línea con su responsable: «Responsable: Ana Ventas». Las que no tienen a nadie dicen **«Sin asignar»** en rojo. Si el responsable ya no está en el equipo, dice «otra persona del equipo».
- Los vendedores no ven esa línea (todas las suyas son suyas).
- Prueba nueva: `tests/inbox-assignee-ui.py`.
