# Actualización Ágora 0.2.2

Incluye la corrección de instalación 0.2.1 y añade enlaces directos a las pantallas.

1. Extraer `agora-update-0.2.2.zip` en el directorio del sitio que contiene public_html, aceptando reemplazar archivos de código. No contiene config/local.php ni la carpeta var, ni reinicializa datos.
2. Abrir la app y aceptar Actualizar cuando no haya formularios pendientes. La versión al pie será v0.2.2.
3. Abrir `https://nueva.agoradigital.app/conexiones`. Si no hay sesión aparecerá Iniciar sesión. Después de entrar como administrador se mantiene esta pantalla.
4. Si el servidor aún no aplica las nuevas reglas de rutas, usar `https://nueva.agoradigital.app/#conexiones`, que entra por la raíz y selecciona la misma pantalla.
5. Completar los pasos que aparezcan: Actualizar base de datos o Preparar protección inicial, si son necesarios. Si hay otro aviso, Copiar resultado permite compartir sólo versión y códigos.

La bandeja conectada está en `/bandeja` o `/#bandeja`. La navegación administrativa sólo aparece para administradores; abrir una URL no concede permisos. La actualización no confirma por sí sola la instalación en el VPS: hay que subir los archivos y comprobar la web real.
