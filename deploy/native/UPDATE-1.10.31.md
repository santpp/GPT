# Actualizar Ágora a 1.10.31

Cambio: «Sin asignar» en la ficha funciona, y guardar la ficha muestra un aviso claro. Incluye 1.10.17 a 1.10.30.

## Pasos

1. Extraer `agora-update-1.10.31.zip` sobre las carpetas existentes.
2. El pie debe decir **v1.10.31**.

## Qué cambia

- **«Sin asignar» quita al responsable.** Antes, guardar la ficha con «Sin asignar» dejaba a la persona que ya estaba. Vale también para «Asignar › Sin asignar» en Conversaciones.
- **Aviso al guardar la ficha:** franja verde «Ficha guardada en la cuenta» arriba de la ficha, en Contactos y en Conversaciones. Si la cuenta mantuvo al responsable (por ejemplo, por una regla automática), la franja sale dorada y lo explica.
- Incluye lo de 1.10.30: «Asignármelo» asigna a la persona correcta.
- Prueba nueva: `tests/contact-save-ui.py`.
