# Actualizar Ágora a 0.7.0

1. Extraer `agora-update-0.7.0.zip` sobre las carpetas existentes del sitio. El paquete no contiene `local.php`, claves ni datos de ejecución.
2. En `agora-private/config/local.php`, añadir antes de `];` la entrada `'LOGIN_MAIL_FROM'=>'acceso@nueva.agoradigital.app',` usando un remitente real del dominio. No compartir el archivo ni su contraseña de correo.
3. Entrar con el administrador, abrir **Conexiones** y pulsar **Actualizar base de datos**. La operación añade las tablas de identidad, membresías y códigos temporales; conserva conexiones, conversaciones y usuarios importados.
4. En cada cuenta, abrir **Equipo y permisos** y pulsar **Sincronizar equipo**.
5. Cerrar sesión y probar con el correo de un usuario importado. Debe recibir un código de seis cifras; caduca en diez minutos y sólo funciona una vez.

Si **Consultas programadas** está activado y el cron de Ágora ya se ejecuta cada minuto, el equipo y sus permisos se renovarán automáticamente cada hora. No hace falta crear una segunda tarea cron.

Ágora no valida ni almacena contraseñas de empleados. El correo, usuario activo, permisos, asignaciones y pertenencia se obtienen de la sincronización. Si un usuario desaparece de la cuenta, pierde acceso tras la siguiente sincronización.

El envío utiliza el transporte `mail()` de PHP 8.3. El remitente debe existir y el dominio debe tener su entrega de correo configurada. Si aparece `login_delivery_unavailable`, comprobar el servicio de correo del sitio y los registros de entrega de CyberPanel; no desactivar la verificación ni mostrar códigos en pantalla.

Los usuarios con `assignedDataOnly` sólo reciben las conversaciones cuyo campo `assignedTo` coincide con su identificador sincronizado. Los permisos se vuelven a evaluar antes de listar cuentas, mostrar bandeja, leer historial o responder. Sólo el administrador nativo puede abrir **Conexiones**.
