# Actualizar Ágora a 1.11.1

Ajustes sobre la 1.11.0: sin gestión de usuarios, auditoría sólo de la propietaria con borrado de lo antiguo, logos SVG, y la subcuenta de demostración.
(La 1.11.0 puede no haberse subido: este paquete la incluye completa.)

## Pasos

1. Extraer `agora-update-1.11.1.zip` sobre las carpetas existentes.
2. Abrir Ágora una vez: se aplican solas las migraciones `031` y `032` (aditivas, repetibles) y se crea la subcuenta **Demo · Academia Horizonte**.
3. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.11.1**.

## Qué cambia

- **Sin manejo de usuarios:** no hay rutas para crear cuentas ni cambiar roles; el equipo sigue siendo el de GoHighLevel y tu cuenta propietaria, como siempre.
- **Auditoría sólo de la persona propietaria**, con **«Borrar los de hace 6 meses o más»**: lo hace el servidor con su propia fecha de corte; no se puede borrar nada más reciente, y el borrado queda anotado.
- **Logo en SVG**, como siempre. El servidor lo reescribe para quitarle scripts, eventos y referencias externas y lo sirve en un contenedor sin permisos.
- **Subcuenta de demostración** con datos ficticios, que nunca llama a la cuenta de origen. Reinicio: botón en la pantalla de Identidad del paquete de pantallas, `POST admin/demo/reset` o `php bin/reset-demo.php`.
- Menos consultas: contactos recientes cada 10 min (antes 5) y anuncios cada 30 (antes 20).
- Pruebas: `tests/demo.php` (23), `tests/workspace-extra.php` (52), `tests/reports.php` (28).
