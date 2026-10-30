# Actualizar Ágora a 0.16.0

Nuevo **Tablero** con las cifras de la cuenta, y la pestaña de **Contactos** rediseñada: lista a un lado y la ficha completa al otro, con edición de todos los campos, los campos personalizados y las etiquetas, sin tener que entrar por una conversación.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.16.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos y la aplicación se actualizan solas.

No hay permisos nuevos: el tablero y la ficha usan los mismos que ya tenías (`conversations.readonly`, `contacts.readonly`, `contacts.write`, `opportunities.readonly`).

## Tablero

Primera pestaña del menú. En una sola lectura de la cuenta muestra:

- Conversaciones abiertas y cuántas están sin leer.
- Número de contactos.
- Oportunidades abiertas, lo que está en juego y el reparto por embudo y etapa, con los nombres reales.

Quien tenga el acceso limitado a sus propios datos ve sus cifras, y el tablero lo dice. En ese caso no aparece el número de contactos: la cuenta no permite pedir la lista de contactos filtrada por responsable, y preferí no mostrar el total de la cuenta como si fuera suyo.

Todo el tablero cuesta una sola ranura del presupuesto de peticiones.

## Contactos: lista y ficha

- La lista queda a la izquierda, con la búsqueda que ya pregunta a la cuenta.
- Al elegir a alguien se abre su ficha completa a la derecha: datos, campos personalizados y etiquetas.
- **Editar ficha** abre el mismo editor que ya usabas dentro de la conversación: los catorce campos, los campos personalizados con el control que toca cada tipo (lista, casillas, número, fecha, texto largo) y las etiquetas, salgan o no rellenadas.
- Sólo se envía lo que cambiaste. Las etiquetas viajan por su vía propia, de modo que añadir una no borra las demás.
- En móvil la lista y la ficha se alternan, con botón de volver, y los botones son del tamaño que pide una pantalla de 6,7".

## Calendario y workflows

No se replican, como pediste. Los workflows se siguen manejando directamente en la cuenta. Quedan fuera del roadmap.

## Menú

El menú pasa a cinco entradas: Tablero, Conversaciones, Contactos, Oportunidades y (para administradores) Conexiones. En móvil siguen entrando todas en la barra inferior.

## Diagnóstico

`dashboard_upstream_<estado>` y `dashboard_invalid_response` para el tablero; `record_upstream_<estado>` y `record_invalid_response` al abrir o guardar una ficha desde la pantalla de contactos. Si la cuenta tiene el presupuesto en pausa, el tablero lo dice y se reintenta solo.

## Enlaces directos

`/tablero`, `/contactos` y `/oportunidades` ya cargan la aplicación si los escribes o los compartes. Antes sólo funcionaban navegando desde dentro: faltaban en las reglas del servidor web.
