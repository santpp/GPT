# Ágora Digital · Workspace comercial

**Actualización 0.4.1:** [ZIP sin configuración ni claves](releases/agora-update-0.4.1.zip) · [Pasos sin terminal](deploy/native/UPDATE-0.4.1.md).

Entrada directa sin depender del menú o de rewrites: `https://nueva.agoradigital.app/conexiones.html`. La URL `/version.json` identifica el paquete instalado.

Acceso directo a Conexiones: `https://nueva.agoradigital.app/conexiones`. Requiere sesión de administrador. Alternativa sin reglas rewrite: `https://nueva.agoradigital.app/#conexiones`.

Interfaz móvil sobre GoHighLevel con identidad del manual Ágora. HTML5, CSS modular, JavaScript, PHP 8.3 y MariaDB. **Demo visual y base técnica, no versión funcional completa.**

La integración usa el **Private Integration Token de cada subcuenta**, configurable por el administrador desde **Conexiones**. No requiere webhooks. Se pueden guardar, cifrar, sustituir y eliminar credenciales, y comprobar acceso a una subcuenta. La vista **Conversaciones** abre los datos reales al iniciar sesión como administrador: 20, 50 o 100 conversaciones recientes, búsqueda local, filtro de pendientes e historial paginado en bloques de 20 mensajes. Incluye respuestas de texto para los canales admitidos, sujetas a los permisos y restricciones del canal. La búsqueda global y la paginación de la lista siguen pendientes. Las consultas periódicas necesitan activar la subcuenta y configurar cron en CyberPanel. Adjuntos interactivos, plantillas, push y Android siguen pendientes. El envío requiere migración 004 desde Conexiones y validación real por canal; ver [contrato, límites y deduplicación](docs/message-sending.md).

- [Descargar ZIP para CyberPanel](releases/agora-cyberpanel-php83.zip) — abrir el archivo en GitHub y pulsar **Download raw**.
- [Arquitectura y alcance actual](docs/architecture.md)
- [API GHL revisada](docs/ghl-api-review.md)
- [Identidad del manual](docs/brand.md)
- [Despliegue actual: CyberPanel nativo, sin Docker](deploy/native/README.md)
- [Alternativa Docker anterior](deploy/README.md)

## Despliegue del cliente

El sitio ya está creado en CyberPanel con PHP 8.3. El usuario eligió instalación nativa, sin Docker. Usar la guía nativa y el ZIP separado en agora-private/public_html; el Docker de desarrollo sigue siendo una herramienta interna opcional. No configurar un proxy adicional ni instalar Docker en el VPS para esta vía.

## Desarrollo

Requisitos: Docker y Docker Compose. Cada tarea cloud ya está aislada: utilizar el checkout existente, sin crear worktrees.

```sh
cd /workspace/GPT
# Crear sólo si no existe; preservar siempre la configuración existente.
if [ ! -f .env ]; then cp .env.example .env; fi
chmod 755 . database
chmod 644 database/*.sql
docker compose up -d --build api
docker compose exec -T api php bin/migrate-integrations.php
docker compose exec -T api php bin/generate-key.php
```

`APP_KEY_FILE=/app/var/integration.key` es el valor de desarrollo en `.env.example`. En una instalación existente, configurarlo también en `.env` y recrear api. El generador no muestra la clave ni sobrescribe una existente. Respaldarla fuera de la base y no versionarla. El volumen de datos nunca se debe borrar para repetir una migración. Los esquemas se inicializan al crear el volumen; la migración adicional usa `IF NOT EXISTS` y es repetible.

El puerto de desarrollo es 8080, enlazado sólo a loopback. `/api/health` consulta la base. La demo visual no requiere credenciales GHL.

## Cuenta administradora y conexiones

No hay usuario predeterminado. Para crear uno, mantener `GHL_LOCATION_ID` como identificador inicial del tenant en `.env` (es una limitación del bootstrap actual, no el token de la integración), recrear api y ejecutar `docker compose exec -T api php bin/create-user.php usuario@empresa.test administrator`, suministrando la contraseña por stdin desde una fuente segura. No incluir contraseñas en argumentos o historial.

Iniciar sesión en la app, abrir **Conexiones**, añadir nombre, Location ID y Private Integration Token. La comprobación utiliza `locations.readonly`; otros scopes se añadirán según los módulos que se habiliten. El token sólo se envía una vez al backend desde el formulario administrador y no se devuelve después. Usar HTTPS en el despliegue real. No enviar tokens en chat ni introducirlos en una demo pública o un equipo ajeno.

Cada administrador accede sólo a las conexiones de su empresa. La selección de subcuenta y la separación de recursos por conexión en el inbox son el siguiente desarrollo; no se anuncia un SaaS multiempresa terminado.

## Pruebas

```sh
docker compose exec -T api php tests/webhook.php
docker compose exec -T api php tests/integration.php
docker compose exec -T api php tests/sync.php
docker compose exec -T api php tests/installation.php
# Con Playwright Python y Chromium disponibles:
python3 tests/ui.py
```

La prueba de integración usa api en localhost:8080 dentro del contenedor y el origen local por defecto. Crea usuarios y tenants ficticios y elimina sus propios datos. Prueba login, CSRF, aislamiento, permisos administrador/vendedor/supervisor, cifrado contextual y rotación de tokens. Incluye regresiones del receptor legado, que está deshabilitado por defecto. No ejecutar contra producción.

La UI permite filtros, búsqueda, vistas de ejemplo, conversación, ficha móvil y shell offline. `TEST_BASE_URL` y `CHROMIUM_PATH` permiten seleccionar servidor y navegador. No se ha realizado una comprobación GHL con un token real ni entrega FCM o prueba APK.

## Notificaciones sin webhooks

El PIT no transmite eventos por sí mismo. Para notificar con la app cerrada, el servidor tendrá que consultar GHL periódicamente, detectar diferencias y enviar FCM. Habrá retraso y consumo de API según el intervalo. La app móvil no debe ser responsable de mantener ese proceso en segundo plano. Ya existe `bin/sync-inbox.php` para importar la ventana reciente desde una tarea cron, con presupuesto compartido y manejo de 429; todavía no envía push ni importa todo el historial. Ver [detalles de sincronización](docs/api-sync.md).
