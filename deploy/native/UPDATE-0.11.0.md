# Actualizar Ágora a 0.11.0

Esta versión reorganiza la aplicación alrededor de la cuenta real: desaparecen las vistas de ejemplo, la subcuenta se elige una sola vez en la cabecera, y Contactos y Oportunidades pasan a mostrar datos de la cuenta. Además corrige dos fallos observados en producción.

El paquete es acumulativo: incluye todo lo de 0.8.1, 0.9.0 y 0.10.0.

## Si vienes de 0.8.0: paso obligatorio

0.8.1 sustituyó el envío SMTP de los códigos del equipo por el correo de una cuenta conectada. Al actualizar desde 0.8.0 hay que elegir el canal de acceso en **Conexiones** antes de que el equipo pueda pedir códigos. Tu acceso de administrador con contraseña sigue funcionando siempre.

## Pasos

1. Extraer `agora-update-0.11.0.zip` sobre las carpetas existentes.
2. Entrar y pulsar **Actualizar base de datos** en Conexiones. Aplica las migraciones pendientes, 012 y 013 incluidas, y es idempotente.
3. Recargar la aplicación una vez. Como cambia el shell, el navegador ofrecerá **Nueva versión** en la cabecera; al pulsarlo se recarga con la versión nueva.

## Qué cambia en la interfaz

Sin sesión sólo se ve la pantalla de acceso. Al entrar se abre directamente el espacio con los datos de la cuenta: no hay conversaciones, contactos ni oportunidades de ejemplo en ninguna pantalla.

La subcuenta se elige en el selector **Cuenta** de la cabecera y manda en todas las pantallas. La elección se recuerda en el navegador y se vuelve a comprobar en cada entrada contra las cuentas que ese usuario puede ver. El desplegable que había dentro de la bandeja desaparece.

El botón **Actualizar** de la bandeja y el **↻** del chat desaparecen: la bandeja se refresca sola según el intervalo de la conexión, el chat cada diez segundos, y la primera visita pide los datos por su cuenta en lugar de esperar a que pulses nada. El único botón de actualizar que queda es **Nueva versión** en la cabecera, que recarga la aplicación cuando hay una versión nueva instalada.

**Contactos** lista los contactos de la cuenta con búsqueda por nombre, correo o teléfono. **Oportunidades** muestra un tablero por embudo con las etapas reales, el número de oportunidades y el total de cada columna. Ambas respetan los permisos del usuario y, con **sólo datos asignados**, muestran únicamente lo suyo y lo indican.

**Calendario** y **Mis tareas** desaparecen del menú: sólo mostraban ejemplos. Las tareas y las citas siguen estando dentro de la conversación, con datos reales, desde 0.10.0. Volverán como pantallas propias cuando estén construidas.

## Fallos corregidos

**El historial decía «messages_invalid_response».** La validación descartaba la conversación entera ante tres diferencias inofensivas: una página con más mensajes de los pedidos, un registro sin identificador, o una referencia de paginación que no aparecía entre los mensajes recibidos. Ahora una página mayor se acepta, un registro sin identificador se omite y el resto se muestra, y una referencia inservible detiene la paginación en lugar de tirar el historial. Las comprobaciones de cuenta y conversación siguen rechazando la respuesta, y cada rechazo indica su motivo concreto.

**«Está en espera para respetar la cuota. Espera 2 segundos.»** El presupuesto interno imponía un segundo entre peticiones de una misma cuenta, diez veces más restrictivo que el límite publicado, así que la ficha del contacto se bloqueaba a sí misma al pedir ficha, campos, oportunidades y actividad. Ahora el presupuesto es una ventana de 20 peticiones cada 10 segundos por cuenta, y la ficha pide sus datos a la vez en lugar de escalonarlos: aparece completa en una o dos décimas en vez de en tres segundos y medio.

## Permisos

Sin cambios respecto a 0.10.0. La pantalla de contactos necesita `contacts.readonly` y la de oportunidades `opportunities.readonly`, ambos ya necesarios para la ficha y el bloque de oportunidades del chat.

## Diagnóstico

Pantallas nuevas: `contacts_upstream_<estado>`, `contacts_invalid_response`, `board_upstream_<estado>`, `board_invalid_response`. Historial: `messages_page_too_large`, `messages_conversation_mismatch`, `messages_location_mismatch`, `messages_messages_missing`, `messages_messages_not_a_list`. Ninguno desactiva la conexión.
