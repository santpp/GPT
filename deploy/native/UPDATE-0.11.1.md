# Actualizar Ágora a 0.11.1

Corrige los dos fallos que seguían apareciendo en el teléfono, adapta la interfaz a pantallas de 6,7 pulgadas y quita el botón de actualizar la base de datos.

El paquete es acumulativo: incluye todo lo de 0.8.1, 0.9.0, 0.10.0 y 0.11.0.

## Pasos

1. Extraer `agora-update-0.11.1.zip` sobre las carpetas existentes.
2. Entrar en la aplicación. **Ya no hay que pulsar nada para la base de datos**: las actualizaciones pendientes se aplican solas en la primera petición.
3. Si la cabecera muestra **Nueva versión**, pulsarlo una vez para recargar con la interfaz nueva.

## La base de datos se actualiza sola

Desaparece el botón **Actualizar base de datos**. Las migraciones son aditivas e idempotentes y se aplican bajo cerrojo en la primera petición posterior a una actualización, así que instalar es copiar los archivos y entrar. La pantalla de preparación sigue existiendo y sigue informando si algo queda pendiente: si la actualización automática no pudiera aplicarse, lo dirá en lugar de romperse, y el resto de comprobaciones (entorno, dominio, protección) siguen igual.

## Fallos corregidos

**«El historial recibido no tiene el formato esperado».** En 0.11.0 se toleraron tres diferencias y el error siguió apareciendo, lo que descarta esas tres como causa. La explicación que queda es que la cuenta responde con los mismos campos envueltos un nivel más abajo de lo que documenta el contrato. Ágora acepta ahora las dos formas. Si aun así fallara, **el mensaje incluye el motivo concreto** (por ejemplo `Código: messages_not_a_list`): ese código dice exactamente qué parte no encaja, así que conviene enviarlo tal cual.

**«Consulta pausada para respetar la cuota. Espera 2 segundos».** No era la cuota. Cada cuenta tiene un cerrojo para no lanzar dos peticiones a la vez, y ese cerrojo fallaba en el acto en lugar de esperar turno; al pedir la ficha del contacto sus cuatro bloques simultáneamente, la pantalla competía consigo misma y con el sondeo del chat. Ahora el cerrojo espera su turno y la ficha pide los bloques uno detrás de otro. El presupuesto de peticiones no cambia.

## Interfaz en el teléfono

Pensada para 6,7 pulgadas (unos 412 × 915 puntos):

- Nada tocable por debajo de 44 píxeles: pestañas inferiores, botones del chat, casillas de tarea, enlaces de llamar y escribir, y los botones de Conexiones.
- La conversación ocupa la pantalla en vez de flotar en una tarjeta: el historial crece hasta llenar el alto disponible y el campo de respuesta queda siempre alcanzable, por encima de la barra inferior y respetando el área segura del teléfono.
- Los campos de texto usan 16 píxeles, así que el navegador ya no hace zoom al escribir.
- La cabecera deja de apretujarse: muestra dónde estás y el selector de cuenta, y el aviso de versión nueva pasa a una línea propia.
- Ninguna pantalla se desplaza en horizontal: contactos en una columna, el tablero de oportunidades en columnas apiladas.

Todo esto se comprueba de forma automática en `tests/mobile-ui.py`, que mide los objetivos táctiles y el ancho real en un viewport de 412 × 915.

## Diagnóstico

Códigos del historial: `messages_messages_missing`, `messages_messages_not_a_list`, `messages_page_too_large`, `messages_conversation_mismatch`, `messages_location_mismatch`. Si vuelve a aparecer el error, el código concreto es lo único que hace falta para identificarlo.
