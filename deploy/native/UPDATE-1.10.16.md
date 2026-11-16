# Actualizar Ágora a 1.10.16

Metas por número de ventas, además de por dinero.

## Pasos

1. Extraer `agora-update-1.10.16.zip` sobre las carpetas existentes. No hay migraciones.
2. El pie debe decir **v1.10.16**.

## Qué cambia

- **Tablero → Cifras del mes:** nuevo «Las metas del mes se miden en»: **Dinero vendido** (como hasta ahora) o **Número de ventas** (con la palabra de cada cierre, por ejemplo «estudiantes»).
- La elección vale para la meta del equipo y la de cada asesora, y se guarda por mes (cada mes puede ser distinto). Los meses ya guardados siguen en dinero.
- En número de ventas, el avance, lo que falta («Faltan 20 estudiantes para la meta de 150 estudiantes»), la meta y la diferencia de cada asesora se cuentan en ventas. Lo vendido en dinero se sigue viendo.
- Al cambiar de unidad, escribe de nuevo las metas: el número que hubiera no se convierte.
- Pruebas: `tests/board.php` (58) y `tests/board-ui.py`.
