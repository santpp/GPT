# Actualizar Ágora a 0.19.0

Avisos de mensajes nuevos en el móvil **sin publicar ninguna aplicación**: Android e iPhone, instalando Ágora desde el navegador.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.19.0.zip` sobre las carpetas existentes.
2. En `config/local.php`, añadir: `'WEBPUSH_KEY_FILE'=>dirname(__DIR__).'/var/webpush.json'`.
3. Comprobar que el cron de un minuto está puesto, con el usuario del sitio:
   `*/1 * * * * php /ruta/agora-private/bin/sync-inbox.php`
   `*/1 * * * * php /ruta/agora-private/bin/send-push.php`
4. Entrar. La base de datos se actualiza sola y las claves de avisos se crean solas la primera vez que alguien activa los avisos.

No hace falta Firebase, ni cuenta de Google, ni APK, ni permisos nuevos en la clave de la cuenta.

## El arreglo importante

Hasta ahora el pase que descubre mensajes nuevos lo disparaba el navegador. Con todas las aplicaciones cerradas —justo cuando hace falta un aviso— nadie leía la cuenta y no había nada que notificar. Ahora ese mismo pase corre también desde el cron de cada minuto. Por eso el paso 3 es obligatorio: sin ese cron no hay avisos.

## Activar los avisos en un dispositivo

Arriba a la derecha hay un botón **Activar avisos**. Cada persona lo pulsa en su propio teléfono.

- **Android**: instalar Ágora desde el menú del navegador («Instalar aplicación»), abrirla y pulsar el botón. También funciona sin instalar, pero instalada es más fiable.
- **iPhone (iOS 16.4 o posterior)**: abrir la web **en Safari** → Compartir → *Añadir a pantalla de inicio*. Abrir Ágora **desde ese icono** y pulsar el botón. Esto es obligatorio: Apple no permite avisos en una pestaña normal, y el botón lo explica en lugar de pedir el permiso, porque en iPhone un permiso denegado no se puede volver a pedir.

El botón dice en todo momento qué pasa: activados, bloqueados en los ajustes, o que falta instalar. Volver a pulsarlo desactiva los avisos en ese dispositivo.

## Qué llega y qué no se filtra

El aviso lleva el nombre del contacto y la vista previa del mensaje, y al tocarlo abre esa conversación. Va **cifrado para ese dispositivo**: el servicio que lo reenvía (Google, Apple o Mozilla) no puede leer ni el nombre ni el texto. Quién recibe cada aviso sigue la regla de siempre: tú todo lo de tu espacio, un integrante sin restricción lo de su cuenta, y uno limitado a sus datos sólo lo asignado.

## Diagnóstico

En **Conexiones**, al final, hay una lista de dispositivos: qué es cada uno, cuándo se registró, cuándo se le vio y **cuándo se le envió el último aviso**. Si alguien dice que no le llegan, eso distingue en un vistazo entre «no se envió» y «se envió y el teléfono no lo mostró».

## Lo que no he podido probar

El cifrado y la firma están comprobados de extremo a extremo: la prueba automática descifra el aviso como lo haría el navegador y verifica la firma. Lo que **no** se ha probado es la entrega real a un servicio y a un teléfono, porque este entorno no tiene salida a ellos.

Cuando lo despliegues, la comprobación es esta:

1. Instala Ágora en un Android y en un iPhone (16.4+), activa los avisos en ambos.
2. Bloquea los dos teléfonos.
3. Manda un WhatsApp a la cuenta desde otro número.
4. Mira cuánto tarda cada aviso y que al tocarlo abra esa conversación.
5. Repite con los teléfonos media hora inactivos, que es donde aparecen los retrasos de Android.

Si algo falla, lo primero que hay que mirar es la lista de dispositivos de *Conexiones*.
