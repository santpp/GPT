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
| 16 | En la Bandeja, el botón de la ficha del contacto (icono de persona) no hacía nada. | El programa de la bandeja activa un estado con un nombre (`contact-open`) y la hoja de estilos esperaba otro (`open`). La ficha se cargaba, pero nunca se mostraba. Venía así en el código recibido; no se había probado ese botón. | Estilo para ese estado: en escritorio la ficha sale al lado; en el teléfono reemplaza al chat y se cierra con la ×. | Corregido en 2.3.3 |
| 17 | Otros estados de la bandeja sin estilo, por la misma causa: modo plantilla de WhatsApp (no ocultaba la caja de texto), campo modificado en el editor de ficha, imagen adjunta cargando, venta ganada recién guardada. | Igual que el 16. Se encontraron al revisar todos los estados del programa contra las hojas. | Estilos añadidos. Comprobación automática nueva: `tests/ui-estados.py` falla si un estado del programa no tiene estilo. | Corregido en 2.3.3 |
| 18 | El buscador de la Bandeja mostraba dos bordes al tocarlo. | Se sumaban el borde del recuadro y el del campo de texto. | Un solo borde. | Corregido en 2.3.3 |
| 19 | En la ficha, la oportunidad salía con letras gigantes y el editor con recuadros sin estilo; «No contactar» era una casilla suelta. | Estilos incompletos en el código recibido. | Filas dato · valor, recuadros por grupo e interruptor. | Corregido en 2.3.3 |
| 20 | El ejecutivo veía los 24 módulos y en 8 le salía «No se pudo cargar · Tu rol no permite esta función». | La pantalla no conocía los permisos reales; el servidor sí los aplicaba. | El servidor envía los permisos efectivos y el menú muestra solo lo permitido. | Corregido en 2.4.0 |
| 21 | Un ejecutivo cuyo correo no coincidía con el de la cuenta no veía nada, sin explicación. | El cruce era solo por correo e invisible. | Enlace explícito en Usuarios y roles, con el correo como sugerencia, y aviso al ejecutivo sin enlace. | Corregido en 2.4.0 |
| 22 | Las tareas de Ágora y las de la cuenta eran dos listas distintas. | El módulo Tareas guardaba solo en la base de Ágora. | Cada tarea se escribe en la cuenta y se guarda una sola vez con el mismo identificador. | Corregido en 2.4.0 |
| 23 | «Agregar manual» y «Excel» en Contactos y Oportunidades guardaban en la base de Ágora mientras la lista se leía de la cuenta. | Dos orígenes de datos para la misma pantalla. | Con la subcuenta conectada esos botones no existen y el servidor rechaza la carga. | Corregido en 2.4.0 |
| 24 | Ajustes nombraba al proveedor y decía que la sincronización «aún no está habilitada»; Ayuda decía «Versión 1.4.3» y hablaba de la demo. | Textos del código recibido, sin actualizar. | Reescritos. | Corregido en 2.4.0 |
| 25 | Auditoría y Registro mostraban códigos (`subcuenta.cobros`, `invoice`, `sesion`). | No usaban la tabla de textos legibles. | La usan, con las acciones que faltaban. | Corregido en 2.4.0 |

## Errores encontrados al revisar (no los vio el usuario)

| N.º | Qué era | Riesgo | Qué se hizo | Estado |
|---|---|---|---|---|
| 8 | `servidor/config/config.php` de Agora New App no estaba en `.gitignore`. | Alguien podía subir las claves al repositorio por descuido. No llegó a pasar. | Añadido a `.gitignore` (junto con `servidor/storage/`). | Corregido |
| 9 | La pantalla de Consumos nombraba el permiso técnico del token. | Texto técnico en pantalla. | Ahora lo dice en palabras; el nombre exacto del permiso queda en la guía de instalación. | Corregido en 2.3.1 |
| 10 | Una contraseña de dueño quedó visible en una captura de pantalla. | Quien vea la captura la conoce. | Cambiarla desde Perfil. No es un fallo de la app. | Pendiente (lo hace el dueño) |

## Sin probar o pendiente

| N.º | Qué falta | Por qué importa |
|---|---|---|
| 11 | Probar las escrituras con la cuenta real: enviar un mensaje, asignar una conversación, editar un contacto, mover y crear una oportunidad, marcar ganada, crear, corregir y completar una tarea, y crear un contacto nuevo. | Las atiende el mismo código de Ágora Mobile 1.10.14, pero en esta app solo se probaron contra una cuenta simulada; desde el entorno de desarrollo no hay salida a la cuenta real. |
| 12 | Confirmar el error 2 en tests después de subir la 2.6.0. | La causa se dedujo sin acceso al servidor. Si la ventana sigue sin estilo tras recargar, hay que mirar si `public_html/assets/diseno.css` y `agora-new.css` del servidor son los del ZIP. |
| 13 | Cabeceras de seguridad en OpenLiteSpeed. | OpenLiteSpeed no aplica las líneas `Header` del `.htaccess`; van en el vHost (paso 9 de la guía). |
| 14 | (Resuelto en 2.5.0) La bandeja usaba el código de una versión anterior de Ágora Mobile. | Bandeja, Contactos y Embudos ya llevan lo de la 1.10.14. |

## Cómo comprobar la versión instalada

- `https://TU-DOMINIO/api/health` debe decir `"version":"2.6.0"`.
- `https://TU-DOMINIO/version.json` debe decir lo mismo.
- Si una pantalla se ve rara tras actualizar: recarga una vez. Si sigue, en Chrome toca el icono a la izquierda de la dirección → Configuración del sitio → Borrar datos, y vuelve a entrar.

## Límites conocidos (2.4.0 a 2.6.0)

- Las tareas creadas directamente en la cuenta (fuera de Ágora) se ven en la ficha del contacto, pero no en el módulo Tareas.
- Una tarea necesita un contacto cuando la subcuenta está conectada, porque en la cuenta toda tarea pertenece a un contacto.
- El responsable de la tarea en Ágora no se envía a la cuenta como «asignado a».
- La hora de la tarea se envía con la zona horaria del navegador de quien la crea.
- Cuando la cuenta rechaza un contacto duplicado, esa subcuenta espera unos segundos antes de la siguiente consulta (la misma protección de cuota de siempre).
- «Nuevo contacto» pide nombre, apellido, teléfono y correo; etiquetas y campos personalizados se completan después en «Editar ficha».
