# Comandos de terminal · Agora Digital App - ERP

Chuleta con los comandos más comunes para **instalar**, **dar soporte** y **desarrollar** el ERP. Está pensada para el servidor con CyberPanel, OpenLiteSpeed y AlmaLinux. El paso a paso completo de la instalación está en `INSTALAR-CYBERPANEL.md`.

> **Antes de copiar:** cambia lo que está en MAYÚSCULAS. Para no repetirlo en cada comando, al abrir la terminal pega esto con tus datos:
>
> ```bash
> export DOM=mi.agoradigital.app          # el dominio del sitio
> export SITIO=/home/$DOM                  # carpeta del sitio en CyberPanel
> export PHP=/usr/local/lsws/lsphp83/bin/php
> export USR=USUARIO_DEL_SITIO             # el dueño de los archivos (ls -l $SITIO lo muestra)
> export BD=NOMBRE_BASE BDU=USUARIO_BASE   # los de CyberPanel → Databases (con su prefijo)
> ```
>
> Los comandos que **cambian algo** llevan ⚠️. Haz un respaldo antes de usarlos.

---

## 1. Lo de todos los días (soporte)

| Para qué | Comando |
|---|---|
| **Revisar todo de una vez** (PHP, base, migraciones, cron, correo, subcuentas, errores) | `$PHP $SITIO/servidor/bin/diagnostico.php` |
| ¿La web responde y qué versión tiene? | `curl -s https://$DOM/api/health` |
| Correr el cron a mano y ver qué hizo | `sudo -u $USR $PHP $SITIO/servidor/bin/cron.php --once` |
| Ver la línea del cron | `crontab -l -u $USR` |
| Últimos errores del servidor web | `tail -n 50 /usr/local/lsws/logs/error.log` |
| Seguir los errores en vivo (Ctrl+C para salir) | `tail -f /usr/local/lsws/logs/error.log` |
| Errores de PHP del sitio | `tail -n 50 /usr/local/lsws/logs/stderr.log` |
| Espacio en disco | `df -h /home` |
| Qué ocupa más en el sitio | `du -sh $SITIO/* \| sort -h` |
| Reiniciar el servidor web | ⚠️ `systemctl restart lsws` |

`diagnostico.php` solo lee: no cambia nada y nunca muestra claves ni códigos. Cada línea sale en verde (bien), amarillo (revisar) o rojo (falla).

### Accesos y claves

| Para qué | Comando |
|---|---|
| Ponerle una clave nueva a alguien (también a un dueño) y desbloquearlo | ⚠️ `$PHP $SITIO/servidor/bin/cambiar-clave.php correo@empresa.com` |
| Quitar todos los bloqueos por «Demasiados intentos» | ⚠️ `$PHP $SITIO/servidor/bin/cambiar-clave.php --desbloquear` |
| Ver las cuentas de dueño (sin claves) | `$PHP $SITIO/servidor/bin/crear-dueno.php --listar` |
| Crear una cuenta de dueño (la primera es la Master) | ⚠️ `$PHP $SITIO/servidor/bin/crear-dueno.php` |

La clave de un dueño **solo** se cambia por terminal, nunca desde la web. Ágora nunca pone ni genera claves: las demás personas usan «¿Olvidaste tu clave?», o quien administra les manda desde Usuarios y roles «Enviar enlace para crear una clave nueva».

### Consultas rápidas a la base de datos (solo leen)

Entra con `mysql -u $BDU -p $BD` (pide la clave de la base) y pega la consulta:

```sql
-- Personas de una subcuenta, con su rol y si su invitación sigue pendiente
SELECT id,name,email,role,active,password_hash='!invitacion' AS invitacion_pendiente FROM agora_users WHERE local_tenant_id=2;
-- Subcuentas y su conexión con la cuenta
SELECT t.id,t.name,g.status,g.location_id,g.last_error,g.checked_at FROM agora_tenants t LEFT JOIN agora_ghl_connections g ON g.tenant_id=t.id;
-- Errores de las últimas 24 horas, los más repetidos
SELECT codigo,COUNT(*) n FROM agora_error_log WHERE created_at>=UTC_TIMESTAMP()-INTERVAL 1 DAY GROUP BY codigo ORDER BY n DESC LIMIT 10;
-- Accesos con código de hoy (quién, desde qué IP; nunca el código)
SELECT created_at,evento,correo,ip FROM agora_access_log WHERE created_at>=UTC_DATE() ORDER BY id DESC LIMIT 30;
-- Cuándo corrió el cron por última vez
SELECT data FROM agora_settings WHERE tenant_id=0 AND setting_key='cron.ultimo';
-- Migraciones aplicadas
SELECT version,applied_at FROM agora_schema ORDER BY version;
```

---

## 2. Instalar desde cero (base de datos nueva)

### 2.1 PHP y extensiones

```bash
dnf install -y lsphp83 lsphp83-common lsphp83-mysqlnd lsphp83-mbstring lsphp83-gd lsphp83-opcache lsphp83-process lsphp83-intl lsphp83-sodium
$PHP -v
$PHP -m | grep -Ei 'pdo_mysql|mbstring|gd|openssl|curl|sodium'   # deben salir todas
```

### 2.2 Base de datos nueva

Lo normal es crearla en CyberPanel → Databases → Create Database. Si prefieres la terminal:

```bash
mysql -u root -p
```
```sql
CREATE DATABASE NOMBRE_BASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'USUARIO_BASE'@'localhost' IDENTIFIED BY 'UNA-CLAVE-LARGA';
GRANT ALL PRIVILEGES ON NOMBRE_BASE.* TO 'USUARIO_BASE'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Para comprobar que entra: `mysql -u $BDU -p $BD -e "SELECT 1"`.

### 2.3 Archivos

```bash
cd $SITIO
sha256sum -c agora-new-app-X.Y.Z.zip.sha256          # debe decir «OK»
unzip -o agora-new-app-X.Y.Z.zip
chown -R $USR:$USR $SITIO/public_html $SITIO/servidor
find $SITIO/servidor -type d -exec chmod 750 {} \;
find $SITIO/servidor -type f -exec chmod 640 {} \;
chmod 770 $SITIO/servidor/storage
```

### 2.4 Configuración

```bash
$PHP -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'    # córrelo DOS veces: una para app_key, otra para vault_key
cd $SITIO/servidor/config && cp config.example.php config.php && chown $USR:$USR config.php && chmod 640 config.php
nano config.php    # app_url=https://$DOM, app_key, vault_key, db (name, user, password), env=production, cookie_secure=true
```

**Guarda `app_key` y `vault_key` en un lugar seguro y no los cambies nunca:** con ellos se cifran los tokens de las cuentas.

### 2.5 Tablas, dueño y cron

```bash
sudo -u $USR $PHP $SITIO/servidor/bin/migrate.php        # crea las tablas (también se crean solas al abrir la web)
$PHP $SITIO/servidor/bin/crear-dueno.php                  # la primera cuenta es la Master
crontab -u $USR -e                                        # agrega esta línea y guarda:
# * * * * * /usr/local/lsws/lsphp83/bin/php /home/mi.agoradigital.app/servidor/bin/cron.php >/dev/null 2>&1
systemctl restart lsws
$PHP $SITIO/servidor/bin/diagnostico.php                  # nada en rojo
curl -s https://$DOM/api/health
```

Si prefieres no usar la terminal para el cron: CyberPanel → Websites → tu sitio → Cron Jobs, con la misma línea.

### 2.6 SSL

CyberPanel → SSL → Manage SSL → elige el dominio → Issue SSL. El DNS del dominio debe apuntar ya al servidor. Para comprobarlo:

```bash
curl -sI https://$DOM | head -1                 # debe decir 200 o 302
echo | openssl s_client -connect $DOM:443 -servername $DOM 2>/dev/null | openssl x509 -noout -dates
```

---

## 3. Actualizar a una versión nueva

```bash
# 1. Respaldo (base + config + llave)
mysqldump -u $BDU -p --single-transaction $BD | gzip > /root/agora-$(date +%F-%H%M).sql.gz
cp $SITIO/servidor/config/config.php /root/config-$(date +%F).php
cp $SITIO/servidor/storage/motor-vault.key /root/motor-vault-$(date +%F).key 2>/dev/null

# 2. Subir y verificar el ZIP nuevo
cd $SITIO && sha256sum -c agora-new-app-X.Y.Z.zip.sha256

# 3. Extraer (no toca config.php ni storage/) y permisos
unzip -o agora-new-app-X.Y.Z.zip
chown -R $USR:$USR $SITIO/public_html $SITIO/servidor
find $SITIO/servidor -type d -exec chmod 750 {} \; ; find $SITIO/servidor -type f -exec chmod 640 {} \; ; chmod 770 $SITIO/servidor/storage

# 4. Migraciones y comprobación
sudo -u $USR $PHP $SITIO/servidor/bin/migrate.php
$PHP $SITIO/servidor/bin/diagnostico.php
curl -s https://$DOM/api/health                 # debe decir la versión nueva
```

Si el diagnóstico dice **«Migraciones cambiadas»** en rojo, se subió un archivo de migración distinto al aplicado: vuelve a subir el ZIP de la versión correcta. **Nunca edites un archivo de `migrations/` ya aplicado.**

---

## 4. Respaldos y restauración

| Para qué | Comando |
|---|---|
| Respaldar la base (comprimido, con fecha) | `mysqldump -u $BDU -p --single-transaction $BD \| gzip > /root/agora-$(date +%F).sql.gz` |
| Respaldar config y llave del motor | `tar czf /root/agora-llaves-$(date +%F).tgz -C $SITIO/servidor config/config.php storage/motor-vault.key` |
| Ver los respaldos | `ls -lh /root/agora-*` |
| Restaurar una base | ⚠️ `gunzip < /root/agora-FECHA.sql.gz \| mysql -u $BDU -p $BD` |
| Si al restaurar falla por `DEFINER` | `gunzip < ARCHIVO.sql.gz \| sed -E 's/DEFINER=\`[^\`]+\`@\`[^\`]+\`//g' \| mysql -u $BDU -p $BD` |
| Borrar respaldos de más de 30 días | ⚠️ `find /root -name 'agora-*.sql.gz' -mtime +30 -delete` |

Sin `config.php` (`app_key` y `vault_key`) y sin `motor-vault.key`, los tokens guardados no se pueden abrir: hay que volver a escribirlos en cada subcuenta.

---

## 5. Problemas frecuentes

| Qué pasa | Qué hacer |
|---|---|
| La web sale en blanco o «500» | `tail -n 50 /usr/local/lsws/logs/stderr.log` y `$PHP $SITIO/servidor/bin/diagnostico.php` |
| «Origen no autorizado» al entrar | `app_url` de `config.php` no coincide con la dirección del navegador (con o sin `www`, con `https://`) |
| Cambios que no se ven después de actualizar | `systemctl restart lsws`; en el navegador, Ctrl+Shift+R |
| Los mensajes nuevos no llegan / la campana no avisa | `crontab -l -u $USR` y `sudo -u $USR $PHP $SITIO/servidor/bin/cron.php --once` |
| No llegan los códigos ni las invitaciones | Agencia → Correo de Ágora → Probar. En el diagnóstico, la línea «Correo de Ágora» |
| Alguien quedó bloqueado por intentos | `$PHP $SITIO/servidor/bin/cambiar-clave.php --desbloquear` |
| «Disco lleno» | `df -h /home` y `du -sh $SITIO/servidor/storage/*`; se puede vaciar `storage/cache-vivo/` sin riesgo: `rm -f $SITIO/servidor/storage/cache-vivo/*` |
| La tasa BCV no se actualiza | `sudo -u $USR $PHP $SITIO/servidor/bin/worker.php` y revisa la línea «Tasa BCV» del diagnóstico |
| Un error con código (AG-…) | Agencia → Errores y reportes → Diccionario dice qué significa y qué hacer |

---

## 6. Desarrollo (en local, sin tocar el servidor)

### Probar el ERP en local

```bash
# MariaDB local, cuenta simulada y la API en :8090 (lo hace todo el guion del proyecto)
bash up-erp.sh
curl -s http://127.0.0.1:8090/api/health
# Una prueba (11 s de pausa y limpiar los límites entre una y otra)
mysql -S /tmp/mdb/run/mysql.sock -uroot agora_erp -e "delete from agora_rate_limits"
python3 "Agora New App/tests/fixes-2220.py"
```

### Antes de entregar

```bash
cd "Agora New App"
rm -f servidor/config/config.php tests/fake-ghl.log   # nunca van al repositorio ni al ZIP
python3 tools/package.py                              # revisa secretos y arma el ZIP + .sha256
python3 tools/make-manual.py                          # vuelve a generar el manual PDF (con la API local arriba)
```

### Git

| Para qué | Comando |
|---|---|
| Qué cambió | `git status` · `git diff --stat` |
| Revisar que no vaya ningún secreto | `git diff --cached \| grep -iE "pit-\|password\|app_key\|vault_key"` |
| Guardar y subir | `git add -A && git commit -m "ERP X.Y.Z: …" && git push -u origin RAMA && git push origin RAMA:main` |
| Ver las últimas versiones | `git log --oneline -10` |

### Procesos

| Para qué | Comando |
|---|---|
| Buscar un proceso | `pgrep -fa "fake-ghl.py"` |
| Pararlo | `kill PID` (nunca `pkill -f`) |
| Ver qué escucha en un puerto | `ss -ltnp \| grep 8090` |

---

## 7. Lo que nunca se hace

- Cambiar `app_key` o `vault_key` en un servidor que ya tiene datos.
- Editar un archivo de `servidor/app/database/migrations/` que ya se aplicó.
- Subir `config.php`, `config/local.php`, `servidor/storage/` o tokens (`pit-…`) a Git.
- Tocar el servidor de Ágora Mobile (nueva.agoradigital.app) desde estas instrucciones.
- Usar `pkill -f` o Docker.
