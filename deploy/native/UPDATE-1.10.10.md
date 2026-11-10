# Actualizar Ágora a 1.10.10

Animaciones al abrir.

## Pasos

1. Extraer `agora-update-1.10.10.zip` sobre las carpetas existentes. No hay migraciones.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.10.10**.

## Qué cambia

- **Ficha de contacto:** al abrirla desde Contactos entra deslizándose desde la derecha en el teléfono (la misma animación que ya usan el chat y la ficha en Conversaciones); en pantallas grandes aparece subiendo suavemente.
- **Más:** el fondo se oscurece, el panel sube desde abajo y sus botones aparecen uno tras otro; al cerrar, el panel baja.
- Si el teléfono pide reducir el movimiento, todo se abre y cierra sin animación.
- Prueba nueva: `tests/open-motion-ui.py`.
