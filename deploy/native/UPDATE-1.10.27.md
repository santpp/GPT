# Actualizar Ágora a 1.10.27

Novedad: «Nuevo contacto» en Contactos, escrito directamente en la cuenta. Incluye 1.10.17 a 1.10.26.

## Pasos

1. Extraer `agora-update-1.10.27.zip` sobre las carpetas existentes. Si vienes de 1.10.20 o anterior: copiar también el `.htaccess` (ruta `/contratos`) y reiniciar OpenLiteSpeed; el campo de la base se crea solo.
2. El pie debe decir **v1.10.27**.
3. No hace falta tocar la conexión: las claves de la cuenta ya tienen permiso para escribir contactos (Mobile ya edita fichas).

## Qué cambia

- **Contactos → «Nuevo contacto»** (arriba, junto al buscador). Pide nombre (obligatorio), apellido, teléfono con código de país y/o correo (al menos uno).
- **Se crea en la cuenta de la subcuenta**, con origen «Ágora». No queda ninguna copia aparte en Ágora: aparece en la lista y en la cuenta como cualquier otro contacto, y se abre su ficha para completar etiquetas y campos.
- **Sin duplicados:** antes de crear, se pregunta a la cuenta si ya existe un contacto con ese teléfono o correo. Si existe, no se crea otro y se ofrece «Abrir ficha» (si es de otra persona y quien lo pide solo ve lo suyo, se le indica que pida que se lo asignen).
- **Asignación:** el vendedor lo crea asignado a sí mismo (si no está enlazado con la cuenta, se le avisa). La propietaria y los administradores pueden marcar «Asignármelo» o dejarlo sin asignar.
- **Quién puede:** quien puede editar fichas. «Entrar como» no crea nada.
- **Pulsar dos veces no crea dos contactos.**
- Pruebas nuevas: `tests/contact-create.php` y `tests/contact-new-ui.py`.
