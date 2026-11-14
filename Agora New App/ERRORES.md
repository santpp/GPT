# Agora New App · lista de errores

Errores vistos al instalar y probar en `tests.agoradigital.app` (13 y 14 de noviembre de 2026), con su causa y su estado. Se actualiza en cada versión.

**Estados:** Corregido (ya está en el paquete) · Por confirmar (falta verlo en el servidor) · Pendiente (no hecho todavía).

## Errores de la instalación en tests

| N.º | Qué se vio | Por qué pasó | Qué se hizo | Estado |
|---|---|---|---|---|
| 1 | Al entrar: «Origen no autorizado». | `app_url` de `config.php` no era igual a la dirección del navegador. La app compara las dos para que nadie inicie sesión desde otra página. No era un fallo del código. | El aviso ahora dice la causa: «la dirección de esta página no coincide con app_url en config.php». La guía ya lo traía en la tabla de fallos. | Corregido en 2.3.1 |
| 2 | La ventana «Nueva subcuenta» sin estilo: opciones como círculos del navegador, botones grises, colores a todo el ancho, «GuardarActiva» pegado, otra letra. | El navegador pintó la ventana nueva con hojas de estilo de una versión anterior. En local, con los archivos del mismo ZIP, la ventana sale bien (también imitando la tablet). Al bloquear las hojas nuevas se reproduce la captura. La app guardaba sus archivos para funcionar sin conexión y servía primero lo guardado; además, la versión nueva esperaba a que se cerraran todas las pestañas. | Cada hoja de estilo y cada programa se piden con su versión (`?v=2.3.1`). El guardado sin conexión ahora busca primero en la red y la versión nueva toma el control al instalarse. | Corregido en 2.3.1. Por confirmar en tests |
| 3 | No había dónde poner el token de agencia. | Sí existía, pero escondido dentro de Agencia → Consumos. En la ventana de la subcuenta solo había un «Company ID (opcional)» que no explicaba nada. | Tarjeta «Conexión de la agencia» arriba en Agencia → Subcuentas, con Company ID, token de agencia y «Guardar y probar». | Corregido en 2.3.1 |
| 4 | El Company ID se pedía en cada subcuenta. | Diseño equivocado: el Company ID es de la agencia, no de la subcuenta. | Se quitó de la subcuenta. Se escribe una sola vez en la conexión de la agencia. | Corregido en 2.3.1 |
| 5 | Al conectar la agencia no se sabía si el token servía hasta abrir un informe. | No había prueba de conexión para la agencia. | «Guardar y probar» consulta la billetera y explica el fallo: token rechazado, sin permiso o Company ID equivocado. | Corregido en 2.3.1 |
| 6 | Guardar o quitar el token de agencia no dejaba rastro. | Esas acciones las atendía el motor de Ágora Mobile, que no escribe en el registro del ERP. | Guardar, probar y quitar quedan en el Registro de actividad (sin el token). | Corregido en 2.3.1 |
| 7 | La ventana de la subcuenta era larga y la conexión quedaba al final. | Se había agregado como último recuadro. | La conexión va justo después de los datos de la empresa, con Location ID y token lado a lado. | Corregido en 2.3.1 |
| 15 | Al crear una subcuenta y pulsar «Ir a subcuenta»: «La subcuenta no está autorizada. Actualiza Agencia e inténtalo de nuevo». | La app lee una sola vez, al iniciar sesión, la lista de subcuentas a las que llegas. La subcuenta recién creada no estaba en esa lista hasta recargar la página. El servidor sí la autorizaba. | La lista se vuelve a pedir al servidor al cargar Agencia y antes de entrar a una subcuenta. El aviso ahora distingue el caso de un Superadmin sin esa subcuenta asignada. | Corregido en 2.3.2 |

## Errores encontrados al revisar (no los vio el usuario)

| N.º | Qué era | Riesgo | Qué se hizo | Estado |
|---|---|---|---|---|
| 8 | `servidor/config/config.php` de Agora New App no estaba en `.gitignore`. | Alguien podía subir las claves al repositorio por descuido. No llegó a pasar. | Añadido a `.gitignore` (junto con `servidor/storage/`). | Corregido |
| 9 | La pantalla de Consumos nombraba el permiso técnico del token. | Texto técnico en pantalla. | Ahora lo dice en palabras; el nombre exacto del permiso queda en la guía de instalación. | Corregido en 2.3.1 |
| 10 | Una contraseña de dueño quedó visible en una captura de pantalla. | Quien vea la captura la conoce. | Cambiarla desde Perfil. No es un fallo de la app. | Pendiente (lo hace el dueño) |

## Sin probar o pendiente

| N.º | Qué falta | Por qué importa |
|---|---|---|
| 11 | Probar las escrituras con la cuenta real: enviar un mensaje, asignar una conversación, editar un contacto, mover y crear una oportunidad, marcar ganada. | Las atiende el mismo código de Ágora Mobile 1.10.14, pero en esta app solo se probaron contra una cuenta simulada; desde el entorno de desarrollo no hay salida a la cuenta real. |
| 12 | Confirmar el error 2 en tests después de subir la 2.3.2. | La causa se dedujo sin acceso al servidor. Si la ventana sigue sin estilo tras recargar, hay que mirar si `public_html/assets/diseno.css` y `agora-new.css` del servidor son los del ZIP. |
| 13 | Cabeceras de seguridad en OpenLiteSpeed. | OpenLiteSpeed no aplica las líneas `Header` del `.htaccess`; van en el vHost (paso 9 de la guía). |
| 14 | La pantalla de la bandeja usa el código de una versión anterior de Ágora Mobile. | Le faltan las mejoras de la 1.10.14 (búsqueda, embudos nuevos, línea de asignación). |

## Cómo comprobar la versión instalada

- `https://TU-DOMINIO/api/health` debe decir `"version":"2.3.2"`.
- `https://TU-DOMINIO/version.json` debe decir lo mismo.
- Si una pantalla se ve rara tras actualizar: recarga una vez. Si sigue, en Chrome toca el icono a la izquierda de la dirección → Configuración del sitio → Borrar datos, y vuelve a entrar.
