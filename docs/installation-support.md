# Guardado de conexiones · revisión 0.2.1

El usuario reportó un 503 genérico al guardar una conexión en su VPS. La captura corresponde a una interfaz anterior. No hay acceso SSH ni sesión administradora del VPS configurados en este entorno y **no se ha confirmado la causa exacta allí**.

El guardado sólo valida, cifra y persiste: no llama a la API remota. La revisión anterior agrupaba falta de clave, Sodium, permisos y errores de SQL en Service unavailable. Además, el generador nativo aceptaba un path por defecto y la bóveda exigía APP_KEY_FILE explícito. Ahora comparten la misma ruta por defecto; una ruta explícita no válida sigue dando error, nunca se cambia en silencio.

## Correcciones

- SetupException expone códigos concretos y mensajes seguros, sin rutas, valores, SQL ni cuerpos remotos. Errores no previstos llevan una referencia correlacionable en el log, con clase y SQLSTATE únicamente.
- GET /api/admin/diagnostics revisa runtime, dominio, clave, capacidad de cifrado y tablas/columnas. Requiere administrador; no contacta con el proveedor.
- El formulario pide la credencial sólo después de la revisión. Los fallos ya no indican introducirla repetidamente sin corregir la instalación.
- POST /api/admin/installation/migrations permite aplicar exclusivamente las migraciones adicionales 002/003 (CREATE IF NOT EXISTS) con autenticación, origen y CSRF. No reinicializa el esquema base.
- POST /api/admin/installation/encryption prepara la clave sólo en una instalación sin conexiones guardadas, fuera de public_html/public y sin sustituir un archivo existente. Publica un archivo privado con modo 600 de forma atómica. Una clave ilegible o inválida no se regenera; con conexiones existentes exige recuperar la original.
- Copiar resultado comparte únicamente versión y códigos de diagnóstico. Si el problema es un propietario incorrecto o un módulo no instalado, requiere intervención del alojamiento; no se oculta ni se utilizan permisos 777.
- Interfaz Ágora sin menciones al proveedor en textos visibles. Navegación administrativa oculta para vendedores/supervisores y validada también por backend. Las nuevas llamadas del frontend usan /api/workspace/*; se conservan los aliases antiguos sólo en backend para compatibilidad.
- Service worker v4 descarga el shell con cache reload y ofrece actualización explícita sin interrumpir formularios. No elimina datos de usuario ni guarda credenciales en caché.

## Comprobado

41 checks HTTP/MariaDB de integración; 9 checks aislados de instalación; 8 checks HTTP de instalación sin clave y recuperación administradora; 17 de sincronización; Chromium para texto visible, roles, formulario y reparación inicial. No son una validación del VPS. Los fixtures de recuperación HTTP sólo deben ejecutarse en el contenedor de prueba con APP_KEY_FILE=/tmp/agora-http-setup-test.key y una base sin conexiones reales.

El ZIP de actualización omite íntegramente config y var, incluyendo entradas de directorio, y no incluye el esquema base. El usuario actualiza código y usa la revisión dentro de la app. El ZIP de instalación completa sigue disponible para instalaciones nuevas.
