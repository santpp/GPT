# Actualizar Ágora a 0.13.0

La ficha del contacto pasa a mostrar y permitir editar **todos** sus campos, incluidos todos los campos personalizados que define la cuenta, también los que todavía están vacíos.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.13.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos y la aplicación se actualizan solas.

## Qué se ve y se edita ahora

Antes la ficha mostraba la dirección como una sola línea unida y, al editar, los campos de dirección aparecían vacíos. Ahora cada parte viaja por separado: **dirección, ciudad, provincia o estado, código postal y país**, además de **sitio web**, **zona horaria** y **fuente**.

Se añaden tres campos que antes no estaban:

- **Responsable**: se elige de una lista con el equipo importado de esa misma cuenta. No se puede asignar a alguien que no pertenezca a ella.
- **No contactar**: la casilla de exclusión del contacto.
- **Fecha de nacimiento**.

## Campos personalizados: todos, con su tipo

La cuenta sólo devuelve los campos personalizados que **tienen valor**, así que hasta ahora un campo definido y vacío era invisible y no había forma de rellenarlo desde Ágora. Ahora se combinan las definiciones de la cuenta con los valores del contacto, de modo que aparecen **todos** los campos definidos, vacíos incluidos, ordenados como en la cuenta.

Cada uno se muestra con el control que le corresponde:

| Tipo del campo | Cómo se edita |
| --- | --- |
| Texto, teléfono, correo | Campo de una línea |
| Texto largo, lista de textos | Área de varias líneas |
| Numérico, monetario | Campo numérico |
| Fecha | Selector de fecha |
| Casilla | Casilla de sí/no |
| Opción única, radio | Desplegable con las opciones de la cuenta |
| Opción múltiple | Casillas, una por opción |
| Archivo, firma | Se muestra pero no se edita aquí |

Los valores se envían con su forma propia: un número como número, una casilla como sí/no y una selección múltiple como lista, no todo como texto.

## Lo que no cambia

Se sigue enviando únicamente lo que modificaste, así que editar no reescribe lo que no tocaste. Las etiquetas siguen viajando como altas y bajas, nunca sustituyendo la lista completa. Hace falta permiso de escritura de contactos; sin él la ficha se ve entera pero no aparece **Editar**.

La fecha de nacimiento se envía en formato `AAAA-MM-DD`. La documentación de la cuenta admite seis formatos distintos para ese campo y no precisa cuál aplica, así que Ágora usa sólo el formato sin ambigüedad y rechaza una fecha imposible (un 31 de febrero, por ejemplo) antes de enviarla.

## Diagnóstico

`invalid_birth_date` (fecha inexistente o con otro formato), `unknown_assignee` (ese responsable no pertenece al equipo importado de la cuenta), `invalid_contact_fields`, `invalid_contact_email`, `invalid_contact_phone`, `nothing_to_change` y `action_forbidden`.
