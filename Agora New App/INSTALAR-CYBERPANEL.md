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

## Paso 10 · Tarea programada (cron)

Envía los mensajes en cola y limpia sesiones vencidas. CyberPanel → Websites → Manage → **Cron Jobs** → añade:

- **Frecuencia:** cada minuto (`* * * * *`).
- **Comando:**

  ```
  /usr/local/lsws/lsphp83/bin/php /home/TU-DOMINIO/servidor/bin/worker.php --max-jobs=50 >/dev/null 2>&1
  ```

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
     - `locations.readonly`, `users.readonly`
     - `contacts.readonly`, `contacts.write`, `locations/tags.readonly`, `locations/customFields.readonly`
     - `conversations.readonly`, `conversations.write`, `conversations/message.readonly`, `conversations/message.write`
     - `opportunities.readonly`, `opportunities.write`
     - `calendars.readonly`, `calendars/events.readonly`
     - Opcionales, para lo que viene: `invoices.readonly`, `products.readonly`, `socialplanner/account.readonly`, `socialplanner/post.readonly`

     Copia el token (empieza por `pit-`) y pégalo. **Solo se ve una vez en la cuenta**: si lo pierdes, crea otro.
   - **Company ID** (opcional): Agency View → Settings → Company.
3. **Guardar**. Vuelve a abrir **Editar datos** y pulsa **Probar conexión**: debe decir «Conectada con …» y la lista de lo que el token puede leer.
4. Desde ese momento, **Bandeja**, **Contactos**, **Oportunidades**, **Ganadas** y el **tablero** de esa subcuenta leen en vivo de la cuenta. Cada persona ve solo lo que tiene asignado; para eso, su correo en Ágora debe ser el mismo que en la cuenta.

### Consumos de la agencia (solo dueños)

1. **Agencia** → menú de la izquierda → **Consumos**.
2. **Company ID:** Agency View → Settings → Company.
3. **Token de agencia:** en Agency View → Settings → **Private Integrations** → nueva integración con el permiso `saas/company.read` (y los de lectura de ubicaciones si los pide). Pégalo y pulsa **Guardar**.
4. Elige mes y subcuenta y pulsa **Actualizar**. Se puede descargar en texto o PDF.

### Crear los usuarios de cada subcuenta

Agencia → subcuenta → **Administrar usuarios**. Roles: Admin, Supervisor, Ejecutivo y los de consulta. Los ejecutivos solo ven lo que tienen asignado en la cuenta.

---

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

La creación, cálculo en centavos, edición de borrador, PDF, revocación y protección del documento firmado están implementados. Para habilitar la aceptación por enlace, revisa primero el texto de `Contracts::document()` y activa `signing_enabled`.

El enlace caduca en siete días, se almacena con hash y solo admite una aceptación. Generar otro revoca el anterior. Se comparte manualmente; no se envía correo automático. Esta aceptación registra nombre declarado, fecha e IP y no verifica identidad mediante un proveedor de firma certificada. No presentarla como firma certificada ni como facturación fiscal.

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
| Al entrar dice "Origen no autorizado" | `app_url` en `config.php` no coincide con la dirección del navegador. Debe ser exacta: `https://`, con o sin `www` igual que como entras. |
| `php` / `ext` | Elige PHP 8.3 para el sitio o instala la extensión indicada (paso 2). En pruebas falta `pdo_sqlite`: `dnf install lsphp83-pdo lsphp83-sqlite3` y `systemctl restart lsws`. |
| `storage` · Modo pruebas: PHP no puede escribir | `chown -R USUARIO_DEL_SITIO:USUARIO_DEL_SITIO /home/TU-DOMINIO/servidor/storage` |
| `pruebas` · no se pudo preparar | Borra `servidor/storage/agora-pruebas.sqlite*` y vuelve a abrir el sitio. El detalle queda en el log de PHP. |

Los errores internos quedan en el log de PHP del sitio (`/home/TU-DOMINIO/logs/`) con el mismo número de referencia que muestra la app.

| Al conectar una subcuenta: «El token no es válido o fue revocado» | Crea otra integración privada en la subcuenta y pega el token nuevo. |
| «No tiene permiso para leer esta ubicación» | A la integración privada le falta `locations.readonly`. Edítala y añádelo. |
| La bandeja dice «en espera para respetar la cuota» | Es normal un momento: Ágora no pasa de 20 consultas cada 10 segundos por subcuenta. Se recupera sola. |
| Una persona no ve sus conversaciones | Su correo en Ágora debe ser el mismo que en la cuenta, y la conversación debe estar asignada a ella. |
