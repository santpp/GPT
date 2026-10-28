# Actualización Ágora 0.2.1 · sin terminal

Esta actualización mejora el guardado de conexiones y elimina referencias al proveedor de la interfaz visible. No instala Docker ni cambia el sitio, el certificado o la base de datos al extraer archivos.

1. Descargar `agora-update-0.2.1.zip` desde la carpeta releases de GitHub.
2. En el administrador de archivos/SFTP, abrir el directorio del sitio que **contiene public_html**, habitualmente `/home/nueva.agoradigital.app/`. Si la ruta real es otra, usar la del sitio existente.
3. Hacer copia de los archivos de aplicación antes de reemplazarlos. Subir el ZIP y extraerlo ahí, aceptando sustituir los archivos de código. **No extraer dentro de public_html.**
4. Este ZIP no contiene `agora-private/config/` ni `agora-private/var/`, ni siquiera entradas vacías de esas carpetas. Conserva `config/local.php`, `var/integration.key`, el propietario del sitio y los datos de MySQL. No borrar carpetas privadas ni volver a importar el esquema base.
5. Abrir la app. Si aparece **Actualizar**, pulsar el botón y confirmar cuando no haya formularios o borradores pendientes. La versión nueva muestra `v0.2.1` al pie. Si no aparece, cerrar y volver a abrir la app o recargarla; comprobar que los archivos del sitio se actualizaron y que no hay caché de páginas del alojamiento sirviendo una copia anterior.
6. Entrar con el administrador y abrir **Conexiones**. La revisión de instalación se ejecuta antes de pedir claves.
7. Si aparece **Actualizar base de datos**, pulsarlo. Sólo aplica las tablas adicionales previstas; no importa el esquema inicial ni elimina datos.
8. Si aparece **Preparar protección inicial**, pulsarlo. Sólo se permite si no hay ninguna conexión guardada y no existe una clave utilizable. No reemplaza claves existentes o necesarias para recuperar conexiones.
9. Cuando la revisión esté correcta, añadir la cuenta. Si aparece otro problema, pulsar **Copiar resultado** y compartir ese resumen con soporte. El resumen sólo contiene versión y códigos; nunca claves, contraseñas, tokens o rutas privadas.

Los controles de administración sólo son visibles y utilizables para administradores. No hay instalador público sin autenticación. Si la clave existe pero PHP no puede leerla, el sistema lo indica y no cambia permisos ni crea otra a escondidas: el propietario del sitio/alojamiento debe corregir ese acceso. No usar permisos 777.

La reparación local se ha probado con una instalación sin clave. La causa concreta del VPS debe confirmarse mediante su revisión; no se da por solucionada remotamente hasta comprobarlo en la web real.
