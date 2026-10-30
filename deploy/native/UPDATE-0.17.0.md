# Actualizar Ágora a 0.17.0

Las oportunidades pasan a ser editables: crear una desde la conversación, editarla, moverla de etapa, cambiar su estado y reasignarla, tanto en el chat como en el tablero de embudos.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.17.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos y la aplicación se actualizan solas.

## Permiso nuevo: escritura de oportunidades

Hace falta **`opportunities.write`** en la clave de la cuenta, que hasta ahora no era necesaria. Sin ese permiso todo lo demás sigue igual y simplemente **no aparece ningún botón de edición**: ni «Nueva oportunidad» ni «Editar». Preferí eso a ofrecerlo y que fallara al guardar.

## Qué se puede hacer

En la ficha del contacto, dentro de la conversación:

- **+ Nueva oportunidad**: nombre, embudo, etapa, valor, responsable y estado.
- **Editar oportunidad** en la que se muestra.

En el **tablero de oportunidades**, cada tarjeta trae **Editar**: cambia la etapa (mover la tarjeta), el valor, el responsable o el estado, y el tablero se recarga con la tarjeta ya en su nueva columna.

El formulario sólo ofrece los embudos y etapas que tiene la cuenta, y como responsable sólo a integrantes activos del equipo importado. Al cambiar de embudo, la lista de etapas cambia con él. Se envía únicamente lo que modificaste.

Quien tenga el acceso limitado a sus propios datos sólo puede editar oportunidades que tenga asignadas, y lo que abre queda a su nombre para que no deje de verlo.

## Repeticiones

Crear una oportunidad no es una operación repetible en la cuenta: la misma petición dos veces abriría dos. Cada guardado lleva su propia clave, igual que el envío de mensajes y las notas o tareas. Si el resultado queda incierto (corte de red, 500), Ágora **no dice que se guardó**: pide actualizar antes de repetir, y bloquea repetir lo mismo mientras no se sepa qué pasó.

## Motivos de pérdida: no incluidos

Marcar una oportunidad como **perdida** funciona, pero sin elegir motivo. El endpoint de motivos existe y el campo es opcional, pero **la especificación no publica ninguna propiedad del motivo**: no hay forma documentada de leer su identificador ni su texto. No lo implemento adivinando. Si lo necesitas, hay que comprobar contra tu cuenta qué devuelve, y es un añadido corto.

## Agendar cita: fuera del roadmap

Sale del roadmap, como pediste, junto con calendario y workflows. Las citas que ya existan se siguen viendo en la ficha del contacto.

## Diagnóstico

`opportunity_upstream_<estado>` y `opportunity_invalid_response`; `pipelines_upstream_<estado>` al leer los embudos. Códigos propios: `unknown_pipeline`, `unknown_stage`, `unknown_assignee`, `assignee_forbidden`, `invalid_status`, `nothing_to_change`, `action_rejected`, `action_unconfirmed`, `action_paused`.
