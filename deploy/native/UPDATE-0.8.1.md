# Actualizar Ágora a 0.8.1

Esta versión sustituye por completo el envío SMTP de los códigos de acceso del equipo por el servicio de correo de una cuenta conectada. Ágora deja de necesitar servidor de correo propio, buzón y contraseña de buzón.

1. Extraer `agora-update-0.8.1.zip` sobre las carpetas existentes. El paquete no contiene `local.php`, claves ni contraseñas.
2. Entrar con el administrador, abrir **Conexiones** y pulsar **Actualizar base de datos**. La migración es idempotente: puede ejecutarse de nuevo sin efecto.
3. En la misma pantalla, en **Canal de acceso del equipo**, elegir la cuenta conectada que entregará los códigos. Sólo aparecen seleccionables las cuentas ya comprobadas; si la cuenta aún no está comprobada, pulsar antes **Comprobar conexión**.
4. Pulsar **Probar canal de acceso**. La prueba se entrega al correo del administrador; revisar también la carpeta de spam.
5. Probar el acceso de un usuario sincronizado desde la pantalla de entrada del equipo.

## Permisos necesarios en la cuenta elegida

La clave de esa cuenta necesita `contacts.readonly`, `contacts.write` y `conversations/message.write`, además del servicio de correo activo en la propia cuenta. Si falta alguno, Ágora informa de permisos insuficientes y no desactiva ni la conexión ni la bandeja: las conversaciones siguen funcionando con los permisos que ya tenía.

Para cada integrante, Ágora busca su dirección en la cuenta elegida, reutiliza la ficha que encuentre y, si no existe, crea una ficha interna marcada con la etiqueta `agora-acceso-interno`. La relación entre integrante, cuenta y ficha queda guardada, así que las entregas siguientes no crean fichas nuevas. Dos direcciones distintas no pueden compartir una misma ficha: si ocurre, Ágora rechaza la entrega y pide una dirección propia para cada persona.

## Limpieza de la configuración SMTP

Ya no se leen `LOGIN_MAIL_FROM` ni `SMTP_*`. Si `agora-private/config/local.php` todavía las define, la instalación sigue funcionando sin cambios: simplemente se ignoran. Se pueden borrar esas líneas cuando convenga, y conviene hacerlo para no dejar la contraseña del buzón almacenada sin uso. El paquete ya no incluye `agora-private/src/LoginMailer.php`; el archivo que quedara de la versión anterior no se utiliza y puede borrarse. El botón **Probar correo** ya no existe; su lugar lo ocupa **Probar canal de acceso**.

## Recuperación

El propietario conserva su acceso nativo con correo y contraseña, que no depende del canal de acceso. Si el canal queda sin elegir, no está comprobado o su correo falla, el equipo no podrá pedir códigos, pero el administrador siempre puede entrar, revisar **Conexiones** y volver a elegir o comprobar la cuenta.

## Diagnóstico

Los mensajes de error no contienen credenciales ni nombran al proveedor subyacente. Los códigos que puede devolver la prueba son: `access_channel_unconfigured` (no hay cuenta elegida), `access_channel_unverified` (la cuenta elegida no está comprobada), `member_without_email` (el integrante no tiene correo sincronizado), `contact_unresolvable` (no se pudo preparar la ficha interna), `shared_access_contact` (esa dirección comparte ficha con otro integrante), `insufficient_permissions` (faltan permisos de contactos o de mensajes), `email_channel_unavailable` (la cuenta no tiene el correo disponible), `delivery_rejected` (el envío fue rechazado) y `delivery_paused` (límite de peticiones; esperar los segundos indicados).
