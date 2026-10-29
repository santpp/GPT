# Roadmap de Ágora Workspace

Estado actualizado: 29 de octubre de 2026.

## Completado y desplegable

- Base PWA móvil, PHP 8.3 y MariaDB; instalación nativa en CyberPanel.
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

- Ficha del contacto: teléfono, email, fuente, etiquetas, campos personalizados y ejecutivo.
- Oportunidades: pipeline, etapa, valor e historial.
- Tareas, notas y citas.
- Acciones rápidas: llamar, agendar, crear tarea/oportunidad, cambiar etapa, reasignar, etiquetar, workflow y nota.

## Operación del equipo

- Proyección de usuarios, roles, scopes y `assignedDataOnly` por cuenta: implementada.
- Acceso por correo sincronizado y código de un solo uso: implementado en 0.7.0; el cron renueva permisos cada hora y la baja sincronizada revoca la sesión.
- Permisos efectivos y filtro de conversaciones asignadas: implementados para la bandeja; se aplicarán del mismo modo a cada módulo nuevo.
- Asignación, filtros “mis conversaciones” y registro de acciones.
- Sincronización incremental de listas más allá de la ventana 20/50/100.
- Monitorización, copias de seguridad y herramientas seguras para resolver envíos con resultado incierto.

## Android y notificaciones

- Firebase Cloud Messaging, registro de dispositivos y contadores.
- Deep links a conversación, contacto, tarea, cita u oportunidad.
- Capacitor para Android; PWA y recursos web actualizables desde el servidor.
- Cola offline para operaciones compatibles y reconciliación al recuperar conexión.

## Evolución

- Pipeline Kanban, calendario, contactos y tareas completamente reales.
- Resúmenes y sugerencias con IA, intención, clasificación y scoring.
- Dashboards, reportes, Ágora Digital ERP, cobranza, facturación y pagos.
- Varias subcuentas por empresa y posterior arquitectura SaaS multiempresa.
