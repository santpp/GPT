# Actualizar Ágora a 0.8.0

Esta versión reemplaza el envío mediante `mail()` por SMTP autenticado y añade la ficha real del contacto dentro del chat.

1. Extraer `agora-update-0.8.0.zip` sobre las carpetas existentes. El paquete no contiene `local.php`, claves ni contraseñas.
2. Abrir en CyberPanel la configuración de cliente del buzón creado y anotar localmente servidor SMTP, puerto y tipo de cifrado. No enviar la contraseña por chat.
3. Añadir estas entradas antes de `];` en `agora-private/config/local.php`:

```php
'LOGIN_MAIL_FROM'=>'acceso@nueva.agoradigital.app',
'SMTP_HOST'=>'SERVIDOR_MOSTRADO_POR_CYBERPANEL',
'SMTP_PORT'=>'465',
'SMTP_USERNAME'=>'acceso@nueva.agoradigital.app',
'SMTP_PASSWORD'=>'CONTRASEÑA_DEL_BUZÓN',
'SMTP_ENCRYPTION'=>'tls',
```

Usar `tls` con el puerto 465. Si la configuración del proveedor indica 587/STARTTLS, usar puerto `587` y `SMTP_ENCRYPTION'=>'starttls'`. Ágora rechaza SMTP sin cifrado. Se puede utilizar el SMTP de otro proveedor si el correo local del VPS no entrega correctamente.

4. Entrar con el administrador, abrir **Conexiones** y pulsar **Actualizar base de datos**.
5. En esa pantalla, pulsar **Probar correo**. La confirmación significa que el servidor SMTP aceptó el mensaje; revisar también spam y los registros de entrega si no llega al destino.
6. Probar el acceso de un usuario sincronizado.

La ficha del contacto requiere `contacts.readonly`. Los nombres de campos personalizados requieren `locations/customFields.readonly`; si falta este último permiso, los valores siguen apareciendo con una etiqueta genérica. Las fichas se almacenan 60 segundos y los nombres de campos una hora para respetar las cuotas.

Los códigos de diagnóstico SMTP no contienen usuario ni contraseña. `smtp_connection_failed` indica host, puerto, DNS, firewall o certificado; `smtp_rejected_535` suele indicar credenciales; `smtp_rejected_550` suele indicar remitente o destinatario rechazado.
