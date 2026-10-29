# Actualizar Ágora a 0.9.0

Esta versión añade el contexto de oportunidad dentro de la conversación: oportunidad activa, pipeline, etapa, estado, valor, responsable y acceso a todas las oportunidades del contacto.

1. Extraer `agora-update-0.9.0.zip` sobre las carpetas existentes. El paquete no contiene `local.php`, claves ni contraseñas.
2. Entrar con el administrador, abrir **Conexiones** y pulsar **Actualizar base de datos**. La migración es idempotente: puede ejecutarse de nuevo sin efecto.
3. Abrir una conversación y la ficha del contacto. El bloque **Oportunidad activa** aparece al final de la ficha, poco después de la ficha y de los nombres de campos, para no consumir varias consultas a la vez.

## Permisos necesarios

La clave de la cuenta necesita `opportunities.readonly`. Con ese permiso se obtienen las oportunidades del contacto y los nombres reales de pipeline y etapa. Si falta, el bloque indica que no se pudieron consultar y el resto de la ficha sigue funcionando. Si la cuenta permite las oportunidades pero no los pipelines, el bloque se muestra sin nombres de pipeline ni de etapa en lugar de mostrar identificadores.

Los integrantes del equipo necesitan además el módulo de oportunidades activo en su usuario de la cuenta conectada. Un integrante con **sólo datos asignados** ve únicamente las oportunidades cuyo responsable es él, y la oportunidad activa se elige dentro de ese subconjunto.

## Comportamiento

La oportunidad activa es la abierta más reciente; si el contacto no tiene ninguna abierta, se muestra el registro modificado más recientemente, con su estado real (Ganada, Perdida o Abandonada). El resto de oportunidades queda plegado bajo **Ver todas las oportunidades**. Un contacto sin oportunidades muestra un estado vacío explícito.

El importe se presenta con formato español y sin símbolo de moneda, porque la respuesta de la cuenta no incluye el código de divisa; no se supone ninguna. Se consultan las 20 oportunidades más recientes del contacto y, si se alcanza esa ventana, el bloque lo indica en lugar de dar la lista por completa.

Las oportunidades de cada contacto se guardan 60 segundos y los nombres de pipeline una hora, para respetar las cuotas de la cuenta. Las dos lecturas comparten una sola ranura del presupuesto, así que la primera apertura ya muestra los nombres reales.

## Diagnóstico

Los códigos posibles son `opportunities_upstream_<estado>` cuando la cuenta rechaza la consulta y `opportunities_invalid_response` cuando la respuesta no tiene la forma esperada. En ninguno de los dos casos se sustituye el último resultado guardado ni se desactiva la conexión: la bandeja y las respuestas siguen funcionando.
