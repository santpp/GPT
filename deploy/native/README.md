# CyberPanel nativo · PHP 8.3 · sin Docker

Destino: `nueva.agoradigital.app` en Ubuntu/CyberPanel Lite, VPS indicado por el usuario `179.236.253.140`. El usuario confirma que el sitio ya está creado y usa PHP 8.3. No se ha accedido al VPS. Usar esta guía en lugar del despliegue Docker anterior.

## Estructura del ZIP

Extraer **en el directorio del sitio que contiene public_html**, no dentro de public_html:

```text
/home/nueva.agoradigital.app/         # confirmar la ruta real en CyberPanel
  agora-private/
    config/local.example.php
    src/
    bin/
    database/
    var/                            # clave privada generada en el VPS
  public_html/
    index.html
    router.php
    .htaccess
    assets/
    sw.js
    manifest.webmanifest
```

Si se creó como child domain y su document root es distinto, confirmar la ruta antes de extraer. No colocar agora-private dentro de la carpeta pública. No hay que cambiar los puertos ni poner un proxy adicional. No usar el ZIP fuente Docker anterior para este procedimiento.

## 1. SSL y archivos

Confirmar que `https://nueva.agoradigital.app` abre con certificado válido. Si falta SSL, emitirlo desde CyberPanel para este sitio cuando el DNS apunte al VPS. No se ha confirmado desde aquí que el DNS o el certificado estén activos.

Hacer copia de los archivos que ya existan en el sitio antes de extraer; no sobrescribir una aplicación existente. El ZIP incluye index.html, por lo que hay que preservar una página previa si existe. Subir por SFTP o el administrador de archivos del sitio y extraer en el directorio padre de public_html. Si el administrador de archivos no permite escribir fuera de public_html, usar SFTP con acceso al directorio del sitio.

Mantener los archivos bajo el propietario del sitio CyberPanel. No usar chmod 777. Código y carpetas de aplicación: 644/755. Carpetas privadas config y var: 700; configuración y claves: 600 (el usuario PHP del sitio debe ser su propietario). Los archivos de código no contienen secretos; el ZIP de actualización los distribuye como 644 para que PHP pueda leerlos sin relajar la protección de config o var. Si se extrae como root, ajustar propietario sólo en agora-private y los archivos de esta aplicación al usuario real del sitio. No adivinar su nombre ni cambiar permisos de otras webs.

Comprobar que PHP 8.3 tiene PDO MySQL, Sodium, cURL, OpenSSL y mbstring. El verificador CLI lo comprobará; el PHP web debe utilizar la misma versión/extensiones. Si la página muestra código PHP en texto, no introducir credenciales: corregir primero el handler PHP del sitio.

## 2. Base de datos

En CyberPanel, Databases → Create Database (o su equivalente), crear una base dedicada a este sitio y un usuario con acceso únicamente a esa base. Anotar nombre y usuario completos, incluidos los prefijos que añada el panel. Crear una contraseña fuerte en el gestor de contraseñas; no enviarla al chat.

Desde phpMyAdmin, seleccionar esa base NUEVA y vacía e importar en orden:

1. `agora-private/database/schema.sql`
2. `agora-private/database/002-integrations.sql`
3. `agora-private/database/003-api-sync.sql`

No importar el esquema base en una base con tablas de otra aplicación. Para una futura actualización, conservar la base y aplicar sólo las migraciones necesarias, nunca volver a inicializarla.

## 3. Configuración privada

En `agora-private/config`, copiar `local.example.php` a `local.php` y editar **sólo ahí** los datos de conexión de MySQL. Mantener APP_ORIGIN como `https://nueva.agoradigital.app`, APP_ENV=production y webhooks=0. Si el socket MySQL no funciona con localhost, comprobar el host/puerto configurados en CyberPanel antes de cambiarlo; no habilitar acceso público a MySQL.

Este archivo es PHP: las contraseñas con comillas simples o barras deben escaparse correctamente en la cadena PHP. No pegar su contenido en chat. El archivo nunca debe estar dentro de public_html. No incluir el PIT de GHL aquí: se añadirá después desde Integraciones.

## 4. Protección y usuario administrador

**Si ya tienes un administrador y estás corrigiendo un fallo de conexión, no necesitas terminal:** sigue [la actualización 0.2.3](UPDATE-0.2.3.md). Conexiones comprueba la instalación y permite aplicar las tablas adicionales y preparar la protección inicial cuando es seguro. Copiar resultado permite compartir códigos de fallo sin claves ni rutas.

Los siguientes comandos son para la primera instalación, antes de disponer de un usuario administrador.

Desde la terminal SSH, ejecutar como **usuario propietario del sitio**. CyberPanel suele instalar el binario en `/usr/local/lsws/lsphp83/bin/php`; verificarlo con `-v` antes de usarlo. Si la ruta difiere, utilizar el PHP 8.3 de ese VPS. No utilizar el PHP del sistema sin comprobar su versión.

Confirmar primero la ruta real del sitio. Para la ruta habitual:

```sh
cd /home/nueva.agoradigital.app/agora-private
/usr/local/lsws/lsphp83/bin/php bin/generate-key.php
/usr/local/lsws/lsphp83/bin/php bin/check-native.php
```

El generador no muestra la clave y conserva una existente. Hacer backup seguro de esa clave separado de la base. Perderla hace irrecuperables los tokens cifrados. Si falla el verificador, corregir el módulo, conexión o permisos antes de seguir.

Elegir el email de administrador y sustituir `TU_EMAIL` en el comando. En Bash:

```sh
read -r -s -p 'Contraseña administrador (mínimo 12 caracteres): ' agora_admin_password
printf '\n'
printf '%s' "$agora_admin_password" | /usr/local/lsws/lsphp83/bin/php bin/create-user.php TU_EMAIL administrator
unset agora_admin_password
```

No hay usuario/contraseña predeterminados. No repetir este comando para restablecer una cuenta: la recuperación de contraseña todavía está pendiente.

## 5. Rewrites y comprobaciones web

Activar Rewrite y Auto Load from .htaccess para **este sitio** en OpenLiteSpeed si todavía no está activo. Se incluye public_html/.htaccess. Mantener las reglas de SSL del panel y el tratamiento de `/.well-known/` para la renovación. No reemplazar el vHost completo; si tiene reglas personalizadas previas, revisar su compatibilidad antes de combinarlas. Desactivar LSCache para la app, en especial `/api/*`. Aplicar sólo los cambios necesarios en ese sitio.

Visitar `https://nueva.agoradigital.app/api/health`: debe responder `{"status":"ok"}`. `api/me` debe responder 401 sin sesión y las respuestas de API deben usar Cache-Control: no-store. Comprobar que la carpeta privada no es accesible desde la web. `router.php` debe rechazarse al pedirlo directamente. La ruta de conversación debe cargar la app al recargar.

Si `/api/health` da 404, revisar las reglas rewrite. Si responde 503, comprobar logs de PHP del sitio y la configuración privada sin compartir valores. Si se ve código fuente, revisar el handler PHP y dejar de introducir secretos hasta corregirlo. No subir un phpinfo público: revela configuración del servidor.

## 6. Conectar una cuenta

Entrar con tu administrador, abrir Conexiones y añadir nombre, Location ID y Private Integration Token. La comprobación requiere `locations.readonly`; sólo prueba acceso a esa subcuenta. El token se guarda cifrado y no se devuelve al navegador. Configurar sólo desde la URL HTTPS propia.

La demo visual permanece identificada y separada. **Bandeja conectada** muestra una primera ventana real de hasta 20 conversaciones y hasta 20 mensajes por conversación, a través del backend. En esta primera entrega sólo accede el administrador; faltan membresías por vendedor y location. Para sincronizar se requiere `conversations.readonly`; para leer mensajes, `conversations/message.readonly`. Los envíos, el historial completo, FCM y Android siguen pendientes. No habilitar webhooks.

### Consultas periódicas opcionales

Primero comprobar la conexión y probar **Bandeja conectada → Sincronizar ahora**. Cuando esa lectura funcione, activar «Consultas» para la subcuenta en Conexiones y añadir desde CyberPanel una tarea cron **como usuario del sitio**, cada minuto, con este comando (confirmar ruta real y PHP):

```sh
/usr/local/lsws/lsphp83/bin/php /home/nueva.agoradigital.app/agora-private/bin/sync-inbox.php >> /home/nueva.agoradigital.app/agora-private/var/sync-inbox.log 2>&1
```

El script procesa sólo conexiones activadas y vencidas según su intervalo (120 segundos por defecto), con bloqueo global para no acumular procesos. No consulta GHL por cada móvil. Limita cada pasada a 20 conexiones y una llamada de listado por conexión. El log no incluye tokens ni cuerpos; mantenerlo privado y configurar rotación. El intervalo mínimo configurable es 60 segundos; cron por minuto no proporciona actualizaciones de 5 segundos. La app lee la instantánea al entrar o sincronizar manualmente; todavía no hay SSE o refresco automático en pantalla.

Las consultas comparten un presupuesto local conservador de 5.000 peticiones/día por conexión y un espaciado mínimo de un segundo; esto no define los límites reales del PIT. Se respetan pausas por 429 y cabeceras de cuota observadas. Un 401/403 pausa la programación hasta corregir y comprobar la credencial. El primer resultado es parcial si hay más de 20 conversaciones: todavía no hay importación completa ni borrado/reconciliación de todo el CRM.

## Verificación y actualización

La ruta nativa de archivos, carga de configuración y acceso privado se validan aquí con PHP 8.3. La ejecución real de .htaccess y LSAPI depende del OpenLiteSpeed del VPS y no se ha probado remotamente. La publicación y el certificado no se dan por confirmados hasta verificar el sitio real.

Si ya se instaló una versión anterior, ejecutar con PHP 8.3 `bin/migrate-integrations.php` desde agora-private antes de usar Inbox GHL; añade tablas con IF NOT EXISTS sin reinicializar la base.

En actualizaciones, preservar `agora-private/config/local.php`, `agora-private/var/integration.key`, base y sesiones. No extraer un ZIP encima de esos archivos ni copiar claves del entorno de desarrollo. La variable de proceso, si existe, tiene prioridad sobre local.php para mantener compatibilidad con instalaciones gestionadas.

Desde 0.2.3 el ZIP contiene conexiones.html y version.json, y cada build usa una carpeta nueva para sus módulos y estilos. Esto evita que un HTML actualizado importe módulos antiguos por reutilizar sus URLs. El código fuente de desarrollo sigue en public/assets; el ZIP ya viene construido y no requiere ejecutar Python en el VPS.
