# Actualizar Ágora a 1.11.0

Las rutas del servidor que piden las pantallas nuevas: tareas, recordatorios de seguimiento, auditoría, usuarios y roles,
identidad del espacio, resumen, operación del día, eficacia de ventas y anuncios. Todo está explicado en `docs/rutas-pantallas-nuevas.md`.

## Pasos

1. Extraer `agora-update-1.11.0.zip` sobre las carpetas existentes.
2. **Hay una migración nueva** (`031-workspace-extra.sql`): se aplica sola al abrir Ágora (o desde Conexiones → Revisar la instalación).
   Es aditiva: crea tablas y añade columnas a `users`; no cambia lo que ya existe. Puede repetirse sin problema.
3. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.11.0**.
4. Opcional: en el `.env` (o `local.php`) puedes fijar `APP_TIMEZONE` (por defecto `America/Caracas`): con ella se cuenta «hoy» en la operación del día.

Si vas a publicar también las pantallas nuevas (paquete `agora-digital-app-1.2.0.zip`), léelo aparte: su `LEEME.md` dice cómo ponerlo
encima de este.

## Qué cambia

- **Rutas nuevas** (ver la tabla del documento). Con la app actual no se nota nada: son las pantallas nuevas las que las usan.
- **Auditoría:** desde ahora se anota quién entra y sale, qué mensajes se envían, qué fichas se editan, las oportunidades, las asignaciones y los
  cambios de cuentas. Sólo se agrega; nada la borra ni la edita.
- **Cuentas con clave:** pueden tener nombre, rol (cinco) y estar activas o no. Una cuenta desactivada no entra y se le cierran las sesiones.
- Las peticiones pueden llevar un cuerpo mayor (hasta 600 KB) sólo para el logo; el resto sigue en 16 KB.
- Pruebas nuevas: `tests/workspace-extra.php` (56 comprobaciones) y `tests/reports.php` (28).
