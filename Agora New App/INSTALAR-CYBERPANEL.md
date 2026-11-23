# Agora New App · instalación paso a paso

Guía para instalar Agora New App en un servidor **AlmaLinux 9 con CyberPanel** (OpenLiteSpeed, PHP 8.3 y MySQL o MariaDB). **No se usa Docker.**

Cada paso dice **dónde hacer clic** o **qué escribir**. Los comandos están listos para copiar y pegar; solo cambia lo que va en MAYÚSCULAS (por ejemplo `TU-DOMINIO`).

Novedades de cada versión: `NOVEDADES.md`.

---

## Antes de empezar: qué necesitas

| Qué | Para qué |
|---|---|
| Acceso a **CyberPanel** como administrador | Crear el sitio, la base de datos y el cron |
| Acceso a la **terminal** del servidor (la de CyberPanel o SSH como `root`) | Unos pocos comandos: extensiones, permisos, base de datos y cuenta de dueño |
| El paquete **`agora-new-app-X.Y.Z.zip`** | Los archivos de la app |
| El dominio apuntando al servidor | Por ejemplo `app.tudominio.com` |

### Cómo abrir la terminal

- **Opción fácil:** CyberPanel → menú de la izquierda **Server** → **Terminal**. Se abre una ventana negra donde escribes los comandos. Ya entras como `root`.
- **Opción SSH** (desde tu ordenador): `ssh root@IP-DEL-SERVIDOR` y escribe la contraseña de root.

Para **pegar** en la terminal: clic derecho → Pegar, o `Ctrl+Shift+V`. Pulsa **Enter** para ejecutar. Si un comando no muestra nada, normalmente es que fue bien.

En todo el documento, el PHP del sitio es `/usr/local/lsws/lsphp83/bin/php` (el de CyberPanel). Para no repetirlo, al abrir la terminal pega primero esta línea, que crea un atajo `php83` válido mientras la ventana siga abierta:

```bash
alias php83=/usr/local/lsws/lsphp83/bin/php
```

---

## Paso 1 · Crear el sitio con SSL

1. CyberPanel → **Websites** → **Create Website**.
2. Elige el paquete y el dueño, escribe tu dominio (`app.tudominio.com`), un correo y en **PHP** elige **PHP 8.3**. Pulsa **Create Website**.
3. CyberPanel → **Websites** → **List Websites** → tu dominio → **Manage** → **SSL** → **Issue SSL**. Espera el mensaje de éxito.

El sitio vive en `/home/TU-DOMINIO/`. Dentro está `public_html/`.

## Paso 2 · Extensiones de PHP

En la terminal, pega esto **una sola vez**:

```bash
dnf install -y lsphp83 lsphp83-common lsphp83-mysqlnd lsphp83-mbstring lsphp83-gd lsphp83-opcache lsphp83-process lsphp83-intl lsphp83-sodium
systemctl restart lsws
/usr/local/lsws/lsphp83/bin/php -m | grep -Ei 'pdo_mysql|mbstring|gd|openssl|curl|sodium'
```

La última línea debe mostrar `curl`, `gd`, `mbstring`, `openssl`, `pdo_mysql` y `sodium`. Si `dnf` dice que `lsphp83-sodium` no existe, no pasa nada: en muchas instalaciones `sodium` ya viene incluido (compruébalo en la lista).

## Paso 3 · Crear la base de datos

1. CyberPanel → **Databases** → **Create Database**.
2. Elige tu sitio, escribe un **nombre** (por ejemplo `agora`), un **usuario** (por ejemplo `agora`) y una **contraseña larga**. Pulsa **Create Database**.
3. **Apunta los tres datos tal como los muestra CyberPanel**: suele añadir un prefijo, por ejemplo `tudom_agora`. Los necesitas en el paso 6.

## Paso 4 · Subir los archivos

El ZIP trae dos carpetas, `public_html/` y `servidor/`, y esta guía. Deben quedar así:

```
/home/TU-DOMINIO/
├── public_html/   ← la web (index.html, api/, assets/, .htaccess)
└── servidor/      ← la parte privada, AL LADO de public_html, nunca dentro
```

**Con el File Manager** (CyberPanel → Websites → Manage → **File Manager**):

1. Entra en la carpeta `/home/TU-DOMINIO/` (un nivel por encima de `public_html`).
2. Pulsa **Upload** y sube `agora-new-app-X.Y.Z.zip`.
3. Clic derecho sobre el ZIP → **Extract** → extrae en `/home/TU-DOMINIO/`. Si pregunta, **reemplaza** `public_html`.
4. Comprueba que existe `public_html/.htaccess`. Es un archivo oculto: activa «Show hidden files» si no lo ves.

**Por terminal** (si el ZIP ya está en `/home/TU-DOMINIO/`):

```bash
cd /home/TU-DOMINIO && unzip -o agora-new-app-X.Y.Z.zip
```

## Paso 5 · Dar los archivos al usuario del sitio

Mira cuál es el usuario del sitio:

```bash
ls -ld /home/TU-DOMINIO/public_html
```

En la salida, la tercera columna es el usuario (por ejemplo `tudom1234`). Usa ese nombre en lugar de `USUARIO`:

```bash
chown -R USUARIO:USUARIO /home/TU-DOMINIO/public_html /home/TU-DOMINIO/servidor
find /home/TU-DOMINIO/servidor -type d -exec chmod 750 {} \;
find /home/TU-DOMINIO/servidor -type f -exec chmod 640 {} \;
chmod 770 /home/TU-DOMINIO/servidor/storage
```

`servidor/storage/` debe poder escribirse: ahí se guarda la clave con la que se cifran los tokens en el servidor.

## Paso 6 · El archivo de configuración

1. Genera **dos claves distintas** (pega el comando dos veces y copia cada resultado):

   ```bash
   /usr/local/lsws/lsphp83/bin/php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```

2. Crea `config.php` copiando el ejemplo:

   ```bash
   cd /home/TU-DOMINIO/servidor/config && cp config.example.php config.php && chown USUARIO:USUARIO config.php && chmod 640 config.php
   ```

3. Ábrelo con el File Manager (clic derecho → **Edit**) y completa:

   | Campo | Qué poner |
   |---|---|
   | `app_url` | `https://TU-DOMINIO`, exactamente como entras (con o sin `www`) |
   | `app_key` | La **primera** clave del punto 1 |
   | `vault_key` | La **segunda** clave del punto 1 (cifra los tokens de las cuentas) |
   | `db` → `name`, `user`, `password` | Los del paso 3, con el prefijo de CyberPanel |
   | `db` → `host` | `localhost` (si no conecta, prueba `127.0.0.1`) |
   | `env` | `production` |
   | `cookie_secure` | `true` |

4. Guarda. **No cambies `app_key` ni `vault_key` después**: si cambian, hay que volver a escribir los tokens de las cuentas y de la agencia.

## Paso 7 · Preparar la base de datos

```bash
cd /home/TU-DOMINIO
sudo -u USUARIO /usr/local/lsws/lsphp83/bin/php servidor/bin/migrate.php
```

Debe responder `{"ok":true,"applied":[...]}` con la lista de migraciones. Si responde `"applied":[]`, ya estaban aplicadas (no es un error).

## Paso 8 · Crear la cuenta de dueño (solo por terminal)

Las cuentas de **dueño de Ágora** (Master y Superadmin) **solo se crean aquí**: la web no deja crearlas ni ascender a nadie a dueño.

```bash
cd /home/TU-DOMINIO
sudo -u USUARIO /usr/local/lsws/lsphp83/bin/php servidor/bin/crear-dueno.php
```

Te pregunta, uno a uno:

1. **Nombre y apellido**.
2. **Correo** (con el que entrarás).
3. **Contraseña**, de al menos 12 caracteres. **No se ve mientras la escribes**: es normal.
4. **Repite la contraseña**.

- La **primera** cuenta que crees es la **Master** (dueña principal; solo hay una).
- Si vuelves a ejecutar el comando, crea un **Superadmin** (otro dueño de Ágora). Después, la Master le asigna subcuentas desde Agencia → Gestión de usuarios.
- Para ver qué cuentas de dueño existen: `… crear-dueno.php --listar`.
- **Si una cuenta no puede entrar** (clave olvidada o mal escrita al crearla), ponle una contraseña nueva desde aquí. Sirve para cualquier cuenta, también la Master; cierra sus sesiones abiertas y quita el bloqueo por intentos:

```bash
sudo -u USUARIO /usr/local/lsws/lsphp83/bin/php servidor/bin/cambiar-clave.php correo@ejemplo.com
```

- Si solo dice «Demasiados intentos», basta con `… cambiar-clave.php --desbloquear` (o esperar 15 minutos).

## Paso 9 · Reiniciar el servidor web y cabeceras de seguridad

OpenLiteSpeed solo lee las reglas del `.htaccess` al reiniciar:

```bash
systemctl restart lsws
```

Cabeceras de seguridad (opcional pero recomendado): CyberPanel → Websites → Manage → **vHost Conf**. Dentro de `context / { … }` añade lo siguiente, guarda y vuelve a ejecutar `systemctl restart lsws`:

```
extraHeaders            <<<END_extraHeaders
X-Content-Type-Options nosniff
Referrer-Policy strict-origin-when-cross-origin
X-Frame-Options SAMEORIGIN
END_extraHeaders
```

## Paso 10 · Tarea programada (cron) · una sola línea

Desde la 2.20.0 hay **un solo cron**: `servidor/bin/cron.php`. Cada minuto hace todo lo que el sistema necesita en segundo plano:

- **Tareas del sistema** (lo que antes hacía `worker.php`): mensajes en cola, limpieza de sesiones vencidas y la **tasa BCV del día** (la pide a `ve.dolarapi.com` y, si falla, a `www.bcv.org.ve`; reintenta cada hora).
- **La Bandeja en vivo** (la misma revisión que usa Ágora Mobile): trae los mensajes nuevos de cada subcuenta conectada y repasa cada 10 segundos durante el minuto, así un mensaje se nota en segundos. Una vez al día vuelve a leer el equipo de cada cuenta.
- **Las notificaciones** de mensajes nuevos: le llegan a quien tiene asignada la conversación (o a administración y supervisión, si nadie la tiene), en la campana de arriba.

CyberPanel → Websites → Manage → **Cron Jobs** → añade (con el usuario del sitio):

- **Frecuencia:** cada minuto (`* * * * *`).
- **Comando:**

  ```
  /usr/local/lsws/lsphp83/bin/php /home/TU-DOMINIO/servidor/bin/cron.php >/dev/null 2>&1
  ```

Si ya tenías la línea de `worker.php`, **reemplázala** por esta (no las dejes las dos; no se dañaría nada, pero sobra).

Para comprobarlo a mano: `php servidor/bin/cron.php --once` dice qué hizo, y `php servidor/bin/diagnostico.php` muestra en la sección **Cron** hace cuánto corrió por última vez (en rojo si nunca corrió o lleva más de 15 minutos sin correr).

### Facturación fiscal: protección de lo emitido

Al instalar la 2.11.0, Ágora crea en la base de datos unos **disparadores** que impiden cambiar o borrar una factura emitida, aunque alguien entre por phpMyAdmin. En algunos servidores el usuario de la base no tiene permiso para crearlos. No pasa nada grave: Ágora igual impide cambiarlas y cada factura queda sellada en una cadena que se puede verificar. Facturación → Datos fiscales avisa si falta y trae el botón «Intentar activarla». Para activarla a mano, el administrador del servidor puede dar el permiso `TRIGGER` al usuario de la base (y, si el servidor tiene registro binario, poner `log_bin_trust_function_creators = 1`).

## Paso 11 · Comprobar

Abre en el navegador:

- `https://TU-DOMINIO/api/health` → debe decir `{"status":"ok","version":"X.Y.Z"}`.
- `https://TU-DOMINIO/api/status` → `{"maintenance":false}`.
- `https://TU-DOMINIO/` → la pantalla de entrada. Entra con el correo y la contraseña del paso 8.

Comprueba también que `https://TU-DOMINIO/servidor/config/config.php` **no** abre nada.

---

## Paso 12 · Primeros pasos dentro de la app

### Crear una subcuenta y conectarla con su cuenta

1. **Agencia** → **Nueva subcuenta**: nombre de la empresa, de la subcuenta, código y logo (PNG; si es muy grande se reduce solo).
2. En **«Conexión con la cuenta»**:
   - **Location ID:** en la subcuenta de la cuenta → Settings → Business Profile (arriba, «Location ID»).
   - **Token de la integración privada:** en la subcuenta → Settings → **Private Integrations** → **Create new integration**. Ponle un nombre (por ejemplo «Ágora») y marca estos permisos:
     - `locations.readonly`, `users.readonly` y **`users.write`** (desde 2.22.0: para crear a las personas en la cuenta con su rol al invitarlas)
     - `contacts.readonly`, `contacts.write`, `locations/tags.readonly`, `locations/customFields.readonly`
     - `conversations.readonly`, `conversations.write`, `conversations/message.readonly`, `conversations/message.write`
     - `opportunities.readonly`, `opportunities.write`
     - `calendars.readonly`, `calendars/events.readonly`
     - Para **Redes y anuncios** (solo lectura): `socialplanner/account.readonly`, `socialplanner/post.readonly`, `socialplanner/statistics.readonly` y, para las campañas de Meta, `adPublishing.readonly`
     - Opcionales, para lo que viene: `invoices.readonly`, `products.readonly`

     Copia el token (empieza por `pit-`) y pégalo. **Solo se ve una vez en la cuenta**: si lo pierdes, crea otro.
3. **Guardar**. Vuelve a abrir **Editar datos** y pulsa **Probar conexión**: debe decir «Conectada con …» y la lista de lo que el token puede leer.
4. Desde ese momento, **Bandeja**, **Contactos**, **Oportunidades**, **Ganadas** y el **tablero** de esa subcuenta leen en vivo de la cuenta. Cada persona ve solo lo que tiene asignado; para eso, su correo en Ágora debe ser el mismo que en la cuenta.

### Conectar la agencia: token de agencia para Consumos (solo dueños, una sola vez)

Cada subcuenta usa **su** token (el de arriba). La billetera de la agencia (gasto, recargas y plan de cada subcuenta) se lee con **otro** token, el de la agencia, que se pone una sola vez:

1. **Agencia** → **Subcuentas**: arriba sale la tarjeta **«Conexión de la agencia»** → **Conectar la agencia**.
2. **Company ID:** Agency View → Settings → Company.
3. **Token de agencia:** en **Agency View** (no dentro de una subcuenta) → Settings → **Private Integrations** → nueva integración con el permiso `saas/company.read` (y `locations.readonly` si lo ofrece). Copia el token (`pit-…`).
4. Pégalo y pulsa **Guardar y probar**. Debe quedar «Conectada». Si no, el aviso dice qué falta (token rechazado, sin permiso o Company ID equivocado).
5. **Agencia** → **Consumos**: elige mes y subcuenta y pulsa **Actualizar**. Se puede descargar en texto o PDF.

El token de agencia se guarda cifrado y nadie puede volver a verlo; guardar, probar o quitarlo queda en el **Registro de actividad**.

### Crear los usuarios de cada subcuenta y enlazarlos con la cuenta

1. Entra a la subcuenta → **Administración** → **Usuarios y roles** → **Invitar persona**: nombre y apellido, correo y rol (Administración, Supervisión, Ejecutivo o Consulta). Cada persona ve solo los módulos que su rol permite.
   - Le llega un correo con **«Crear mi clave»**: escribe su correo, recibe un código de 6 dígitos y crea su clave. Hasta entonces sale como **«Invitación pendiente»** (Opciones → Reenviar invitación). Necesita el **Correo de Ágora** configurado; si no, Opciones → **Darle una clave temporal**.
   - Con **«Crearla también en la cuenta»** (2.22.0, necesita `users.write`), Ágora la crea en la cuenta con los permisos de su rol y la deja enlazada. Si su correo ya estaba en la cuenta, solo la enlaza y le ajusta los permisos. Reparto: Administración = administrador con todo; Supervisión = ve todo, sin ajustes ni pagos; Ejecutivo = solo lo asignado (conversaciones, contactos, oportunidades y citas); Consulta = solo lo asignado, contactos y oportunidades, sin conversaciones.
   - Cambiar el rol ajusta sus permisos en la cuenta. **Suspender o eliminar** en Ágora le **quita los permisos en la cuenta, pero no la borra** (sus conversaciones no quedan huérfanas); reactivar se los devuelve.
   - La clave de la cuenta la genera Ágora al azar y no se guarda: si la persona necesita entrar a la cuenta, usa «olvidé mi clave» allí.
2. Si la subcuenta está conectada, la columna **«Usuario en la cuenta»** dice con quién está enlazada cada persona:
   - **Enlazado con …** «por tener el mismo correo»: Ágora lo dedujo solo.
   - **Sin enlazar:** nadie en la cuenta tiene ese correo. Pulsa **Opciones → Enlazar con la cuenta** y elige a la persona.
3. Si agregaste gente nueva en la cuenta, pulsa **Actualizar equipo** para que aparezca en la lista.
4. Un ejecutivo **sin enlazar no ve conversaciones, contactos ni oportunidades** (la app se lo avisa). Los administradores y supervisores ven toda la subcuenta aunque no estén enlazados.

### Contactos nuevos

Con la subcuenta conectada, **Contactos → Nuevo contacto** lo crea en la cuenta (hace falta el teléfono o el correo). Necesita el mismo permiso `contacts.write` de la integración privada.

### Redes y anuncios, y campañas de Meta

**Gestión y resultados → Redes y anuncios** es solo para ver:
- **Campañas de Meta** del mes (los últimos 6): inversión, resultados, costo por resultado, impresiones, alcance, clics y CTR, en total y por campaña. Lo mismo aparece arriba en **Embudo y publicidad**.
- Las **redes conectadas** y sus cifras de los últimos 7 días frente a los 7 anteriores, en total y por red.
- Las **publicaciones** (publicadas, programadas, con error, borradores), agrupadas por red. Incluyen las hechas en Meta Business Suite si en la cuenta la red tiene activada la sincronización de publicaciones (Social Planner → ajustes → «Sync posts»).

Para que salgan las campañas: la cuenta publicitaria de Meta tiene que estar conectada en la cuenta de la subcuenta (Marketing → administrador de anuncios → conectar Facebook) y la integración privada necesita `adPublishing.readonly`. Sirve también para campañas creadas directamente en Meta.

- Lo ven Administración y Supervisión. Un ejecutivo no lo ve.
- Las redes y la cuenta publicitaria se conectan en la cuenta, no en Ágora.

### Tareas

Con la subcuenta conectada, **Nueva tarea** pide el contacto y la tarea queda escrita en su ficha en la cuenta. Completarla o corregirla en Ágora la cambia también allí. Las tareas creadas desde la ficha del contacto en la Bandeja aparecen en el módulo Tareas. La integración privada de la subcuenta necesita el permiso `contacts.write` (ya está en la lista del paso anterior).

---

## Lista para desplegar (2.20.0 y 2.21.0)

1. Respaldo de la base y de los archivos (CyberPanel → Backup), y aparte `servidor/config/config.php` y `servidor/storage/motor-vault.key`.
2. Sube el ZIP y reemplaza `public_html/` y `servidor/` (conserva `config.php` y `storage/`). Repite el **paso 5** (permisos).
3. En `config.php`: `'env'=>'production'`, `app_url` con `https://` exacto y, si vas a usar convenios por enlace, `'signing_enabled'=>true`.
4. Cron: **una sola línea** con `cron.php` (paso 10).
5. Agencia → **Correo de Ágora**: elige la subcuenta que envía y pruébala (respaldo de los correos a clientes y de los códigos de acceso).
6. Corre `php servidor/bin/diagnostico.php`: nada en rojo. Lo amarillo, revísalo.
7. Abre la app, entra y mira la campana, un convenio de prueba enviado a tu propio correo y una factura enviada a tu correo.

## Pasar a mi.agoradigital.app usando la base de datos de tests (2.21.0)

Sirve para estrenar el dominio definitivo **sin perder nada** de lo que ya está en tests (subcuentas, tokens, usuarios, ventas, convenios, facturas). Lo importante: **la base de datos sola no basta**. Los tokens de las cuentas están cifrados con `vault_key` y con `servidor/storage/motor-vault.key`; si cambian, los tokens guardados no se pueden abrir.

**Opción A · el mismo sitio con otro nombre (la más simple).** Si en CyberPanel solo cambias el dominio del sitio de tests a mi.agoradigital.app (mismos archivos, misma base):

1. Respaldo primero (CyberPanel → Backup: sitio y base de datos).
2. Emite el SSL del dominio nuevo (CyberPanel → SSL → Manage SSL → mi.agoradigital.app). El DNS de `mi` debe apuntar ya al servidor.
3. En `servidor/config/config.php` cambia **solo** `app_url` a `'https://mi.agoradigital.app'` (sin barra al final). **No toques** `app_key`, `vault_key` ni `db`.
4. Si la carpeta del sitio cambió (CyberPanel usa `/home/DOMINIO/`), corrige la ruta en la **línea del cron** (paso 10) y repite el **paso 5** (permisos).
5. Sube el ZIP 2.21.0 como una actualización normal (sección «Actualizar a una versión nueva»).
6. Corre `php servidor/bin/diagnostico.php`: la línea «Dirección (app_url)» debe decir `https://mi.agoradigital.app` y nada en rojo.

**Opción B · un sitio nuevo con la base de tests.** Si creas mi.agoradigital.app como sitio aparte:

1. Crea el sitio con SSL (paso 1), extensiones (paso 2) y sube el ZIP (pasos 4 y 5).
2. Base de datos: puedes **apuntar a la misma base** de tests (mismos datos en `db`) o **copiarla** a una base nueva:
   ```bash
   mysqldump -u USUARIO_TESTS -p BASE_TESTS > /root/agora-tests.sql
   mysql -u USUARIO_MI -p BASE_MI < /root/agora-tests.sql
   ```
   (Si al importar sale un error de `DEFINER`, quita esas partes: `sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' -i /root/agora-tests.sql`).
3. Copia del sitio de tests **`servidor/config/config.php`** y **`servidor/storage/motor-vault.key`** (y, si quieres conservar las copias rápidas, todo `servidor/storage/`). Mismo dueño y permisos del sitio nuevo (paso 5).
4. En el `config.php` copiado cambia `app_url` a `'https://mi.agoradigital.app'` y, si copiaste la base, los datos de `db`. **`app_key` y `vault_key` se quedan iguales.**
5. Cron: la línea del paso 10 con la ruta del sitio nuevo. **Quita el cron del sitio de tests** si los dos usan la misma base (si no, los dos traerían los mensajes a la vez; no se rompe nada, pero es trabajo doble).
6. `php servidor/bin/diagnostico.php` y entra.

**Lo que cambia para las personas (en las dos opciones):**
- Todos tienen que **volver a entrar** una vez: las sesiones y «Recuérdame en este equipo» van atados al dominio. Si alguien había guardado una llave de acceso (huella) en tests, tendrá que crearla de nuevo.
- Los **enlaces ya enviados** a clientes (firma de convenios y facturas) llevan la dirección de tests. Para que sigan sirviendo, deja tests redirigiendo a mi: en CyberPanel → tests.agoradigital.app → Rewrite Rules:
  ```
  RewriteEngine On
  RewriteRule ^(.*)$ https://mi.agoradigital.app/$1 [R=301,L]
  ```
  Los enlaces llevan su código después de `#`, que el navegador conserva al redirigir. Los enlaces nuevos ya salen con mi.agoradigital.app.
- **Avisos de la cuenta (webhooks):** este ERP no los usa; los mensajes llegan por el cron. No hay nada que cambiar en la cuenta.
- Revisa en Agencia → **Correo de Ágora** que la subcuenta que envía sigue conectada y manda un correo de prueba: el logo de los correos sale de `app_url`.

## Actualizar a una versión nueva

1. Haz un **respaldo**: CyberPanel → Backup (sitio y base de datos).
2. Sube el ZIP nuevo y extráelo **reemplazando** `public_html/` y `servidor/`. **Conserva** `servidor/config/config.php` y `servidor/storage/`: no los borres.
3. Repite el **paso 5** (permisos).
4. Abre la app: las migraciones nuevas se aplican solas. Si prefieres hacerlo a mano: el **paso 7**.
5. Si cambió el `.htaccess`: `systemctl restart lsws`.
6. Comprueba `https://TU-DOMINIO/api/health` (versión nueva).

## Respaldos

- **Base de datos y archivos**, a diario, desde CyberPanel → Backup.
- Guarda aparte, en un sitio seguro, `servidor/config/config.php` y `servidor/storage/motor-vault.key`. Sin ellos, los tokens guardados no se pueden abrir y hay que volver a escribirlos.

---

## Paquete de demostración

`agora-cyberpanel-2.1.19-demo.zip` es la versión original con datos inventados y un **modo pruebas** (entra con `admin` / `123456789`, sin base de datos). Sirve solo para enseñar la app. **Nunca** lo instales en un servidor con datos reales. El paquete de producción no trae ese modo: si falta `config.php`, avisa en lugar de abrir una base de pruebas.

---

## Convenios y firma por enlace

La creación, cálculo en centavos, edición de borrador, PDF, revocación y protección del documento firmado están implementados. Para habilitar la aceptación por enlace, revisa primero el texto de `Contracts::document()` y pon `'signing_enabled'=>true` en `servidor/config/config.php`.

Desde la 2.20.0, al preparar el convenio **el enlace se envía por correo al cliente**, desde la propia subcuenta (si no está conectada, por el Correo de Ágora). El correo trae el botón «Revisar y firmar el convenio». Si no se pudo enviar, la pantalla dice por qué y deja el enlace para compartirlo por WhatsApp. El enlace caduca en siete días, se guarda solo su huella y admite una sola aceptación; enviar uno nuevo revoca el anterior. La aceptación registra nombre declarado, fecha e IP y no verifica identidad. Al firmar, administración y supervisión reciben un aviso en la campana.

## Facturas por correo (2.20.0)

En Facturas, una factura emitida trae **«Enviar por correo»**: el cliente recibe un enlace personal (vence en 60 días) a `https://TU-DOMINIO/factura.html` para verla, imprimirla o guardarla en PDF, sin entrar a Ágora. Se guarda solo la huella del enlace. La primera vez que el cliente la abre, quien la envió recibe un aviso. Para que el logo salga en los correos, `app_url` debe empezar con `https://`.

---

## Si algo falla: la pantalla de mantenimiento

La pantalla "Oops! Algo ocurre" aparece cuando la app no logra hablar con la API. Desde la 2.1.12 la tarjeta muestra el **detalle para quien administra el servidor**, y lo mismo responde `https://TU-DOMINIO/api/status`:

| Lo que dice | Qué hacer |
|---|---|
| Responde 404 o una página HTML | La API no está enrutada: falta `public_html/.htaccess` o no se reinició OpenLiteSpeed (`systemctl restart lsws`). |
| `servidor` · No se encontró la carpeta servidor/ | Sube `servidor/` a `/home/TU-DOMINIO/servidor`, al lado de `public_html`. |
| `expuesto` · servidor/ está dentro de public_html | Muévela un nivel arriba. |
| `config` · problema en servidor/config/config.php | Corrige `config.php` (paso 6). Sin `config.php`, la app avisa y no arranca. |
| `db` · No se pudo conectar a la base de datos | Revisa nombre, usuario y clave de CyberPanel → Databases; prueba host `127.0.0.1`. |
| `migraciones` · La base está vacía o incompleta | Ejecuta el **paso 7** (`migrate.php`). |
| Al entrar dice «Correo o contraseña incorrectos» con la clave correcta | Pon una clave nueva con `servidor/bin/cambiar-clave.php correo` (paso 8). Revisa que el correo sea el mismo con el que se creó la cuenta (`crear-dueno.php --listar`). |
| Al entrar dice «Demasiados intentos» | Son 15 intentos por correo cada 15 minutos. Espera o ejecuta `servidor/bin/cambiar-clave.php --desbloquear`. |
| La pantalla de entrada dice «Ambiente de pruebas · datos sintéticos» | El servidor no encuentra `servidor/config/config.php` y arrancó en modo pruebas, con cuentas de prueba: las cuentas reales no entran. Revisa que `config.php` siga en su lugar (paso 6). |
| Al entrar dice "Origen no autorizado…" | `app_url` en `config.php` no coincide con la dirección del navegador. Debe ser exacta: `https://`, con o sin `www` igual que como entras. |
| `php` / `ext` | Elige PHP 8.3 para el sitio o instala la extensión indicada (paso 2). En pruebas falta `pdo_sqlite`: `dnf install lsphp83-pdo lsphp83-sqlite3` y `systemctl restart lsws`. |
| `storage` · Modo pruebas: PHP no puede escribir | `chown -R USUARIO_DEL_SITIO:USUARIO_DEL_SITIO /home/TU-DOMINIO/servidor/storage` |
| `pruebas` · no se pudo preparar | Borra `servidor/storage/agora-pruebas.sqlite*` y vuelve a abrir el sitio. El detalle queda en el log de PHP. |

Los errores internos quedan en el log de PHP del sitio (`/home/TU-DOMINIO/logs/`) con el mismo número de referencia que muestra la app.

| Al conectar una subcuenta: «El token no es válido o fue revocado» | Crea otra integración privada en la subcuenta y pega el token nuevo. |
| «No tiene permiso para leer esta ubicación» | A la integración privada le falta `locations.readonly`. Edítala y añádelo. |
| La tarjeta «Conexión de la agencia» dice que el token no es válido | Ese token es de una subcuenta, o fue revocado. Créalo en **Agency View** → Private Integrations. |
| «El token no tiene permiso para leer la billetera» | A la integración de agencia le falta `saas/company.read`, o el Company ID es de otra agencia. |
| Una ventana se ve sin estilos (botones grises, letra distinta) | El navegador guardó archivos de una versión anterior. Desde la 2.3.1 la app pide siempre los nuevos: recarga una vez. Si sigue, Chrome → candado de la barra → Configuración del sitio → **Borrar datos**. |
| La bandeja dice «en espera para respetar la cuota» | Es normal un momento: Ágora no pasa de 20 consultas cada 10 segundos por subcuenta. Se recupera sola. |
| Una persona no ve sus conversaciones | Su correo en Ágora debe ser el mismo que en la cuenta, y la conversación debe estar asignada a ella. |


## Cabeceras de seguridad (2.12.14)

El `.htaccess` de `public_html` ya trae las cabeceras (CSP, HSTS, Permissions-Policy, COOP/CORP, nosniff, Referrer-Policy, X-Frame-Options) y bloquea archivos ocultos y de trabajo. Para comprobar en el servidor:

```
curl -sI https://tests.agoradigital.app/ | grep -iE "strict-transport|content-security|permissions-policy|cross-origin"
curl -sI https://tests.agoradigital.app/api/health | grep -iE "x-frame|content-security"
curl -s -o /dev/null -w "%{http_code}\n" https://tests.agoradigital.app/NOVEDADES.md   # debe dar 403 o 404
```

Si la primera no muestra las cabeceras, en CyberPanel: el sitio → «vHost Conf» → confirmar que `allowOverride` permite `Headers` (o «Rewrite» y «Headers»), y reiniciar OpenLiteSpeed. La API manda sus propias cabeceras aunque eso falle.


## Correo de Ágora y código al entrar (2.13.0)

No hace falta SMTP. Después de instalar:

1. Entra como dueño → **Agencia** → **Correo de Ágora** → «Elegir subcuenta». Elige la subcuenta de la agencia (debe estar conectada y su cuenta debe tener el canal de correo activo).
2. Pulsa **Probar envío**: te llega un correo a tu dirección de dueño.
3. Desde ese momento, entrar pide un código por correo. Con «Recuérdame en este equipo (14 días)», ese navegador no lo vuelve a pedir en 14 días.
4. La migración `009_acceso.sql` se aplica sola al primer pedido a la API. Si quieres hacerlo a mano: `php servidor/bin/migrate.php`.
5. En `servidor/config/config.php`, `remember_seconds` puede quedar como estaba: el valor antiguo (604800) se toma como 14 días. Para otro valor, ponlo en segundos.

Permisos que necesita la cuenta de la subcuenta elegida (guía para la agencia): contactos (leer y escribir, para el contacto interno del destinatario) y conversaciones/mensajes (escribir, para enviar el correo).
