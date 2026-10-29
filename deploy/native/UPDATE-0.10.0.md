# Actualizar Ágora a 0.10.0

Esta versión añade al chat las tareas, las notas y las citas del contacto, con acciones rápidas para crear una nota, crear una tarea y completar o reabrir una tarea.

El paquete es acumulativo: incluye todo lo de 0.8.1 y 0.9.0, así que se puede instalar directamente sobre cualquiera de las dos sin pasar por las intermedias.

## Si vienes de 0.8.0: paso obligatorio

0.8.1 sustituyó el envío SMTP de los códigos de acceso del equipo por el correo de una cuenta conectada. Al actualizar desde 0.8.0, **el equipo no podrá pedir códigos hasta que elijas el canal de acceso** (paso 3). Tu acceso de administrador con contraseña sigue funcionando siempre. Los detalles están en `UPDATE-0.8.1.md`, en el repositorio.

## Pasos

1. Extraer `agora-update-0.10.0.zip` sobre las carpetas existentes. El paquete no contiene `local.php`, claves ni contraseñas.
2. Entrar con el administrador, abrir **Conexiones** y pulsar **Actualizar base de datos**. Aplica las migraciones pendientes, 009, 010 y 011 incluidas, y es idempotente.
3. Sólo si vienes de 0.8.0: en **Canal de acceso del equipo**, elegir la cuenta conectada que entregará los códigos y pulsar **Probar canal de acceso**.
4. Abrir una conversación y la ficha del contacto. Al final de la ficha aparecen **Tareas**, **Notas** y **Citas**, y debajo las acciones rápidas.

## Permisos necesarios

Las tres listas necesitan `contacts.readonly`, que ya hacía falta para la ficha del contacto desde 0.8.0. Las acciones rápidas necesitan además `contacts.write`: sin ese permiso, las listas se ven pero los botones y formularios no aparecen.

Los integrantes del equipo necesitan el módulo de contactos activo en su usuario de la cuenta conectada para ver las listas, y permiso de escritura de contactos para las acciones. Las **citas sólo se muestran a quien tenga el módulo de citas activo**, aunque el endpoint no lo exija: el permiso del módulo en la cuenta es lo que decide qué ve cada persona. El administrador nativo las ve siempre.

## Comportamiento

Las tareas se ordenan con las pendientes primero y por fecha de vencimiento; las notas y las citas, de más reciente a más antigua. Se muestran como máximo 25 registros de cada tipo y, al alcanzar ese límite, el bloque lo indica en lugar de dar la lista por completa. El responsable de una tarea y el autor de una nota se resuelven contra el equipo ya importado; si la persona no está importada, el campo se deja vacío en lugar de mostrar un identificador.

Al crear una tarea hay que indicar título y fecha de vencimiento, porque el contrato de la cuenta los exige; el detalle es opcional. La fecha se envía como marca UTC explícita y una fecha sin hora se fija al mediodía UTC para que no cambie de día según la zona del servidor. La tarea se crea sin asignar: asignarla a otra persona requiere elegir usuario y esa interfaz llegará más adelante. La nota se guarda sin atribuir autoría, porque la clave de la cuenta no identifica a la persona que actúa y atribuirla sería falsear el dato.

Crear una nota o una tarea no es una operación repetible en la cuenta, así que cada acción lleva una clave propia derivada de su contenido. Si pulsas dos veces o se corta la conexión, la repetición devuelve el primer resultado en lugar de crear un duplicado. Si el resultado queda sin confirmar, Ágora lo dice y bloquea repetir el mismo contenido: conviene actualizar la ficha y comprobar antes de volver a intentarlo.

## Diagnóstico

En las listas: `activity_upstream_<estado>` cuando la cuenta rechaza la consulta y `activity_invalid_response` cuando la respuesta no tiene la forma esperada. En las acciones: `action_forbidden` (tu usuario no puede modificar la ficha), `invalid_note`, `invalid_task`, `invalid_due_date`, `invalid_task_id`, `invalid_task_state` (datos del formulario), `action_rejected` (la cuenta rechazó la acción; incluye el código), `action_paused` (cuota; espera los segundos indicados), `action_busy` (hay otra acción en curso sobre ese contacto), `request_conflict` (la clave corresponde a otra acción) y `action_unconfirmed` (resultado incierto). En ninguno de estos casos se desactiva la conexión: la bandeja y las respuestas siguen funcionando.
