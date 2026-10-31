# Roadmap de Ágora Workspace

Estado actualizado: 31 de octubre de 2026. Versión publicada: 0.21.1.

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
- Añadir confirmación de lectura y estados de entrega donde la API lo permita.
- Mejorar tratamiento de emails, adjuntos, notas internas y mensajes no textuales.

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

- Monitorización, copias de seguridad y herramientas seguras para resolver envíos con resultado incierto.

## Android y notificaciones

- Capacitor para Android: proyecto en `mobile/`, envoltorio que carga la web desplegada, de modo que cada despliegue llega al teléfono sin publicar versión nueva (0.18.0).
- Avisos en Android e iPhone **sin tienda de aplicaciones**: Web Push propio, con la aplicación instalada desde el navegador (0.19.0). La activación fallaba en el servidor por una ruta de desarrollo; corregido y probado sobre el paquete desplegado en 0.20.1. Pendiente de prueba con teléfonos reales.
- Firebase Cloud Messaging para el APK: registro del dispositivo, cola de avisos y entrega por cron (0.18.0). Pendiente de prueba contra el proyecto real.
- El pase que descubre mensajes corre también desde el reloj, de modo que hay aviso con todas las aplicaciones cerradas (0.19.0).
- Contador en el icono: número en iPhone instalada y escritorio; punto del sistema en Android (0.21.0). Pendiente: agrupación de avisos por conversación.
- Diagnóstico de avisos en Conexiones, eslabón a eslabón, con aviso de prueba (0.21.0).
- Un solo cron revisa y entrega; el service worker nuevo ya no queda bloqueado tras iniciar sesión; un rechazo del servicio ya no borra el teléfono (0.21.1).
- Deep links a contacto y oportunidad (la conversación ya funciona).
- Cola offline para operaciones compatibles y reconciliación al recuperar conexión.

## Evolución

- Contactos y tablero de oportunidades: implementados en 0.11.0; contactos rediseñado con ficha editable en 0.16.0.
- Fuera de alcance por decisión de producto: calendario, agendar cita y workflows. Los workflows se gestionan directamente en la cuenta; las citas ya creadas se siguen viendo en la ficha del contacto.
- Pendiente como pantalla propia: tareas, que hoy sólo aparecen dentro de la conversación.
- Resúmenes y sugerencias con IA, intención, clasificación y scoring.
- Tablero: cifras de la cuenta en 0.16.0; rentabilidad del mes y desempeño del equipo con metas y semáforo en 0.20.0; lo invertido en Meta leído de la cuenta publicitaria conectada en 0.21.0. Pendientes: comparación entre meses y reportes exportables.
- Reportes, Ágora Digital ERP, cobranza, facturación y pagos.
- Varias subcuentas por empresa y posterior arquitectura SaaS multiempresa.
