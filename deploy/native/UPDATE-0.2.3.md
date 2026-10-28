# Actualización Ágora 0.2.3 · acceso directo independiente del menú

Esta versión evita mezclar HTML nuevo con módulos JavaScript de una versión anterior. El paquete utiliza rutas nuevas e inmutables para todos los scripts y estilos, y contiene una entrada directa de Conexiones que no requiere reglas rewrite.

1. Subir y extraer `agora-update-0.2.3.zip` en el directorio del sitio que contiene public_html. Aceptar reemplazar archivos de código. No extraer dentro de public_html: las dos carpetas del ZIP deben coincidir con las del sitio.
2. Abrir **https://nueva.agoradigital.app/conexiones.html**. Es un archivo real, no depende del menú ni de la ruta /conexiones. Si hay sesión válida de administrador, abre las conexiones; si no, muestra Iniciar sesión.
3. Tras entrar, el recuadro de login se cierra y se mantiene Conexiones. Si falta preparar protección o actualizar tablas, la pantalla muestra el paso correspondiente. Si hay otro error, Copiar resultado comparte sólo códigos seguros.
4. Para confirmar qué paquete sirve el sitio, abrir **https://nueva.agoradigital.app/version.json**. Debe indicar version 0.2.3 y un identificador build, sin credenciales. Si el archivo no existe o devuelve otra versión, la copia nueva no se está sirviendo desde ese directorio.

El ZIP conserva config/local.php, la carpeta var y los datos. No reemplaza claves ni importa el esquema base. No hace falta borrar los datos del navegador para probar la entrada nueva. Nunca introducir tokens en el login: ahí sólo van el email y la contraseña de Ágora.

Las rutas /conexiones y /#conexiones siguen disponibles cuando el sitio sirve la versión nueva. Una copia antigua de la página principal puede seguir apareciendo si existe caché de páginas en el alojamiento; la entrada conexiones.html y los scripts con rutas únicas permiten comprobar el paquete de forma independiente. El comportamiento real del VPS debe verificarse después de subirlo.
