# Alternativa Docker anterior · no usar para el despliegue actual

El usuario eligió **CyberPanel nativo con PHP 8.3, sin Docker**. Seguir [la guía nativa](native/README.md). Esta alternativa se conserva como referencia y no es necesaria en el VPS.

Destino confirmado por el usuario: **Ubuntu, CyberPanel Lite, IP pública 179.236.253.140**. No se ha accedido al VPS ni modificado su DNS. OpenLiteSpeed/CyberPanel conserva los puertos 80/443 y gestiona HTTPS. La aplicación se publica sólo en `127.0.0.1:8080` y el sitio la sirve mediante proxy inverso. Confirmar que 8080 está libre antes de arrancar. No detener las webs existentes ni sustituir su servidor HTTP.

Esta entrega publica una demo con panel de integraciones; no convierte la aplicación en un CRM funcional completo. Sincronización REST, envíos y push siguen pendientes.

## 1. DNS

En el proveedor que gestiona los DNS autoritativos de agoradigital.app, crear un registro **A** con nombre `nueva` y destino `179.236.253.140`. Revisar un registro existente antes de modificarlo. No cambiar el dominio principal ni los registros de correo. Crear AAAA sólo si IPv6 del VPS está configurado correctamente. Para la primera comprobación usar DNS directo si el proveedor dispone de proxy HTTP y no se ha decidido utilizarlo.

## 2. Preparar el VPS y subir el proyecto

Confirmar versión de Ubuntu y si Docker ya está instalado antes de instalarlo desde su fuente oficial. La instalación exacta depende de la versión de Ubuntu. Este documento no ejecuta instaladores remotos ni modifica firewall. Deben poder llegar conexiones TCP 80 y 443; mantener también el acceso SSH existente. La base y PHP-FPM no publican puertos.

Subir el paquete fuente a un directorio como `/opt/agora` mediante SFTP o un despliegue Git privado, y extraerlo allí. El paquete no incluye claves, `.env` ni datos locales. No clonar un repositorio vacío suponiendo que ya contiene estos cambios: aún no se han subido a GitHub. El archivo preparado es `agora-release.tar.gz`; extraerlo con `tar -xzf agora-release.tar.gz -C /opt/agora` una vez creado ese directorio.

## 3. Preparar los secretos en el propio VPS

Ejecutar dentro del checkout:

```sh
python3 deploy/prepare.py
```

Crea `deploy/.env.production` con contraseñas de base aleatorias y `deploy/secrets/integration.key` con la clave de cifrado. No muestra valores ni sobrescribe archivos existentes. Mantener los permisos 600 y el directorio secrets privado. GHL_LOCATION_ID=agora-workspace es un identificador interno inicial del tenant, no una conexión real ni una credencial GHL.

**Actualizaciones/restauraciones:** restaurar primero los archivos originales de configuración y clave. No generar claves nuevas si ya hay tokens cifrados. Respaldar la clave de forma segura y separada de la base. Los secretos locales de este entorno de desarrollo no deben copiarse al VPS.

## 4. Crear el sitio y su certificado en CyberPanel

Crear un sitio web (Websites → Create Website, según la versión del panel) para `nueva.agoradigital.app`. No usar ese nombre para reemplazar otro sitio. Una vez propagado el registro A, emitir SSL para ese sitio desde CyberPanel (SSL → Manage SSL o el control SSL del sitio, según versión). OpenLiteSpeed sirve los desafíos ACME y conserva el certificado y su renovación.

Todavía no añadir reglas proxy hasta que el backend esté sano. Si la emisión de SSL falla, revisar DNS, IPv6 y acceso a los puertos antes de modificar otras webs. No se ha confirmado el DNS: la resolución desde el entorno de trabajo falló temporalmente y ese fallo no demuestra que falte el registro.

## 5. Arrancar el backend privado

Después de comprobar que `127.0.0.1:8080` no lo utiliza otro servicio:

```sh
docker compose --env-file deploy/.env.production -f deploy/compose.production.yaml up -d --build
docker compose --env-file deploy/.env.production -f deploy/compose.production.yaml exec -T api php bin/migrate-integrations.php
docker compose --env-file deploy/.env.production -f deploy/compose.production.yaml ps
```

Comprobar desde el VPS `curl --fail http://127.0.0.1:8080/api/health`. El contenedor Caddy funciona como router HTTP interno, sin solicitar certificados ni ocupar 80/443. El HTTPS público lo proporciona CyberPanel.

## 6. Conectar OpenLiteSpeed al backend

En la configuración del virtual host de **este dominio**, registrar una aplicación externa de tipo Web Server/Proxy hacia `127.0.0.1:8080` y dirigir las peticiones a ella. Se incluye `openlitespeed-proxy.conf.example` como referencia para fusionar con la configuración existente. Los controles pueden estar en el editor de vHost de CyberPanel o en WebAdmin de OpenLiteSpeed, dependiendo de la versión.

No sustituir el archivo vHost completo ni duplicar un bloque rewrite existente. Excluir `/.well-known/` para que CyberPanel pueda renovar SSL. Conservar la redirección de HTTP a HTTPS del panel y desactivar la caché de páginas de LiteSpeed para esta aplicación. Aplicar una recarga controlada del servicio tras validar la configuración; no parar el servidor ni eliminar otros sitios.

La configuración exacta del vHost debe revisarse en el VPS antes de aplicar el ejemplo. Esta capa OpenLiteSpeed aún no se ha probado; no hay acceso remoto configurado en este entorno.

PHP corre con PHP-FPM. Las sesiones sobreviven al reinicio en un volumen y las cookies son Secure/HttpOnly/SameSite Strict. La base y las sesiones tienen volúmenes separados. El certificado lo conserva CyberPanel, fuera de estos contenedores. El frontend se empaqueta en cada imagen web; no usa un volumen persistente que congele versiones antiguas.

## 7. Crear tu administrador

Elegir el email que se usará para entrar. En una shell Bash del VPS, sustituir `TU_EMAIL` por ese email (no es una contraseña):

```sh
read -r -s -p 'Contraseña administrador (mínimo 12 caracteres): ' agora_admin_password
printf '\n'
printf '%s' "$agora_admin_password" | docker compose --env-file deploy/.env.production -f deploy/compose.production.yaml exec -T api php bin/create-user.php TU_EMAIL
unset agora_admin_password
```

La contraseña no aparece en argumentos, en el historial ni en el repositorio. El comando no debe repetirse para restablecer una cuenta; todavía no hay recuperación de contraseña implementada. No hay usuarios ni tokens predeterminados.

## 8. Comprobar antes de introducir GHL

```sh
curl --fail --show-error https://nueva.agoradigital.app/api/health
```

Debe responder `{"status":"ok"}` con certificado válido. Entrar con el administrador y abrir Integraciones. Verificar que un vendedor/supervisor no pueda administrarlas. Introducir el PIT únicamente en esta URL HTTPS de confianza, con el Location ID de la subcuenta. Comprobar conexión requiere `locations.readonly`. Un resultado positivo no verifica todos los módulos ni activa todavía una sincronización.

Para leer logs, limitarse al servicio que falla y no compartir valores de entorno, archivos de credenciales ni cuerpos de peticiones. Nunca ejecutar `docker compose config` sin `--quiet` para mostrar configuración con secretos.

## Actualizaciones y respaldo

Conservar configuración, clave y volúmenes; hacer backup y restauración probada antes de actualizaciones. Repetir build/arranque y aplicar sólo migraciones compatibles. Nunca usar `down -v` en el VPS con datos. Respaldar base y certificados además de la clave. Limitar acceso SSH y acceso al socket Docker. Los administradores del VPS/Docker pueden leer los secretos: Docker Compose secrets los monta desde archivo, no es un servicio de bóveda cifrada.

Quedan por configurar las operaciones del VPS (backups, alertas, firewall adaptado a lo que ya aloja y actualización del sistema) y completar las funciones de aplicación documentadas en docs/architecture.md. No anunciar producción operativa sólo porque la página carga.

## Validación realizada aquí

Construidas imágenes PHP 8.3-FPM y Caddy; arranque aislado con MariaDB. Diez comprobaciones HTTP de página, API, sesión segura, caché y bloqueo de archivos PHP/configuración. Comprobado que el usuario www-data puede cifrar con la clave protegida; el entrypoint copia la fuente secreta en un tmpfs privado, sin cambiar sus permisos en el host. Esta validación local no demuestra el funcionamiento de DNS, SSL u OpenLiteSpeed del VPS.

Tras desplegar, ejecutar `python3 deploy/smoke.py` para verificar la URL pública con TLS normal, sin desactivar la validación del certificado. No solicitar secretos por chat.
