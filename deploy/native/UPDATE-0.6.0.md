# Ágora 0.6.0 · equipo y permisos importados

1. Extraer `agora-update-0.6.0.zip` en la carpeta que contiene `public_html` y `agora-private`, reemplazando el código.
2. Abrir `/conexiones.html` con el administrador y pulsar **Actualizar base de datos**. La migración 006 crea las proyecciones de usuarios y vínculos de identidad; no modifica claves, conversaciones ni al administrador existente.
3. En cada conexión comprobada, abrir **Equipo y permisos** y pulsar **Sincronizar equipo**. La credencial necesita `users.readonly`.
4. `/version.json` debe indicar `0.6.0`.

El administrador de Ágora sigue siendo la única cuenta local. La importación guarda ID, email, nombre, rol, scopes, permisos y `assignedDataOnly` por conexión. Los ausentes se marcan inactivos; no se eliminan. Los usuarios importados nunca pueden configurar conexiones.

Esta versión no permite todavía iniciar sesión a los empleados: el token privado no verifica sus contraseñas. La tabla de vínculos queda preparada, pero antes de activarla hay que configurar un proveedor de identidad y vincularlo al usuario importado. Hasta entonces, `Sin acceso vinculado` es el estado esperado.

El endpoint de usuarios por ubicación está documentado como deprecado, pero es el contrato disponible con acceso de subcuenta. El reemplazo `/users/search` exige `companyId`; se migrará cuando Ágora pueda obtener y validar el ID de agencia.
