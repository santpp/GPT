# Usuarios y permisos importados

Ágora mantiene una sola cuenta nativa: el administrador que configura conexiones y tareas técnicas. `bin/create-user.php` sólo crea ese primer administrador y rechaza cuentas locales adicionales. Los empleados no tienen contraseña en Ágora.

Cada conexión puede importar usuarios mediante `GET /users/?locationId=…`, Version `2021-07-28`, con scope `users.readonly`. Se usa este endpoint de ubicación porque funciona con acceso de subcuenta; la documentación lo marca deprecado y recomienda `/users/search`, pero ese reemplazo exige `companyId`. Antes de migrar al buscador se deberá obtener y validar el identificador de agencia correspondiente.

La proyección conserva ID externo, email, nombre, rol, permisos booleanos conocidos, scopes, `assignedDataOnly`, actividad y fecha. Los valores se validan antes de abrir la transacción. Una sincronización válida marca inactivos a los ausentes y reactiva los que reaparecen; no elimina identidades ni vínculos. Un payload inválido conserva el estado anterior.

`PermissionPolicy` traduce la proyección a capacidades de Ágora. Un usuario importado nunca recibe `configureConnections`. Conversaciones, contactos, oportunidades y calendario requieren que el módulo esté habilitado; cuando existe una lista de scopes, también exige un scope compatible. Escritura de mensajes requiere `conversations.write` o `conversations/message.write`. `assignedDataOnly` se conserva como restricción obligatoria para las futuras consultas.

La importación no autentica al empleado. Un PIT permite leer usuarios y permisos, pero no verificar su contraseña. `external_identity_links` está preparada para vincular una identidad verificada de Google, Microsoft u otro proveedor con el usuario importado. Hasta elegir y configurar ese proveedor, `authenticationConfigured` permanece falso y ningún empleado importado puede iniciar sesión. Una vinculación futura deberá exigir usuario activo, email/subject verificado, pertenencia a la conexión y reevaluación de permisos en cada sesión.
