# Actualización Ágora 0.3.0 · conversaciones reales e historial

1. Descargar `agora-update-0.3.0.zip` desde GitHub.
2. En CyberPanel, subir y extraer en la carpeta del sitio que contiene `public_html` y `agora-private`. Reemplazar archivos de código; no extraer dentro de public_html.
3. Abrir `/conexiones.html` para cargar la nueva entrada con recursos versionados. Con sesión de administrador, pulsar **Conversaciones**: ahora abre la bandeja real. `/bandeja` sigue funcionando.
4. Buscar dentro de las conversaciones cargadas, filtrar pendientes y abrir un chat. **Cargar mensajes anteriores** consulta otras páginas de 20 mensajes. Una consulta pausada permite reintentar manualmente sin perder el historial ya mostrado.
5. Comprobar `/version.json`: debe indicar `0.3.0`.

No requiere nuevas tablas, cambios de token ni comandos de terminal. El ZIP omite configuración, claves y carpetas privadas de ejecución: conserva el propietario corregido de integration.key.

La lista sigue limitada a las 20 conversaciones recientes. La búsqueda es local sobre esa lista; no busca todavía entre todas las conversaciones de la cuenta. El historial se solicita bajo demanda, no se importa íntegramente. Abrir un chat no lo marca como leído. No se habilitan envíos, push ni acceso de vendedores en esta entrega. Otros módulos siguen siendo demostraciones identificadas como tales.

Validación local: PHP/MariaDB, paginación con respuestas simuladas, Chromium móvil/escritorio y paquete con recursos antiguos en caché. La comprobación contra la cuenta real corresponde al despliegue en el VPS.
