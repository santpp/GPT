# Actualizar Ágora a 0.18.0

Tres cosas: la bandeja deja de estar limitada a una ventana de 20, 50 o 100; las pantallas se actualizan en vivo sin que nadie pulse nada y **gastando menos** peticiones de la cuenta que antes; y hay aplicación Android con notificaciones.

El paquete es acumulativo: incluye todo lo publicado desde 0.8.1.

## Pasos

1. Extraer `agora-update-0.18.0.zip` sobre las carpetas existentes.
2. Entrar. La base de datos y la aplicación se actualizan solas.
3. Opcional, sólo si quieres notificaciones en Android: los pasos de «Notificaciones» más abajo.

No hay permisos nuevos en la clave de la cuenta.

## La ventana desaparece

Al final de la lista aparece **Cargar más conversaciones**: continúa después de la última que haya, usando la posición que la propia cuenta da. Se puede seguir cargando hasta el final. Los filtros y la búsqueda siguen resolviéndose en la cuenta, así que también se continúan.

Hay un detalle honesto que conviene saber: la cuenta ordena por la fecha del último mensaje, y si dos conversaciones comparten exactamente esa fecha la página siguiente podría repetirlas. Ágora compara con lo que ya tiene, no repite ninguna, y si una página no aporta nada nuevo cierra la lista ahí en lugar de pedir lo mismo otra vez.

## En vivo, y más barato

Antes, el chat abierto preguntaba a la cuenta cada 10 segundos **por cada navegador**, y la lista se actualizaba en pasadas largas. Ahora:

- El navegador pregunta **a Ágora** cada 5 segundos. Eso no consume nada de la cuenta.
- La cuenta la lee **un solo pase compartido por subcuenta**, cada 10 segundos como mínimo, lo pida quien lo pida. Diez personas sobre la misma subcuenta cuestan lo que una.
- Cuando algo cambia de verdad (mensaje nuevo, conversación marcada como leída, cambio de responsable), la lista, el chat abierto y el contador del menú se actualizan solos en unos segundos. Si no cambia nada, no se recarga nada.

Resultado: los mensajes nuevos aparecen en 10–15 segundos sin tocar nada, y el gasto de peticiones baja en lugar de subir. Lo que una persona marca como leído desaparece de los pendientes de las demás sin que nadie recargue.

Si el presupuesto de peticiones entra en pausa, la barra lo dice y se reanuda solo.

## Android

El proyecto está en `mobile/` del repositorio, con sus instrucciones en `mobile/README.md`. El APK **no lleva la aplicación dentro**: abre el sitio desplegado, así que cada actualización del servidor llega al teléfono sin publicar una versión nueva en Play. Lo nativo aporta icono propio, notificaciones del sistema y apertura directa de la conversación al tocar un aviso.

### Notificaciones

1. En Firebase, crea el proyecto y añade una app Android con el identificador `app.agoradigital.workspace`; descarga `google-services.json` y colócalo en `mobile/android/app/` (no se versiona).
2. En el mismo proyecto de Firebase, crea una cuenta de servicio con permiso de *Firebase Cloud Messaging API*, descarga su JSON y **súbelo fuera de `public_html`**, por ejemplo a `agora-private/var/fcm-service-account.json`.
3. En `config/local.php` añade `'FCM_CREDENTIALS_FILE'=>dirname(__DIR__).'/var/fcm-service-account.json'`.
4. Añade el cron, con el usuario del sitio: `*/1 * * * * php /ruta/agora-private/bin/send-push.php`.
5. Compila el APK donde tengas el SDK de Android: `cd mobile && npm install && npx cap sync android && cd android && ./gradlew assembleRelease`.

Quién recibe cada aviso sigue la misma regla que las pantallas: tú recibes todo lo de tu espacio, un integrante sin restricción lo de su cuenta, y un integrante limitado a sus datos sólo lo que tiene asignado. El mismo mensaje no se notifica dos veces. Un teléfono que el servicio ya no reconoce (app desinstalada) se olvida solo. Cerrar sesión en un teléfono deja de notificarlo.

Sin los pasos 2 y 3, Ágora simplemente no notifica: no falla nada más.

## Lo que no he podido probar

La entrega de notificaciones está probada contra un servicio simulado, con la forma de petición que documenta Firebase y con una firma real. **No está probada contra Firebase ni contra un teléfono**, porque este entorno no tiene acceso a Google ni el SDK de Android. Tampoco he compilado el APK por el mismo motivo. Cuando tengas el proyecto de Firebase creado, la prueba de extremo a extremo es corta y te digo exactamente qué mirar.

## Diagnóstico

`pulse_failed` si la comprobación de novedades falla. La barra de estado dice «en vivo», «en espera para respetar la cuota» o «reintentando». Para las notificaciones, el cron imprime cuántos avisos había, cuántos se enviaron, cuántos se reintentan y cuántos dispositivos se olvidaron; nunca imprime credenciales, tokens ni el texto de los mensajes.
