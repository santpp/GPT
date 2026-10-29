# Actualizar Ágora a 0.12.0

Añade la edición completa de la ficha del contacto, un selector de canal en la respuesta, y corrige el texto con marcado que aparecía en las tareas. La aplicación pasa a actualizarse sola.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.12.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos se actualiza sola y, a partir de ahora, **la aplicación también**: ya no hay que pulsar «Nueva versión».

## La aplicación se actualiza sola

Desaparece el botón **Nueva versión**. Cuando hay una versión nueva instalada, Ágora la aplica y recarga por su cuenta. Lo único que la hace esperar es tener texto sin enviar: si estás escribiendo una respuesta, una nota o editando la ficha, la actualización se pospone y se aplica en cuanto termines, para no perder lo escrito. Además, cada vez que vuelves a la aplicación se comprueba si hay versión nueva, no sólo al abrirla de cero.

## Tareas y notas sin marcado

Las tareas y notas se guardan con texto enriquecido en la cuenta, y Ágora mostraba ese marcado tal cual (`<p style=…>`, `<span data-cv-variable=…>`). Ahora se muestra el texto de lectura, con los saltos de línea en su sitio. El marcado recibido nunca se interpreta, sólo se limpia.

## Editar la ficha del contacto

En la ficha, dentro de la conversación, aparece **Editar**. Se pueden cambiar nombre, nombre de pila, apellido, correo, teléfono, empresa, dirección, ciudad, provincia, código postal, país, sitio web, fuente y zona horaria, además de los campos personalizados que tenga la cuenta.

Las **etiquetas** se editan en la misma pantalla: se añaden escribiendo y pulsando «Añadir etiqueta», y se quitan con la × de cada etiqueta. Se escriben como altas y bajas, nunca sustituyendo la lista completa, así que una etiqueta puesta por un workflow o por otra persona no se pierde por editar desde aquí.

Sólo se envía lo que cambiaste: los campos que no tocas no se reescriben. Hace falta permiso de escritura de contactos (`contacts.write`); sin él, la ficha se ve pero el botón no aparece. Un correo o un teléfono con formato imposible se rechaza antes de llegar a la cuenta.

Todavía no se editan desde aquí el «no molestar», la fecha de nacimiento ni el responsable asignado: el primero necesita explicarse bien, el segundo admite seis formatos distintos y el tercero requiere un selector de usuarios. Llegarán después.

## Selector de canal

El caso que pediste: alguien escribe por Instagram y te pasa su número para seguir por WhatsApp. Ahora, encima del campo de respuesta, aparecen los canales por los que ese contacto puede recibir mensajes y se cambia con un toque. Lo escrito se conserva al cambiar de canal.

Qué se ofrece lo decide el servidor, no el navegador: siempre el canal del propio hilo; **SMS** y **WhatsApp** si la ficha tiene teléfono; **Email** si tiene correo, pidiendo entonces el asunto. Instagram, Messenger y el chat web sólo aparecen si son el canal del hilo, porque no hay forma documentada de iniciar una conversación por esas vías. Si todavía no se ha cargado la ficha, sólo se ofrece el canal del hilo. La elección se vuelve a comprobar al enviar, así que no se puede forzar un canal desde el navegador.

## Diagnóstico

Edición de ficha: `invalid_contact_fields` (un campo demasiado largo o con forma inesperada), `invalid_contact_email`, `invalid_contact_phone`, `nothing_to_change` (no cambiaste nada), `action_forbidden` (tu usuario no puede modificar la ficha) y los códigos de acción ya conocidos. Envío: `unsupported_channel` significa que ese canal no está disponible para ese contacto.
