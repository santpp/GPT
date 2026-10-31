# Actualizar Ágora a 0.25.0

La clave de revisión exige tu contraseña y viaja en una cabecera, y hay copia de seguridad diaria.

## Pasos

1. Extraer `agora-update-0.25.0.zip` sobre las carpetas existentes. No hay cambios de base de datos.
2. **Nada se rompe:** la dirección de revisión que tienes ahora en cron-job.org sigue funcionando. Cambiarla a la forma nueva (punto 3) es recomendable, no obligatorio.
3. **Revisión desde fuera, forma nueva** (Conexiones → Cambiar la clave):
   1. Ágora pide **tu contraseña** antes de crear la clave.
   2. Muestra, una sola vez, tres datos: la **dirección** (`https://nueva.agoradigital.app/api/cron`), el **nombre de la cabecera** (`X-Agora-Key`) y la **clave**.
   3. En cron-job.org, edita la tarea: pon la dirección nueva y, en **Avanzado → Cabeceras**, añade `X-Agora-Key` con la clave como valor. Guarda.
   4. La anterior deja de funcionar en cuanto creas la nueva.
4. **Copia de seguridad diaria:**
   1. En `agora-private/config/local.php`, añade una frase de al menos 12 caracteres y apúntala en un sitio seguro fuera del servidor:
      ```php
      'BACKUP_PASSPHRASE'=>'una frase larga que sólo tú conozcas',
      ```
   2. En CyberPanel → Cron Jobs, añade una tarea diaria (por ejemplo, a las 3:15):
      ```
      15 3 * * * php /home/TU_DOMINIO/agora-private/bin/backup.php
      ```
   3. Las copias quedan en `agora-private/backups/`, las 14 últimas. Conviene descargar de vez en cuando la más reciente y guardarla fuera del servidor.

## Revisión desde fuera

- Crear o cambiar la clave pide otra vez la contraseña del administrador, con un límite de 5 intentos cada 15 minutos. Una sesión abierta y olvidada en otro aparato no basta.
- La clave viaja en la cabecera `X-Agora-Key` (también se acepta `Authorization: Bearer …`). No queda escrita en la dirección, ni en registros de servidores ni en historiales.
- Para servicios de cron que no admiten cabeceras sigue existiendo la dirección con la clave dentro, marcada como alternativa menos privada.
- El panel muestra el **último uso** de la clave y de qué forma, para notar un uso que no esperas.

## Copias de seguridad

- **Con frase:** la copia (`.agora-backup`) va cifrada con AES-256-GCM e incluye la base de datos, las claves de `var/` (`integration.key`, `webpush.json` y la de Firebase si existe) y `config/local.php`. Sin la frase no se puede leer, y una copia dañada se detecta.
- **Sin frase:** solo la base de datos, en `.zip`. Las claves nunca se copian sin cifrar. Sin `integration.key`, los tokens guardados no se pueden abrir, así que guárdala aparte.
- **Qué se deja fuera:** las cachés y los registros de vida corta, porque la revisión de cada minuto los rehace sola.
- **Conexiones** muestra la fecha de la última copia y si incluye las claves. Avisa en amarillo si pasan más de dos días sin copia o si va sin cifrar.
- **Restaurar:**
  1. `php agora-private/bin/open-backup.php COPIA.agora-backup copia.zip` (pide la frase).
  2. Dentro: `database.sql` se importa con `mysql -u USUARIO -p BASE < database.sql`; `private/` va a `agora-private/var` y `agora-private/config`. Lo explica también el `LEEME.txt` de la copia.
