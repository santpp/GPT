# Actualizar Ágora a 1.10.30

Cambio: «Asignármelo» asigna a la persona correcta. Incluye 1.10.17 a 1.10.29.

## Pasos

1. Extraer `agora-update-1.10.30.zip` sobre las carpetas existentes.
2. El pie debe decir **v1.10.30**.
3. Quien administra: en **Tablero › «¿Quién eres en esta subcuenta?»**, revisar que esté elegido su propio usuario.

## Qué cambia

- **«Asignármelo» ya no cae en otra persona.** Para saber quién eres en la cuenta, Ágora usa primero lo que elegiste en «¿Quién eres en esta subcuenta?»; antes un dato viejo del perfil podía pasar por delante.
- **La casilla dice a quién se asigna:** «Asignármelo · tu nombre». Si Ágora no sabe quién eres, no hay casilla y explica dónde elegirlo.
- **Al crear, avisa a quién quedó asignado.** Si una regla automática de la cuenta lo reasigna, también lo dice.
- **Editar la ficha ya no quita al responsable** cuando esa persona no está en la lista del equipo (pasaba al guardar cualquier otro dato). Lo mismo en oportunidades y en «Asignar» de la bandeja.
- Pruebas nuevas: `tests/self-assign.php`, `tests/assignee-keep-ui.py`.
