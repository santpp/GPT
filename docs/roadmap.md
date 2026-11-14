- **Gasto en GHL por subcuenta (sólo propietario):** verificado en la documentación local (`apps/v3/saas-v3.json`): existe el saldo por subcuenta `GET /saas-api/public-api/companies/{companyId}/locations/{locationId}/wallet-balance` (devuelve `balance`, `complimentaryCredits`, `walletId`). NO aparece en la documentación local el listado de transacciones de billetera (`/saas/companies/{companyId}/wallet-transactions`) que se citó; falta confirmarlo con un token de agencia (PIT con `saas/company.read`). Sin ese listado sólo hay saldo, no gasto del mes. Pendiente: confirmar el endpoint de transacciones.
# Roadmap de Ágora Workspace

Estado actualizado: 10 de noviembre de 2026. **Versión activa: 1.10.14 (1.10.1 + Consumos + teléfono + Embudos y búsqueda + Entrar como + listas por vendedor + menú «Más»)**, con el cambio de fuente (texto negro y más grande) y el canal de origen a la vista. El usuario probó en el teléfono todo lo pendiente (comisión por cierre, asignación, novedades, avisos con subcuenta y destino, comentarios de Instagram/Facebook) y está bien. El usuario descartó el comentario interno por contacto y el resto de ideas de después de la v1.0.

El trabajo de la 1.11.0 y la 1.11.1 (rutas de servidor para el armazón nuevo de Agora Digital App: tareas, recordatorios, auditoría, identidad del espacio, resumen, subcuenta de demostración) **queda en pausa, sin subir**, hasta que el usuario retome ese desarrollo. El código y los paquetes siguen en el repositorio por si se retoma, pero no son la versión activa. El desarrollo sigue a partir de la 1.10.1.

## Completado y desplegable

- Base PWA móvil, PHP 8.3 y MariaDB; instalación nativa en CyberPanel.
- Entrada directa al espacio real: pantalla de acceso y, al entrar, datos de la cuenta. Sin vistas de ejemplo.
- Selector de subcuenta en la cabecera que manda en toda la aplicación.
- Pantallas reales de contactos y de oportunidades por embudo, con edición de la oportunidad desde el tablero.
- Tablero con las cifras de la cuenta: conversaciones, pendientes, contactos y oportunidades abiertas por etapa.
- Ficha de contacto completa y editable desde la pantalla de contactos, con todos los campos, los campos personalizados y las etiquetas.
- Login propio, roles base y administración de conexiones por subcuenta.
- Credenciales cifradas en el servidor; el navegador nunca recibe la clave privada.
- Bandeja real con 20/50/100 conversaciones, búsqueda local, pendientes y canales legibles.
- Historial paginado y respuestas de texto con protección contra duplicados.
- Chat activo con consulta cada 10 segundos, caché compartida, pausa al ocultar la app y respeto de cuotas.

## Hito actual: estabilización de conversaciones

- Validar 0.5.0 con conversaciones reales y confirmar que desaparece el fallo 502.
- Validar un envío real por cada canal habilitado y documentar sus restricciones.
- Plantillas de WhatsApp (0.24.0): número de WhatsApp por conexión, aviso de 24 horas, selector de plantillas aprobadas con vista previa, variables y envío con la forma que usa la cuenta. En 0.24.2: comprobación desde Conexiones (cuántas devuelve la cuenta y por qué se descarta cada una), WABA ID opcional para pedirlas y lectura más tolerante. Pendiente: que aparezcan en la cuenta real (la primera prueba no mostró ninguna), confirmar un envío real, plantillas con imagen y botones con enlace variable.
- Añadir confirmación de lectura y estados de entrega donde la API lo permita.
- Adjuntos con miniatura, visor, audio y vídeo en el chat; motivo de los envíos fallidos e iconos propios en la cabecera (0.22.0). Pendiente: notas internas.
- Avisos con el texto real del último mensaje entrante, o «Envió una foto / nota de voz / vídeo / documento» (0.22.1).

Sin webhooks, “tiempo real” significa consulta frecuente: el chat visible puede tardar alrededor de 10 segundos, más latencia o pausas de cuota. SSE permitirá distribuir un resultado ya descubierto entre navegadores, pero no hará que el backend descubra antes el mensaje. El cron mantiene la lista cuando la app está cerrada; FCM se incorporará más adelante para avisos en Android.

## Siguiente hito: contexto comercial dentro del chat

- Ficha del contacto: teléfono, email, empresa, fuente, etiquetas, campos personalizados, actividad y ejecutivo: implementada en 0.8.0.
- Oportunidades: oportunidad activa, pipeline, etapa, estado, valor y responsable dentro del chat, con acceso a todas las oportunidades del contacto: implementadas en 0.9.0, con nombres reales de pipeline y etapa, caché de 60 segundos por contacto y una hora para el directorio, y respeto de permisos y `assignedDataOnly`.
- Tareas, notas y citas dentro del chat, con crear nota, crear tarea y completar o reabrir tarea: implementadas en 0.10.0, con protección contra duplicados por clave de petición.
- Acciones rápidas: crear nota, crear tarea, completar tarea, etiquetar, y desde 0.17.0 crear oportunidad, editarla, moverla de etapa, cambiar su estado y reasignarla, tanto en la conversación como en el tablero.

## Operación del equipo

- Proyección de usuarios, roles, scopes y `assignedDataOnly` por cuenta: implementada.
- Acceso por correo sincronizado y código de un solo uso: implementado en 0.7.0; el cron renueva permisos cada hora y la baja sincronizada revoca la sesión.
- Entrega de los códigos a través del correo de una cuenta conectada elegida como canal de acceso: implementada en 0.8.1. Sustituye por completo al SMTP propio; Ágora ya no necesita servidor de correo ni credenciales de buzón. El propietario conserva su acceso con contraseña como recuperación.
- Permisos efectivos y filtro de conversaciones asignadas: implementados para la bandeja; se aplicarán del mismo modo a cada módulo nuevo.
- Asignación, filtros “mis conversaciones” y registro de acciones.
- Lista sin ventana: la bandeja continúa más allá de 20/50/100 con la posición que da la cuenta (0.18.0).
- Actualización en vivo sin webhooks: un pulso contra Ágora y un pase compartido por cuenta (0.18.0).

- Sesión persistente de 60 días por dispositivo (0.23.0): cookie protegida, secreto rotado cada día y guardado sólo como huella, cierre desde cualquier dispositivo, baja sincronizada que la anula.
- Entrar con huella, Face ID o PIN mediante llaves de acceso WebAuthn (0.23.0): ES256 y RS256, verificación del usuario obligatoria, sólo la parte pública en el servidor, comprobación de origen y dominio, contador contra copias. Oferta única tras entrar, botón en la pantalla de acceso, panel «Seguridad y sesión» con llaves y dispositivos (y los del equipo para el administrador), y bloqueo opcional al volver que aplica el servidor. Las contraseñas las guarda el gestor del teléfono, no Ágora.
- Pendiente de validar en teléfonos reales: iPhone instalada y Android con Chrome. En el APK, la huella depende del visor; la sesión de 60 días funciona igual.
- Monitorización, copias de seguridad y herramientas seguras para resolver envíos con resultado incierto.

## Android y notificaciones

- Capacitor para Android: proyecto en `mobile/`, envoltorio que carga la web desplegada, de modo que cada despliegue llega al teléfono sin publicar versión nueva (0.18.0).
- Avisos en Android e iPhone **sin tienda de aplicaciones**: Web Push propio, con la aplicación instalada desde el navegador (0.19.0). La activación fallaba en el servidor por una ruta de desarrollo; corregido y probado sobre el paquete desplegado en 0.20.1. Pendiente de prueba con teléfonos reales.
- Firebase Cloud Messaging para el APK: registro del dispositivo, cola de avisos y entrega por cron (0.18.0). Pendiente de prueba contra el proyecto real.
- El pase que descubre mensajes corre también desde el reloj, de modo que hay aviso con todas las aplicaciones cerradas (0.19.0).
- Contador en el icono: número en iPhone instalada y escritorio; punto del sistema en Android (0.21.0). Pendiente: agrupación de avisos por conversación.
- Diagnóstico de avisos en Conexiones, eslabón a eslabón, con aviso de prueba (0.21.0).
- Un solo cron revisa y entrega; el service worker nuevo ya no queda bloqueado tras iniciar sesión; un rechazo del servicio ya no borra el teléfono (0.21.1).
- Revisión desde fuera con una dirección secreta, para cuando el cron del servidor no corre (0.21.2).
- Avisos en 10–15 segundos con la app cerrada, con quién, canal y texto; aviso visible si el service worker se queda atrás (0.21.3).
- Texto real del mensaje en el aviso (0.22.1): confirmado en teléfono real. El título lleva quién y por qué canal y el cuerpo, lo que escribió.
- Cuota diaria propia corregida en 0.22.2: el tope interno pasa de 5.000 a 50.000 consultas al día por subcuenta. Con 5.000, la revisión cada 10 segundos lo agotaba entre las 9:00 y las 13:00 de Nueva York y Ágora no leía la cuenta hasta las 19:00 o 20:00. Límite oficial: 200.000 al día y 100 cada 10 segundos; se siguen obedeciendo las cabeceras de cuota.
- Deep links a contacto y oportunidad (la conversación ya funciona).
- Cola offline para operaciones compatibles y reconciliación al recuperar conexión.

## Limpieza de interfaz para el despliegue

- 0.22.2: marca de Ágora en la cabecera, iconos dibujados en el menú, bandeja más compacta con hora corta, sin estados de entrega en lo recibido, sin canal repetido en el cuadro de respuesta, buscador de contactos completo, sin avisos técnicos y errores del navegador explicados en castellano.
- 0.24.1: filtro de canales en menú desplegable, «Enviar mensaje» desde la ficha del contacto (abre o crea su conversación), logo en PNG transparente y token de seguridad al guardar la ficha desde Contactos.
- 0.27.0: icono propio para instalar, pestaña y avisos (con versión adaptable para Android) y más limpieza: pie mínimo, menú sin etiquetas sobrantes, tablero sin botones repetidos, bandeja sin contador duplicado ni columna vacía.
- Pendiente: revisión final del usuario en teléfono con datos reales, en claro y en oscuro.

## v1.0 (publicada)

- 1.0.0: «¿Venta ganada?» en el chat (cuenta en el mes en que se marca, asigna a quien la cierra si no tenía responsable), leads de hoy, leads asignados a mí y mis ventas del mes en el tablero, bienvenida para instalar la app y activar avisos, ficha agrupada al editar, icono monocromo correcto en la barra de estado y versión en el menú.
- 1.0.1: cabecera del chat discreta, recuadros personales siempre visibles con su motivo, ventas ganadas del mes de la cuenta, propietario reconocido por correo y leads de hoy con alternativas.
- 1.0.2: los avisos abren la app instalada y no una pestaña de Chrome (dirección completa, ventana reutilizada y `launch_handler` en el manifiesto). Pendiente de confirmar en el Pixel tras reinstalar.

## Antes era «Para la v1.0»

- Confirmar el envío real de una plantilla de WhatsApp.
- Dirección de revisión protegida (0.25.0): contraseña antes de crear la clave, clave en cabecera `X-Agora-Key` y último uso visible.
- Validación real por canal y de la huella en iPhone y Android.
- Copias de seguridad: fuera del plan por decisión del usuario (los datos viven en la cuenta conectada). La herramienta de 0.25.0 queda disponible pero inactiva si no se configura.
- Migraciones automáticas: se mantienen como están, por decisión del usuario.
- Modo oscuro (0.26.0): automático según el dispositivo, o fijo en claro u oscuro desde Mi cuenta, sin parpadeo al abrir.

## Evolución

- Contactos y tablero de oportunidades: implementados en 0.11.0; contactos rediseñado con ficha editable en 0.16.0.
- Fuera de alcance por decisión de producto: calendario, agendar cita y workflows. Los workflows se gestionan directamente en la cuenta; las citas ya creadas se siguen viendo en la ficha del contacto.
- Pendiente como pantalla propia: tareas, que hoy sólo aparecen dentro de la conversación.
- Resúmenes y sugerencias con IA, intención, clasificación y scoring.
- Tablero: cifras de la cuenta en 0.16.0; rentabilidad del mes y desempeño del equipo con metas y semáforo en 0.20.0; lo invertido en Meta leído de la cuenta publicitaria conectada en 0.21.0. Pendientes: comparación entre meses y reportes exportables.
- Reportes, Ágora Digital ERP, cobranza, facturación y pagos.
- Varias subcuentas por empresa y posterior arquitectura SaaS multiempresa.

## Plan después de la v1.0 (decisión del usuario)

- A estudiar, sólo si el usuario lo confirma: gestionar usuarios del equipo desde Ágora (crear, editar rol y permisos, quitar) con `POST/PUT/DELETE /users`, que exigen el permiso `users.write`, el `companyId` de la agencia y una prueba real.
- Descartado por el usuario: plantillas con imagen, estados de entrega y lectura, informes por mes, agrupar avisos, enlaces directos, uso sin conexión, pantalla de tareas, IA y ERP.

## Hecho en 1.1.0

1. **Tablero sin administradores entre los vendedores** (de subcuenta o de agencia); lo que cierren sigue contando en el total.
2. **Comisión por subcuenta, sólo desde Conexiones y sólo por el administrador:** porcentaje de la venta o monto fijo por cierre, válida desde el mes en que se cambia, con historial. Sale de «Cifras del mes».
3. **«¿De dónde salen estas cifras?»** en la rentabilidad del mes.
4. **Cada aviso lleva a su conversación,** cambiando antes a su subcuenta; si ya no está disponible, lo dice.
5. **Novedades escritas por el administrador desde Conexiones:** una ventana que cada persona ve una sola vez al entrar, con vista previa, botón opcional, caducidad y recuento de quién la ha visto.

## Pendiente
- Que pruebes en el teléfono: la comisión por cierre en la otra subcuenta, que el aviso abra la conversación y una novedad de prueba.
- Plantillas de WhatsApp, en pausa hasta tener el WABA ID.
- **Hecho en 1.2.0:** asignar la conversación (responsable del contacto y de sus oportunidades abiertas), sólo para administradores de la subcuenta y el propietario; el responsable del contacto y de la oportunidad sólo lo cambian ellos.
- **Descartado por el usuario:** gestionar usuarios desde Ágora (y con ello el permiso `users.write`), y etiquetar desde el chat.
- **Descartado por el usuario:** respuestas rápidas y «+ Nuevo contacto».

## 1.2.1
- El aviso dice de qué subcuenta es cuando hay más de una; abre la conversación aunque sea de otra subcuenta o no traiga la suya, y no pierde la apertura si la app se recarga. Pendiente de confirmar en el teléfono.

## 1.3.0 (retirada en 1.4.0)
- Se probó citar un mensaje con `replyMessageId`. La cuenta aceptaba el envío, pero WhatsApp no mostraba la cita al cliente (sólo se veía dentro de Ágora). Se quitó en la 1.4.0 mientras se busca otra forma.

## 1.4.0
- Se quita la cita de mensajes (ver arriba).
- Conversaciones que empiezan con un comentario en una publicación de Instagram o Facebook: aviso arriba («Su primera interacción fue un comentario…»), etiqueta en cada comentario y respuesta como mensaje directo. Probado por el usuario: funciona.
- Si la cuenta rechaza un envío, el mensaje de error incluye el motivo que da (sin marcas), para diagnosticar. 
- **Notas de voz: descartadas definitivamente.** La subida (de dos pasos y clásica) funcionaba y el archivo se servía como `audio/ogg`, pero la cuenta rechazaba siempre el envío por WhatsApp (HTTP 400). Se retiró todo el código de la 1.4.0. Si se retoma, hay que abrir un caso con el soporte de la cuenta.

## 1.5.0 · pulido de la interfaz
- Pila técnica sin cambios: HTML, CSS y JavaScript sin framework en el navegador, PHP 8.3 y MariaDB en el servidor.
- Esqueletos de carga con brillo suave en lugar de textos «Cargando…» (conversaciones, mensajes, tablero, contactos, oportunidades, ficha y seguridad), con entrada escalonada.
- El tablero dice «actualizado a las HH:MM» con un punto verde, en vez de «leídas a las…».
- Menos aspecto de plantilla: títulos de sección en minúsculas y en negrita (sin mayúsculas separadas), cifras del tablero en una franja con separadores finos en vez de cajas, y fuera los textos de relleno bajo los números. El punto verde del tablero se quitó. Las tarjetas con borde se dejan como están por decisión del usuario, a ver cómo queda.
- Animaciones más pausadas (0,5 a 0,7 s, con escalonado) para dar sensación de web; las burbujas y la lista de conversaciones sólo se animan la primera vez, no en cada refresco.
- En el móvil, la conversación y la ficha del contacto entran deslizando desde la derecha y la lista vuelve desde la izquierda; los mensajes nuevos y el que se envía se desplazan suavemente al final (el primer abrir va directo al último mensaje).
- Ficha del contacto: los campos personalizados se agrupan por las carpetas de la cuenta (en la ficha y en el editor), en el mismo orden; los que no tienen carpeta van al final en «Otros campos». Las carpetas se leen de `GET /custom-fields/object-key/contact`, que la referencia sólo documenta para objetos personalizados y empresas: si la cuenta no responde, los campos se muestran sin agrupar y el editor lo dice. Pendiente de probar en la cuenta real.
- Etiquetas: al pulsar «Añadir» se ofrecen las etiquetas que ya existen en la cuenta (`GET /locations/{id}/tags`), con filtro al escribir; se sigue pudiendo crear una nueva.
- Transiciones al aparecer listas y mensajes, y respuesta al pulsar botones. Todo se desactiva con «reducir movimiento» del dispositivo.

## 1.5.1 · edición de la ficha
- Carpetas de campos plegables (la primera abierta, con «n de m» rellenados), buscador de campos cuando hay más de 8, campos modificados marcados, contador en «Guardar ficha (n)», aviso de dos toques al descartar con cambios sin guardar, y espacio para que la barra de guardar no tape el último campo.

## 1.5.2 y 1.5.3 · carpetas de campos
- Diagnóstico en la cuenta real: los 22 campos traen su carpeta (`parentId`), pero `GET /custom-fields/object-key/contact` responde 400 («Api does not support objectKey of type contact or opportunity»). Se deja de llamarlo (cada 400 pausaba la cuenta unos segundos).
- 1.5.3: los campos se agrupan por `parentId`. El nombre se pide una vez por carpeta con `GET /custom-fields/{id}` (la referencia lo describe como «campo o carpeta por id», pero sólo para objetos personalizados y empresas); si la cuenta no lo da, no se reintenta hasta 24 h después. Mientras tanto las carpetas se llaman «Carpeta 1, 2…» en el orden de los campos, y el administrador las nombra en Conexiones → «Carpetas de campos», reconociéndolas por campos de muestra. Los nombres puestos a mano mandan siempre.

## 1.5.4 · entrada de los usuarios de subcuenta
- El selector de subcuenta sólo aparece cuando hay más de una; con una se muestra su nombre y la persona ya está dentro. (Antes quedaba un selector vacío visible porque un estilo anulaba el atributo «oculto».)
- Si a un usuario de subcuenta no le queda ninguna, se le explica por qué (sin subcuenta asociada, o sin permiso de conversaciones en ella) en lugar de mandarle a «Conexiones», que sólo ve quien administra Ágora.
- El código de acceso por correo sale desde la subcuenta a la que pertenece la persona (integrante activo de una subcuenta comprobada); la cuenta elegida como canal de acceso queda de respaldo.
- Pendiente de verificar con el usuario afectado: su caso mostraba la lista de subcuentas vacía; el mensaje nuevo dirá la causa.

## 1.5.5 · administradores de subcuenta
- Un administrador de la subcuenta entraba sin acceso a nada («no tiene activadas las conversaciones») porque la cuenta no lista los permisos de un administrador y Ágora exigía verlos activados. Ahora quien tiene rol `admin` (o `agency`) en la subcuenta tiene todo, salvo lo que se le desactive expresamente, sin depender de la lista de alcances, y sin restricción a lo asignado a él. Nunca configura conexiones (eso es del propietario de Ágora).

## 1.5.6 · avisos de otra persona
- Error de privacidad: la suscripción de avisos es del navegador, no de la persona. Si el propietario había activado avisos en un navegador y otra persona entraba allí (con la sesión del propietario caducada, sin «cerrar sesión»), el navegador seguía registrado a nombre del propietario y mostraba a la nueva persona los avisos de todas las subcuentas. Ahora, al entrar, Ágora registra a nombre de quien entra la suscripción que el navegador ya tenga (sin preguntar nada), y los avisos pasan a filtrarse por sus subcuentas. El servidor ya filtraba por destinatario; el fallo era de a quién pertenecía el navegador.

## 1.5.7 · avisos sólo de lo asignado
- Los avisos (y el número del icono) llegan a cada persona sólo por las conversaciones que tiene asignadas, dentro de su subcuenta. Reciben los de toda su subcuenta únicamente los administradores de esa subcuenta (rol `admin` o `agency`) y el propietario de Ágora. Antes, quien no tenía marcada la restricción «sólo lo asignado» recibía todas.

## 1.5.8 · responder como DM
- Cada comentario de Instagram o Facebook trae un botón «Responder como DM»: elige el mensaje directo de esa red, lleva al cuadro de respuesta y avisa de que se responde por mensaje directo, no en el comentario. Responder en público al comentario no se ofrece: la API publicada no lo documenta.

## 1.6.0 · periodo del tablero
- Selector de periodo en el tablero: Hoy, Ayer, 7 días, 30 días, Este mes, Mes anterior, Otro mes y Rango de fechas. Cambia los **leads nuevos** (contactos creados en ese periodo, con los días de la zona del dispositivo) y el **mes** del resumen de rentabilidad y ventas, que sigue siendo de un mes natural: con un periodo corto usa el mes en curso y con un rango usa el mes en que termina. Conversaciones, sin leer, oportunidades y «asignados a mí» son el estado actual, no dependen del periodo. La elección se recuerda en el dispositivo. El selector de mes del propio resumen sigue ahí y mueve el mismo control.

## 1.6.1 · selector de periodo rediseñado
- La fila de chips pasa a un solo botón («Hoy · 1 nov») que abre una lista con los periodos rápidos, «Otro mes» y un rango de fechas con «Aplicar rango»; en el móvil ocupa el ancho y la lista se abre debajo. Se cierra al elegir, al pulsar fuera o con Escape.

## 1.7.0 · identidad visual
- **Tipografía Author** (Fontshare, licencia libre) alojada en el propio servidor: `@font-face` con `Author-Variable.woff2` en `/assets/fonts/`. Los archivos no vienen en el paquete (esta sesión no puede descargarlos de Fontshare): se copian una vez a esa carpeta y se recarga; sin ellos se usa la fuente del sistema. Ajustes de lectura: kerning, suavizado, cifras tabulares y tracking en títulos.
- **Color sólo donde importa:** burbujas sin rosa (entrante blanca con borde, saliente gris azulado); etiquetas neutras; el rosa queda para lo seleccionado y la acción principal.
- **Radios unificados:** 6, 10 y 14 px (más círculo y píldora), en lugar de siete valores distintos.
- **Iconos** con trazo más fino y puntas rectas, a juego con la tipografía.
- **Avisos** como una línea de texto con filo fino, no un bloque amarillo (24 horas de WhatsApp, origen por comentario, advertencias del tablero).
- **Movimiento con motivo:** los datos del tablero aparecen quietos; se animan las listas, los mensajes nuevos, los paneles y los diálogos.
- **Selector de periodo:** o un botón rápido o un mes/rango; al tocar uno se apaga el otro.
- Se dejan como están el selector de canal y las tarjetas con borde, por decisión del usuario.

## 1.7.1 · pulidos del tablero y de los avisos
- Mes en español («Noviembre 2026») con un selector que no se corta, tanto en el resumen como en «Otro mes».
- Botón de avisos con campana en lugar de punto: activa, desactivada (con campana tachada) o bloqueada. Si los avisos no están activos aparece una línea que lo dice, con botón «Activar avisos» y «Ahora no» (una vez al día como mucho); si el navegador los tiene bloqueados explica cómo permitirlos.
- Recuperación automática: al entrar y al volver a la pestaña, si el permiso ya está dado pero falta la suscripción, o el servidor ya no la tiene a nombre de quien está dentro, Ágora la rehace sola (`GET /api/devices?token=` indica si el servidor la tiene).
- Cifras con punto de miles («1.520»).
- Moneda por subcuenta, sólo euros o dólares (por defecto dólares), que fija el propietario en Conexiones → Comisión y moneda; todos los importes la usan (migración 027).
- «Elegir quién soy» para el propietario: en «Leads asignados a mí» y «Mis ventas del mes» elige su usuario entre los de la subcuenta (se guarda sólo para él, por subcuenta). Los administradores de subcuenta ya se reconocen por su correo.
- Tablero con filas alineadas.

## 1.8.0 · sin respuesta y avisos de asignación y de recordatorio
- **Sin respuesta:** pestaña en la bandeja (conversaciones cuyo último mensaje es del contacto, las que más llevan esperando primero, con «Espera 3 h», en color si pasa de una hora) y tarjeta «Esperando respuesta» en el tablero con acceso a esa lista (una consulta más a la cuenta por carga del tablero).
- **«Te asignaron una conversación»:** aviso push sólo a la persona asignada, tanto si el cambio se hace en Ágora como si llega desde la cuenta. No avisa al propietario ni a otros.
- **Recordatorio de mensaje sin leer:** pasados N minutos (10 por defecto) de un mensaje entrante sin leer, un aviso a quien tiene asignada la conversación (o a los administradores de la subcuenta y al propietario si no tiene a nadie). Una vez por mensaje y sólo si es de las últimas 24 h. Los minutos (0 = desactivado) los fija el propietario por subcuenta en Conexiones → «Comisión, moneda y avisos» (migración 028).

## 1.8.1 · redes por las que escribe el contacto
- Error: de un contacto que había escrito por Instagram (o comentado) y al que luego se le escribió por WhatsApp, Ágora sólo ofrecía WhatsApp. La cuenta guarda una conversación por contacto y su «último canal» cambia con cada mensaje; Ágora ofrecía Instagram o Messenger únicamente si el último mensaje era de esa red. Ahora recuerda cada red desde la que el contacto escribió (en el historial y en la lista) y la sigue ofreciendo; sólo se añaden, nunca se quitan (migración 029). El historial devuelve los canales posibles, así que el selector se actualiza al abrir la conversación. Meta sólo permite escribir por Instagram o Messenger dentro de su ventana de respuesta (unas 24 horas tras el último mensaje del contacto; algo más tras un comentario): fuera de ella la cuenta puede rechazar el envío y Ágora muestra su motivo.

## 1.8.2 · ventas ganadas y alerta de ventas sin monto
- **Oportunidades en dos pestañas:** Embudos (lo de siempre) y **Ganadas**: ventas ganadas del mes elegido (mes en español), número, monto vendido y cuántas están sin monto; las sin monto primero, con un campo para escribirlo ahí mismo (escribe el monto en la oportunidad de la cuenta). Cada vendedor ve las suyas; los administradores de la subcuenta y el propietario ven todas, con resumen y filtro por vendedor.
- **Alerta de ventas ganadas sin monto** (monto vacío o cero), sólo del **mes en curso**: se consulta a la cuenta **4 segundos después de entrar** y **cada vez que se edita una oportunidad** (nunca por sondeo; el servidor guarda la lectura 2 minutos y la borra al editar). Al entrar: ventana con las ventas y un campo por cada una; después, franja roja fija y contador rojo en la pestaña Oportunidades. **Escalada:** si una lleva los días fijados (7 por defecto, 0 = nunca) la ventana no se puede cerrar (ni con Escape) hasta completar; si no se puede guardar por permisos, no atrapa a nadie. La reciben el vendedor con ventas suyas sin monto; los administradores, las sin responsable más un resumen del equipo en el tablero («Ventas ganadas sin monto», por vendedor).
- Los días se fijan por subcuenta, sólo el propietario, en Conexiones → «Comisión, moneda y avisos» (migración 030).

## 1.8.3 · alerta rediseñada y fecha de las ventas
- **Alerta rediseñada:** hoja con una franja roja arriba (en el móvil sube desde abajo), etiqueta «Pendiente» o «Urgente», barra de progreso («0 de 2 completadas»), una tarjeta por venta con los días que lleva (en rojo si cumple la escalada), campo de monto con el símbolo de la moneda ($ o €) pegado al botón Guardar, Enter para guardar, el cursor ya en el primer monto, «Guardar N ventas» cuando hay varias rellenas y animación al completarse cada una.
- **Fecha de las ventas ganadas:** la búsqueda de oportunidades de la cuenta acepta `date` y `endDate` («start date», «end date») sin decir si filtran por la fecha de creación o por la del cambio de estado; cada oportunidad sí trae `lastStatusChangeAt` (el «status changed on»). Ahora se pide desde 90 días antes del mes y se descartan las que no cambiaron a ganada dentro del mes según `lastStatusChangeAt`. Así una venta creada antes y ganada este mes cuenta este mes, en la lista, la alerta y también en el resumen del mes del tablero (ventas, comisiones, ganancia), sea cual sea la fecha a la que filtre la cuenta. Si el mes trae más ventas de las que se pueden leer de una vez, se avisa.

## 1.8.4
- Alerta de ventas sin monto: se quita la etiqueta «Pendiente»/«Urgente» y la «píldora» de los días; los días son texto (en rojo si cumplen la escalada). La urgencia se nota por el fondo rosado de la cabecera, el título y la franja roja fija.

## 1.8.5 · Ganadas y resumen del equipo con la estética de la app
- **Ganadas:** mes y vendedor en dos columnas sin desbordar; cifras con una línea separadora; cada venta sin la barra roja gruesa: nombre, «Sin monto» en rojo con el vendedor y la fecha, y el campo de monto con símbolo de moneda y Guardar integrado (igual que en la alerta); no se repite el contacto cuando se llama igual que la oportunidad; el aviso de que hay muchas ventas (se revisan las 500 más recientes) pasa a una línea breve y natural: «Hay muchas ventas en estos meses, así que puede faltar alguna de las más antiguas.»
- **Tablero, resumen del equipo:** deja de ser una tarjeta más y pasa a una sección propia («Ventas ganadas sin monto»): el total, una lista de vendedores con su cuenta (una por línea) y «Ver y completar en Ganadas».

## 1.8.6 · usuario nuevo sin acceso
- Error: un usuario recién creado en la cuenta entraba y veía «Tu usuario no tiene activadas las conversaciones en su subcuenta». Causa: la cuenta sólo lista en los permisos de un usuario los que se tocaron; Ágora trataba un permiso no listado como desactivado, pero la referencia de la cuenta publica que conversaciones, contactos, oportunidades y citas están activados por defecto. Ahora un permiso no listado vale su valor por defecto (activado) y uno desactivado explícitamente sigue desactivado.
- Para el propietario: si un usuario sigue sin acceso, Conexiones → «Equipo y permisos» muestra qué módulos tiene activados; si «conversaciones» sale desactivado, es que la cuenta lo desactivó para ese usuario.

## 1.8.7
- El selector del mes del resumen (y los de Ganadas y «Otro mes») tienen el estilo de la app: borde fino, esquinas redondeadas, flecha propia y foco con el color de marca; ya no se ven como el campo nativo.
- Se quita el filo lateral de los avisos (24 horas de WhatsApp, comentario de origen, advertencias del tablero, «Activar avisos» y la nota de Ganadas); quedan como texto limpio. En oscuro, las cifras del tablero también van sin cajas.

## 1.8.8 · cada vendedor ve lo suyo y selector de canal desplegable
- **Privacidad:** los vendedores veían todas las conversaciones en «Todas» (sólo «Mías» las filtraba). La restricción a lo asignado dependía de una marca de la cuenta que la mayoría de usuarios no tiene. Ahora **todo usuario que no administra la subcuenta ve y trabaja sólo lo que tiene asignado** (conversaciones, contactos, oportunidades y cifras del tablero), tenga o no esa marca; los administradores de la subcuenta y el propietario ven todo. La bandeja pide a la cuenta sólo lo suyo (las páginas llegan completas) y la pestaña «Mías», redundante para ellos, se oculta. Consecuencia: lo que entra sin asignar sólo lo ve quien administra hasta que se asigna.
- **Selector de canal desplegable:** en lugar de dos filas de botones, un único botón con el canal elegido que abre una lista (con el motivo de los no disponibles); se cierra al elegir, al pulsar fuera o con Escape. Ocupa una línea.

## Siguiente (a decidir)
- **Siguiente, ya acordado con el usuario (por orden):**
  - 1.9: arreglar la prueba `announce-ui` intermitente y una **revisión de seguridad** completa (permisos por rol, límites de intentos, sesiones y avisos).
  - 1.10: **pantalla de mantenimiento** para cuando el servidor no responda o el propietario lo ponga en pausa.
  - **V2.1:** resumen diario o semanal (leads, ventas ganadas, conversaciones sin responder).
  - Descartado por el usuario: búsqueda global, vista de conversación con ficha fija en escritorio, avisos configurables por canal.
- Plantillas de WhatsApp: en espera del WABA ID o de la salida de «Comprobar plantillas».
- Motivos de pérdida de oportunidades: bloqueado, la cuenta no devuelve ninguno.

## 1.9.0 · manual, tutorial y logo de ayuda
- Manual de uso para vendedores (`/ayuda` y PDF descargable con capturas de datos inventados), solo vendedores, sin nombres de proveedor.
- Tutorial de primera vez (omitible, una vez por dispositivo, repetible desde el logo); el logo abre el menú de ayuda.
- Cola de ventanas al entrar: tutorial → clave de acceso → novedades → alerta de ventas (la urgente, justo tras el tutorial).
- El canal elegido recupera su color; generador `tools/make-manual.py`.
- Siguiente: 1.10 (prueba `announce-ui` inestable y revisión de seguridad completa), 1.11 (pantalla de mantenimiento), V2.1 (resumen diario/semanal).

## 1.10.0 · mantenimiento, texto grande y revisión
- Pantalla de mantenimiento (servidor caído o pausa del propietario) con el texto acordado; interruptor del propietario en «Mi cuenta».
- Modo «Texto grande» (letra +15 % y negrita) por dispositivo, en «Mi cuenta»; explicado en el manual y el tutorial.
- Encabezado alineado; `/ayuda` en las reglas del servidor real; `native-delivery` usa el paquete más reciente.
- Revisión de seguridad dirigida (SQL, HTML, cookies, CSRF, secretos). Pendiente: cabeceras `X-Frame-Options`/CSP en el servidor nativo.
- Descartado: despliegue por etiqueta. Siguiente: V2.1.

## V2.1 · planificada
- Resumen diario o semanal.
- Cabeceras de seguridad del servidor nativo (decidido el 12 de noviembre de 2026): `X-Frame-Options`/`frame-ancestors`, `Strict-Transport-Security` (empezando con duración corta), `Referrer-Policy`, `Permissions-Policy` y `Content-Security-Policy` estricta (sólo el propio dominio; estilos en línea permitidos; imágenes y audio de adjuntos por `https:`), primero en modo informe. Enviadas desde PHP para no depender del panel. Ágora no usa scripts en línea ni de terceros.

## 1.10.1 · legibilidad y canal de origen
- Letra +20 % y negra en toda la app (nada bajo 11,5 px); mensajes y cuadro de respuesta a 17 px; «Texto grande» a 20 px.
- Canal de origen (WhatsApp, Instagram, Facebook, SMS, correo) con marca y color en la lista y en el chat.
- El chat abre siempre en el último mensaje aunque el cuadro de respuesta cambie de alto.

## 1.11.0 y 1.11.1 · rutas del servidor para el armazón nuevo — sin publicar (ligadas al armazón)
- Hechas pero no desplegadas: tareas, recordatorios de seguimiento, auditoría (sólo se agrega, con borrado de lo de seis meses o más y acceso sólo para la propietaria), identidad del espacio, resumen, operación del día, eficacia de ventas, anuncios, leads por persona, subcuenta de demostración con datos ficticios (`DemoSpace`). Ver `docs/rutas-pantallas-nuevas.md`.
- Dependen del armazón de Agora Digital App (abajo): no se publican hasta que se retome.
- Pendiente si se retoma: conectar los cobros, planes y egresos al Resumen; las pantallas de Contratos (otro servidor).

## Agora New App (ERP) — activo desde el 13 de noviembre de 2026
- El repositorio se divide: `Agora Mobile/` (release 1.10.14 de nueva.agoradigital.app; el código sigue en la raíz) y `Agora New App/` (ERP 2.1.19 recibido: `public_html/`, `servidor/`). Análisis completo: `Agora New App/ANALISIS.md`. Sin Docker; MySQL.
- Se deja de llamar «armazón». Lo de abajo es el historial de cuando lo era.
- **2.2.0 (13 de noviembre)**: estable en MySQL; conexión con la cuenta por subcuenta (token cifrado, Location ID, «Probar conexión»); fuente Author; arreglos de Ajustes, Contactos, Gestión Comercial y logos; paquete de producción sin modo pruebas (`agora-new-app-2.2.0.zip`) y paquete de demo aparte. Siguiente: Conversaciones con el motor de Ágora Mobile.
- **2.3.2 (14 de noviembre)**: «Ir a subcuenta» funciona en una subcuenta recién creada.
- **2.3.1 (14 de noviembre)**: conexión de la agencia (Company ID y token de agencia) visible en Subcuentas con «Guardar y probar»; ventana de subcuenta reordenada; los estilos ya no se quedan de una versión anterior; lista de errores en `Agora New App/ERRORES.md`.
- **2.3.0 (14 de noviembre)**: bandeja, contactos, oportunidades, ganadas y tablero en vivo con el motor de Ágora Mobile 1.10.14; Consumos con token de agencia; dueños solo por terminal; guía de instalación paso a paso. Siguiente: probar con token real; interfaz de la bandeja al día con Ágora Mobile; avisos al teléfono; anuncios y redes.

## (Historial) Armazón nuevo (Agora Digital App)
- Paquete de pantallas de terceros que reemplaza el armazón visual (Resumen, ARP Comercial, Tareas, Auditoría, Athena…). Revisado y corregido en 1.1.1/1.2.x, sin publicar.
- La versión activa sigue siendo la 1.10.1. Sólo se retoma cuando el usuario lo pida explícitamente.

## Pendientes activos
- **Gasto en GHL por subcuenta (sólo administrador/propietario):** pendiente de confirmar. La documentación local sólo tiene el wallet de cargos de la propia app (`GET /marketplace/billing/charges`), no el gasto general de la subcuenta. Falta la fuente exacta que indica que la API de agencia lo da; si se confirma el endpoint, se diseña la pestaña. Descartada la aproximación por conteo de mensajes.
- Plantillas de WhatsApp: falta el ID de la cuenta de WhatsApp Business.
- V2.1: resumen diario o semanal y cabeceras de seguridad del servidor nativo.

## 1.10.2 · Consumos
- Pestaña «Consumos» sólo para la propietaria: token de agencia y Company ID escritos en la pestaña (cifrado, nunca se muestra completo), gasto y recargas del mes por subcuenta, plan activo, saldo, desglose, y botones de prueba y depuración (transacciones por POST y por GET, saldo y plan).
- El código de la 1.11.x (armazón nuevo) quedó fuera del árbol: sigue en el historial del repositorio (último commit: `b98d8ae`, 1.11.1; el de las notas y paquetes, `19aa91d`).

## 1.10.3 · Consumos sin depuración, con reporte y exportación
- Se quita la depuración (GET y saldo daban 404; POST v3 es el que contesta). La cuenta responde 201 a varias consultas: cualquier 2xx cuenta como éxito.
- Hoja de reporte por subcuenta y exportación a .txt o a PDF (por impresión), sólo para la propietaria.

## 1.10.4 · Consumos: movimientos de la agencia
- Bloque «Agencia · movimientos propios», bloques para las subcuentas no conectadas, y selector «Sólo la agencia».

## 1.10.14 · responsable en la bandeja
Quien ve toda la subcuenta ve el responsable de cada conversación («Sin asignar» en rojo).

## 1.10.13 · «asignado a ti» en todas partes
Se unifica el texto: «asignadas a tu usuario» pasa a «asignadas a ti».

## 1.10.12 · sistema de diseño completo
Paleta de 18 colores con nombre, escala de letra y tres sombras, vigilados por `tests/design-tokens.py`; fuera «Tus cifras» del tablero; `docs/DESIGN.md` ampliado.

## Decisiones del 12 de noviembre de 2026
- **Filtro por responsable confirmado** con la cuenta real (1.10.8): las listas de cada vendedor llegan ya filtradas.
- **Cabeceras de seguridad** → V2.1.
- **Sistema de diseño completo**: aprobado y aplicado en 1.10.12.
- Nueva guía de diseño para personas e IA: `docs/DESIGN.md`.

## 1.10.11 · diseño ordenado, instalar la app y manual al día
Valores de diseño con nombre y toques de 44 px; franja para instalar la app en cada visita (teléfono sin instalarla); manual de vendedores y PDF actualizados.

**Propuesto:** seguir llevando el resto de colores, sombras y tamaños de letra a valores con nombre (hoy 155 colores distintos).

## 1.10.10 · animaciones al abrir
La ficha de contacto entra deslizándose (misma animación que la bandeja) y «Más» sube desde abajo y baja al cerrar. Sin movimiento si el teléfono lo pide.

## 1.10.9 · Embudos con desplegables y menú «Más»
Embudo, Responsable y Etapa como desplegables iguales; fuera «Sin monto» de Embudos; en el teléfono la barra de abajo no se desliza (cuatro pestañas y «Más» sólo para la propietaria).

## 1.10.8 · listas de cada vendedor completas
Contactos y oportunidades de un vendedor se piden a la cuenta ya filtrados por su usuario, con total real y «Ver más». Antes salían sólo los suyos entre los más recientes de toda la subcuenta.

## 1.10.7 · Entrar como y «Mis leads» por periodo
La propietaria puede ver Ágora como cualquier usuario de una subcuenta, sólo para mirar y con registro. «Mis leads» cuenta el periodo elegido, no el total. Un vendedor ya no provoca que se cree una conversación de un contacto ajeno. Revisión de acceso: todas las pantallas filtran por lo asignado a quien no administra la subcuenta.

**Pendiente de decidir (revisión de seguridad del 8 de noviembre):** cabeceras de seguridad en el servidor nativo, límite de intentos de contraseña por correo, Capacitor 6.2.3 en la app Android.

**Futuro lejano, sin versión:** módulo de inventario para panaderías.

## 1.10.6 · Embudos nuevos y búsqueda
Chips de etapa, etapas plegables, tarjetas compactas con atajos (conversación, ficha, «Mover a…»), filtros de responsable y «sin monto», y búsqueda por nombre, teléfono y correo en bandeja, contactos y oportunidades. Descartado: tiempo en etapa y aviso discreto.

## 1.10.5 · arreglos de ancho en el teléfono
- Filtros de la bandeja, barra superior, menú inferior (desplazable) y selector de embudo ajustados al ancho; prueba que lo mide a 360–412 px. Descartado: aviso propio de consumo alto.
