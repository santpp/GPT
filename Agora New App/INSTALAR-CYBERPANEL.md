# Ágora Digital 2.1.19 · Instalación en AlmaLinux 9 con CyberPanel

> **Novedades 2.1.19:** columnas editables en todas las tablas (nombre, orden, ocultar, agregar y quitar columnas), botón «Agregar manual» con formulario armado con esas columnas, y Excel de cada tabla (descarga, plantilla y carga masiva) que sigue las columnas editadas. Se corrigieron los menús «Columnas» y «Excel» que no abrían. **No hay migraciones nuevas**: todo vive en `agora_settings` y `agora_records`. Reemplaza `public_html/` y `servidor/` conservando `servidor/config/config.php` y `servidor/storage/`. No se usa Docker.
>
> **Novedades 2.1.18:** se quitó de todas las subcuentas la franja «Cohorte académica · Año de corte · Responsable de gestión» del encabezado del reporte, junto con sus campos en Configuración mensual. Era una personalización de un cliente que ya no se usa. **No hay migraciones nuevas** y no hace falta tocar la base de datos: si una subcuenta tenía esos datos guardados, simplemente ya no se muestran.
>
> **Novedades 2.1.17:** Personalizar con embudos y parámetros de KPI, formulario de venta ganada integrado, Excel y Migración de datos en cada módulo, tablas dinámicas, Catálogo dentro de Productos y servicios y edición de todos los usuarios por el superadmin. **Tampoco hay migraciones nuevas** (todo en `agora_settings` y `agora_records`). Reemplaza `public_html/` y `servidor/` conservando `servidor/config/config.php` y `servidor/storage/`. El `.htaccess` trae las rutas nuevas (`/migracion`, `/productos`, `/personalizar`): súbelo también. No se usa Docker.
>
> **Novedades 2.1.16:** tipo de cuenta por subcuenta, módulo Productos y servicios (rubros y productos con cobro recurrente, pago único o venta única) y módulo Personalizar (tablero del Inicio, ARP Comercial, CRM y módulos visibles). **No hay migraciones nuevas**: todo se guarda en `agora_settings`. Basta con reemplazar `public_html/` y `servidor/` (conservando `servidor/config/config.php` y `servidor/storage/`). En modo pruebas, si quieres ver el catálogo de ejemplo, borra `servidor/storage/agora-pruebas.sqlite` para que se vuelva a crear.

> **Novedades 2.1.15:** módulo ARP con el mismo diseño de la agencia, paleta de colores por cuenta y logos solo en PNG. No hay migraciones nuevas: basta con reemplazar `public_html/` y `servidor/` (conservando `servidor/config/config.php` y `servidor/storage/`). La extensión `gd` (paquete `lsphp83-gd`) es necesaria para reducir logos grandes.

> **No se usa Docker.** Ágora se instala directamente en el servidor: AlmaLinux 9, CyberPanel (servidor web OpenLiteSpeed), PHP 8.3 (lsphp83) y MariaDB. El paquete ya no trae `Dockerfile`, `docker-compose.yml`, `docker/` ni `.env.example`, y la configuración se lee solo de `servidor/config/config.php`.

Entrega preparada para una **instalación nueva** o para actualizar una existente. Hay dos formas de usarla:

- **Pruebas (lo de ahora):** no creas base de datos ni `config.php`. Ágora usa SQLite y entra con **admin / 123456789**, con todos los datos sintéticos cargados. Sigue solo el **punto 0**.
- **Producción:** MySQL/MariaDB, `config.php` y dueño propio (puntos 1 a 9).

## 0. Ambiente de pruebas rápido (sin base de datos)

1. Crea el sitio en CyberPanel con **PHP 8.3** y SSL (punto 1, primer párrafo).
2. Verifica que PHP tenga SQLite. Por SSH como root:

```bash
dnf install -y lsphp83-pdo lsphp83-sqlite3 lsphp83-mbstring
systemctl restart lsws
/usr/local/lsws/lsphp83/bin/php -m | grep -Ei 'pdo_sqlite|mbstring'
```

3. Sube `public_html/` y `servidor/` como indica el punto 2 (pasos 1 a 4, con el `chown` al usuario del sitio). **No crees `servidor/config/config.php`.** PHP debe poder escribir en `servidor/storage/`.
4. Abre `https://TU-DOMINIO`. El primer arranque tarda un par de segundos: crea `servidor/storage/agora-pruebas.sqlite`, el usuario y los datos.
5. Entra con usuario **admin** y clave **123456789** (dueño de la agencia). Las subcuentas tienen sus propios usuarios con la misma clave: `admin.asocim`, `supervisor.asocim`, `ejecutivo1.asocim`… y lo mismo con `.streamingnet`.
6. Cron (punto 6) es opcional en pruebas; si lo pones, que corra como el usuario del sitio.

**Empezar de cero:** borra `servidor/storage/agora-pruebas.sqlite*` (o ejecuta `/usr/local/lsws/lsphp83/bin/php servidor/bin/sembrar-sinteticos.php --reiniciar-pruebas` como el usuario del sitio). Al abrir el sitio se crea todo otra vez.

**Pasar a producción:** crea `config.php` con MySQL (puntos 1 a 4). En cuanto existe ese archivo el modo pruebas se apaga, y la base SQLite deja de usarse; puedes borrarla.

> La clave 123456789 es débil a propósito. No dejes un servidor de pruebas público con datos reales: úsalo solo con los datos sintéticos.

## 1. Requisitos

- AlmaLinux 9 con CyberPanel y OpenLiteSpeed. El sitio se crea en CyberPanel → Websites → Create Website, con **PHP 8.3**, y lleva SSL activo (Websites → Manage → Issue SSL). Instala en la raíz del dominio, no en una subcarpeta.
- PHP 8.3 (lsphp83) con PDO, `pdo_mysql` (paquete `mysqlnd`), `mbstring`, `curl`, `gd`, `iconv`, JSON y OpenSSL, con OPcache activo. Por SSH como root:

```bash
dnf install -y lsphp83 lsphp83-common lsphp83-mysqlnd lsphp83-mbstring lsphp83-gd lsphp83-opcache lsphp83-process lsphp83-intl
systemctl restart lsws
/usr/local/lsws/lsphp83/bin/php -m | grep -Ei 'pdo_mysql|mbstring|gd|openssl|curl'
```

  También puedes hacerlo desde CyberPanel → Server → PHP → Install Extensions (elige PHP 8.3).
- MariaDB 10.11+ (la que instala CyberPanel) o MySQL 8, con InnoDB y `utf8mb4`. Una base vacía y un usuario con permisos sobre ella (CyberPanel → Databases → Create Database). Nunca uses el usuario root de MySQL.
- Una tarea cron cada minuto (punto 6).

## 2. Subir los archivos

El ZIP `agora-cyberpanel-2.1.19.zip` trae dos carpetas: `public_html/` y `servidor/`. En CyberPanel cada sitio vive en `/home/TU-DOMINIO/`:

```
/home/TU-DOMINIO/
├── public_html/   ← contenido de public_html/ del ZIP (incluye .htaccess y api/)
└── servidor/      ← carpeta servidor/ del ZIP, al LADO de public_html, nunca adentro
```

1. Respalda la web y la base antes de reemplazar una instalación anterior. Conserva tu `servidor/config/config.php`.
2. Sube los archivos con el File Manager de CyberPanel (Websites → Manage → File Manager) o por SFTP. Verifica que se subió `public_html/.htaccess`: es un archivo oculto.
3. Deja como dueño al usuario del sitio (lo ves en el File Manager o con `ls -l /home/TU-DOMINIO`):

```bash
chown -R USUARIO_DEL_SITIO:USUARIO_DEL_SITIO /home/TU-DOMINIO/public_html /home/TU-DOMINIO/servidor
find /home/TU-DOMINIO/servidor -type d -exec chmod 750 {} \; && find /home/TU-DOMINIO/servidor -type f -exec chmod 640 {} \;
```

4. **Reinicia OpenLiteSpeed** después de subir o cambiar un `.htaccess`, porque OpenLiteSpeed solo relee las reglas al reiniciar: `systemctl restart lsws` (o CyberPanel → Server Status → LiteSpeed Status → Reboot).
5. OpenLiteSpeed solo aplica las reglas `Rewrite` del `.htaccess`. Ignora `Header`, `Options` y `Require`. Por eso `servidor/` debe quedar **fuera** de `public_html`; la API se niega a arrancar si lo detecta adentro. Para las cabeceras de seguridad, agrega en CyberPanel → Websites → Manage → vHost Conf, dentro de `context / { … }`:

```
extraHeaders            <<<END_extraHeaders
X-Content-Type-Options nosniff
Referrer-Policy strict-origin-when-cross-origin
X-Frame-Options SAMEORIGIN
END_extraHeaders
```

6. No publiques `tests/`, `construccion/`, `prueba.html` ni la documentación privada. Los datos sintéticos viven en `servidor/datos-sinteticos/`, fuera de la web.
7. Si el sitio usa `open_basedir`, debe incluir `/home/TU-DOMINIO` completo (no solo `public_html`), para que PHP pueda leer `servidor/`. Si SELinux está en modo `Enforcing` (`getenforce`), los archivos deben conservar el contexto del sitio: `restorecon -R /home/TU-DOMINIO`.

## 3. Configurar el servidor

Copia `servidor/config/config.example.php` como `servidor/config/config.php`. Completa `app_url` con la dirección exacta del sitio (https://TU-DOMINIO, con o sin `www` tal como se entra) y los datos de la base que creaste en CyberPanel → Databases → Create Database. El host es `localhost`; si la conexión falla, prueba `127.0.0.1`. Conserva `env=production` y `cookie_secure=true`.

Genera dos valores **distintos** de 64 caracteres hexadecimales, uno para `app_key` y otro para `install_token`:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Ejecuta el comando dos veces. No uses claves de ejemplo ni publiques esos valores. Mantén `config.php` privado y con permisos mínimos que permitan leerlo al proceso PHP. No cambies `app_key` rutinariamente: invalida tokens CSRF y controles de frecuencia anteriores.

`db.persistent=true` reutiliza conexiones por proceso PHP. Dimensiona el número de procesos respecto de `max_connections` y la cuota del plan. No es un pool global ni garantiza capacidad ilimitada.

## 4. Instalar y crear el dueño

**Desde el navegador:** abre `https://TU-DOMINIO/install.php`, introduce el token privado y los datos del dueño. La contraseña debe tener al menos 12 caracteres y como máximo 72 bytes. El instalador aplica las migraciones y crea un único usuario `MASTER`.

Al terminar, elimina `public_html/install.php` y deja `install_token` vacío en la configuración. Entra con el correo y la contraseña que acabas de crear. **No existe una contraseña de producción predeterminada.**

**Alternativa por SSH:** entra a `/home/TU-DOMINIO` y ejecuta `/usr/local/lsws/lsphp83/bin/php servidor/bin/migrate.php`; después ejecuta `/usr/local/lsws/lsphp83/bin/php servidor/bin/create-owner.php` proporcionando por entrada estándar un objeto JSON con `nombre`, `correo` y `clave`. Usa un archivo temporal privado fuera de la web, y bórralo después. No pongas contraseñas en argumentos de comandos ni en repositorios.

Migraciones activas: `servidor/app/database/migrations/`. El antiguo `020_saas_governance.sql` se movió a `docs/historico/sql/` y no debe importarse. Las migraciones tienen control de checksum. No edites una migración ya aplicada; añade una nueva. Si una instalación inicial se interrumpe durante DDL, conserva el error y rehace la instalación en otra base vacía; no borres una base con datos reales.

### Actualizar desde 2.0

1. Respaldo de base y archivos.
2. Sube `public_html/` y `servidor/` nuevos (conserva tu `servidor/config/config.php`).
3. Agrega `'remember_seconds'=>604800,` a `config.php` (opcional; ese es el valor por omisión).
4. Ejecuta `/usr/local/lsws/lsphp83/bin/php servidor/bin/migrate.php` por SSH. Aplica `002_agencia.sql` (columnas `plan`, `license_expires_at`, `last_login_at`) y `003_cobros.sql` (columna `billing`). Sin SSH: sube temporalmente `install.php`, define `install_token` y envía el formulario; aplica las migraciones y luego responde "Ya existe un dueño" (es lo esperado). Bórralo y vacía el token.
5. Los navegadores toman la versión nueva solos: el service worker cambió de caché (`agora-digital-app-2.1.0-…`).

## 5. Arranque operativo

1. El dueño crea las subcuentas en Agencia y sus administradores locales.
2. Los Superadmins se asignan explícitamente a las subcuentas autorizadas. La pantalla de usuarios de Agencia muestra únicamente Master y Superadmins.
3. Dentro de cada subcuenta, abre Ajustes para importar datos y Usuarios para gestionar el equipo.
4. Crea una tarea y un convenio de prueba, recarga y comprueba persistencia. Verifica con un usuario local que no puede acceder a otra empresa.
5. Comprueba el perfil, los logotipos y el tamaño móvil. Revisa logs PHP ante errores con su identificador de solicitud.

La instalación empieza vacía. Los gráficos no representan datos reales hasta cargar los reportes. Los ejemplos de `tests/fixtures/` son **sintéticos**, exclusivos de pruebas; no cargarlos como contabilidad real.

## 5 bis. Datos sintéticos en un servidor de pruebas con MySQL

En el ambiente de pruebas del punto 0 los datos se cargan solos. Si en cambio montaste un servidor de pruebas con MySQL, puedes cargarlos con un comando:

```bash
cd /home/TU-DOMINIO
/usr/local/lsws/lsphp83/bin/php servidor/bin/sembrar-sinteticos.php --confirmo-pruebas
```

Al terminar, el comando muestra los correos (`admin.asocim@agora.test`, `ejecutivo1.streamingnet@agora.test`…) y la clave (con MySQL, por defecto `Agora-Pruebas-2026!`, porque ahí la mínima es de 12 caracteres; puedes indicar otra con `--clave=`). Para limpiar: `… sembrar-sinteticos.php --borrar --confirmo-pruebas`. Los detalles están en `servidor/datos-sinteticos/LEEME.md`. **Antes de producción**, borra los datos sintéticos y elimina esa carpeta y el comando.

## 6. Cron y mensajes

En CyberPanel → Websites → Manage → Cron Jobs (o con `crontab -u USUARIO_DEL_SITIO -e`), crea una tarea cada minuto:

```bash
* * * * * /usr/local/lsws/lsphp83/bin/php /home/TU-DOMINIO/servidor/bin/worker.php --max-jobs=50 >/dev/null 2>&1
```

El worker toma un bloqueo para evitar ejecuciones superpuestas, limpia sesiones/limitadores vencidos y claves idempotentes de más de siete días. Procesa hasta 50 mensajes por ejecución. Conserva los logs fuera de `public_html` y establece su rotación. Supervisa `agora_jobs`: `queued`, `running`, `done`, `failed`, `manual_review`.

Para GHL, configura por ID interno de subcuenta `ghl.tenants[ID] = ['token'=>..., 'location_id'=>...]` en el archivo privado. El worker verifica que el contacto externo pertenece a esa ubicación antes de enviar. No se probó entrega externa sin credenciales. Los resultados ambiguos quedan en revisión manual y no se reenvían automáticamente, para evitar duplicados.

**La sincronización automática de contactos, oportunidades y conversaciones de GHL no está implementada en esta entrega.** No basta con agregar un token para habilitarla. La interfaz local dispone de importación JSON para contactos, pipelines, oportunidades y reportes. Webhooks, plantillas, notificaciones push y passkeys requieren implementación/configuración adicional; no se anuncian como activos.

## 7. Convenios y firma por enlace

La creación, cálculo en centavos, edición de borrador, PDF, revocación y protección del documento firmado están implementados. Para habilitar la aceptación por enlace, revisa primero el texto de `Contracts::document()` y activa `signing_enabled`.

El enlace caduca en siete días, se almacena con hash y solo admite una aceptación. Generar otro revoca el anterior. Se comparte manualmente; no se envía correo automático. Esta aceptación registra nombre declarado, fecha e IP y no verifica identidad mediante un proveedor de firma certificada. No presentarla como firma certificada ni como facturación fiscal.

## 8. Verificación y actualizaciones

- Abre `/api/health` y confirma la versión 2.1.19. Abre `/api/status`: debe responder `{"maintenance":false}`.
- Verifica que `/servidor/config/config.php` no sea accesible y que no esté disponible el instalador.
- Comprueba HTTPS, cookies Secure/HttpOnly/SameSite y que `/api/*` nunca se cachee.
- Respaldos diarios de la base, retención acorde a la operación y prueba de restauración en otro entorno. Los adjuntos de perfil se guardan en la base; también respalda los archivos privados de configuración por un canal seguro.
- Actualiza primero en staging. Conserva respaldo y versión anterior del código. Un cambio de esquema no se revierte solamente reemplazando archivos.
- Las rutas de demostración y pruebas no deben ser accesibles en producción. No ejecutar el sembrado de 10.000 usuarios en una base real.

Consulta `docs/ESCALABILIDAD.md`, `docs/IMPORTACION.md` y `docs/AUDITORIA-BACKEND.md` para alcance y resultados verificables.

## 9. Si sale la pantalla de mantenimiento

La pantalla "Oops! Algo ocurre" aparece cuando la app no logra hablar con la API. Desde la 2.1.12 la tarjeta muestra el **detalle para quien administra el servidor**, y lo mismo responde `https://TU-DOMINIO/api/status`:

| Lo que dice | Qué hacer |
|---|---|
| Responde 404 o una página HTML | La API no está enrutada: falta `public_html/.htaccess` o no se reinició OpenLiteSpeed (`systemctl restart lsws`). |
| `servidor` · No se encontró la carpeta servidor/ | Sube `servidor/` a `/home/TU-DOMINIO/servidor`, al lado de `public_html`. |
| `expuesto` · servidor/ está dentro de public_html | Muévela un nivel arriba. |
| `config` · problema en servidor/config/config.php | Corrige `config.php` (punto 3). Sin `config.php`, Ágora arranca en modo pruebas (punto 0). |
| `db` · No se pudo conectar a la base de datos | Revisa nombre, usuario y clave de CyberPanel → Databases; prueba host `127.0.0.1`. |
| `migraciones` · La base está vacía o incompleta | Abre `/install.php` (primera vez) o ejecuta `migrate.php` (punto 4). |
| Al entrar dice "Origen no autorizado" | `app_url` en `config.php` no coincide con la dirección del navegador. Debe ser exacta: `https://`, con o sin `www` igual que como entras. |
| `php` / `ext` | Elige PHP 8.3 para el sitio o instala la extensión indicada (punto 1). En pruebas falta `pdo_sqlite`: `dnf install lsphp83-pdo lsphp83-sqlite3` y `systemctl restart lsws`. |
| `storage` · Modo pruebas: PHP no puede escribir | `chown -R USUARIO_DEL_SITIO:USUARIO_DEL_SITIO /home/TU-DOMINIO/servidor/storage` |
| `pruebas` · no se pudo preparar | Borra `servidor/storage/agora-pruebas.sqlite*` y vuelve a abrir el sitio. El detalle queda en el log de PHP. |

Los errores internos quedan en el log de PHP del sitio (`/home/TU-DOMINIO/logs/`) con el mismo número de referencia que muestra la app.
