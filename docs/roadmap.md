# Roadmap de Ágora Workspace

Estado actualizado: 31 de octubre de 2026. Versión publicada: 1.0.2. Después de la v1.0 el usuario sólo quiere el comentario interno por contacto; el resto de ideas se descarta.

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

- Comentario interno por contacto: ya se pueden crear notas desde el chat; falta verlas y crearlas también desde la ficha de Contactos, destacadas como comentario del equipo.
- A estudiar, sólo si el usuario lo confirma: gestionar usuarios del equipo desde Ágora (crear, editar rol y permisos, quitar) con `POST/PUT/DELETE /users`, que exigen el permiso `users.write`, el `companyId` de la agencia y una prueba real.
- Descartado por el usuario: plantillas con imagen, estados de entrega y lectura, informes por mes, agrupar avisos, enlaces directos, uso sin conexión, pantalla de tareas, IA y ERP.
