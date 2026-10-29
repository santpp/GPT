# Actualizar Ágora a 0.15.0

La bandeja pasa a leerse siempre de la cuenta, los filtros y la búsqueda los resuelve la cuenta, las conversaciones se marcan como leídas al abrirlas, los adjuntos se ven y el menú lleva el contador de no leídas.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.15.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos y la aplicación se actualizan solas.

## Permiso nuevo: marcar como leída

Marcar una conversación como leída necesita **`conversations.write`** en la clave de la cuenta, que hasta ahora no hacía falta. Sin él todo lo demás sigue funcionando y sólo falla el marcado; el contador de no leídas seguirá como lo tenga la cuenta.

## Siempre se lee de la cuenta

Antes cada pantalla guardaba su resultado un minuto (la ficha, las oportunidades, la actividad, los contactos, el tablero) y servía esa copia. Ahora **cada carga pregunta a la cuenta**. Lo guardado sólo entra cuando el presupuesto de peticiones está en pausa o la lectura falla, y en ese caso la pantalla lo dice y muestra de cuándo es la copia, en lugar de hacerla pasar por actual.

Conviene saber el coste: esto multiplica las peticiones. El presupuesto interno son 20 peticiones cada 10 segundos por cuenta, suficiente para un uso normal, pero con varias personas sobre la misma subcuenta a la vez pueden aparecer pausas. Cuando ocurra, verás la última lista con su aviso, nunca un error en blanco.

## Filtros y búsqueda, resueltos por la cuenta

La búsqueda ya no filtra lo que estaba cargado: pregunta a la cuenta, así que encuentra conversaciones que no estaban en la ventana de 20, 50 o 100.

Los filtros son:

- **Todas** y **No leídas**, como pediste.
- **Mías**, que aparece sólo para los integrantes del equipo y pide a la cuenta las asignadas a esa persona.
- **Canal**: una fila de filtros con los canales que la cuenta usa, para ver sólo WhatsApp, sólo Instagram, etc.

Los filtros se combinan entre sí y con la búsqueda. El pie de la lista dice siempre qué filtro está puesto y cuántas conversaciones hay.

## Marcar como leída

Al abrir una conversación con mensajes sin leer, Ágora se lo dice a la cuenta y el contador baja, en la lista y en el menú. Si falta el permiso de escritura, abrirla sigue funcionando y sólo no se marca.

## Contador en el menú

**Conversaciones** lleva ahora el número de mensajes sin leer de la cuenta en uso, para verlo sin entrar.

## Adjuntos

Los adjuntos que llegan se ven: las imágenes en línea y el resto como enlace con su nombre de archivo. Sólo se siguen enlaces `https` y el nombre sale de la ruta del archivo, nunca de texto que mande el remitente. Enviar adjuntos todavía no está: necesita almacenamiento propio.

## Plantillas de WhatsApp: no incluidas

El endpoint de plantillas de la cuenta acepta el tipo `whatsapp`, pero **exige un parámetro `originId` cuyo significado no aparece documentado**, y la respuesta sólo publica estructura para SMS y correo, no para WhatsApp. No lo implemento adivinando un parámetro obligatorio. Hace falta comprobar contra tu cuenta qué valor espera; si lo averiguas, es un añadido corto.

## Diagnóstico

`inbox_upstream_<estado>` e `inbox_invalid_response` para la bandeja; `read_upstream_<estado>` y `read_failed` al marcar como leída. Si la lista dice «Mostrando la última lista, de …», es que la cuenta no respondió o el presupuesto está en pausa: se reintenta solo.
