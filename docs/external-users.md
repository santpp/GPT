# Usuarios y permisos importados

Ágora mantiene una sola cuenta nativa: el administrador que configura conexiones y tareas técnicas. `bin/create-user.php` sólo crea ese primer administrador y rechaza cuentas locales adicionales. Los empleados no tienen contraseña en Ágora.

Cada conexión puede importar usuarios mediante `GET /users/?locationId=…`, Version `2021-07-28`, con scope `users.readonly`. Se usa este endpoint de ubicación porque funciona con acceso de subcuenta; la documentación lo marca deprecado y recomienda `/users/search`, pero ese reemplazo exige `companyId`. Antes de migrar al buscador se deberá obtener y validar el identificador de agencia correspondiente.

La proyección conserva ID externo, email, nombre, rol, permisos booleanos conocidos, scopes, `assignedDataOnly`, actividad y fecha. Los valores se validan antes de abrir la transacción. Una sincronización válida marca inactivos a los ausentes y reactiva los que reaparecen; no elimina identidades ni vínculos. Un payload inválido conserva el estado anterior.

`PermissionPolicy` traduce la proyección a capacidades de Ágora. Un usuario importado nunca recibe `configureConnections`. Conversaciones, contactos, oportunidades y calendario requieren que el módulo esté habilitado; cuando existe una lista de scopes, también exige un scope compatible. Escritura de mensajes requiere `conversations.write` o `conversations/message.write`. `assignedDataOnly` se conserva como restricción obligatoria para las futuras consultas.

Un token privado permite leer usuarios y permisos, pero no verificar su contraseña. El OAuth público documentado es un flujo de instalación y consentimiento para administradores; no es un proveedor general de inicio de sesión para vendedores. El contexto SSO con Shared Secret sólo se entrega a páginas ejecutadas dentro de la plataforma original. Ágora no trata ninguno de esos mecanismos como validación de contraseña.

La versión 0.7.0 usa el correo sincronizado como identidad y envía un código aleatorio de seis cifras, válido diez minutos y una sola vez. Sólo se emite cuando el correo aparece en un usuario activo. La respuesta de solicitud es idéntica para correos existentes e inexistentes, hay límites por dirección y correo, y cinco intentos por código. La sesión se vincula a `external_identities`; `external_identity_memberships` permite que una misma identidad tenga permisos distintos en cada cuenta conectada.

Cada petición vuelve a consultar las membresías activas. Si el usuario desaparece en la siguiente sincronización, su sesión deja de ser válida. `PermissionPolicy` se evalúa por conexión. `assignedDataOnly` filtra la bandeja y la autorización se repite antes de leer el historial o enviar. Conexiones y credenciales permanecen reservadas al administrador nativo.

Cuando las consultas programadas de una conexión están activadas, el mismo cron renueva el equipo y sus permisos cada hora. La consulta comparte bloqueo, presupuesto y pausas por cuota con la bandeja. Un fallo se difiere cinco minutos y se registra con un código seguro; no reactiva usuarios ni reemplaza la última proyección válida.

El servidor debe definir `LOGIN_MAIL_FROM` con un remitente del dominio y tener configurado el transporte de correo de PHP. Si la entrega no está disponible, el acceso de equipo devuelve un error operativo y no crea una sesión. `external_identity_links` se conserva para una futura identidad federada; no participa en el acceso por correo.
