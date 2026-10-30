# Ágora para Android

Envoltorio Capacitor de la aplicación web. El APK **no lleva la aplicación dentro**: abre `https://nueva.agoradigital.app`, de modo que cada despliegue del servidor llega al teléfono sin publicar una versión nueva en Play. Lo nativo aporta tres cosas: icono y pantalla de inicio propios, notificaciones del sistema y apertura directa de la conversación al tocar un aviso.

Fuera de línea lo cubre el service worker de la propia web, que funciona dentro del WebView; `www/` sólo contiene la pantalla que se ve si el teléfono no tiene red en el primer arranque.

## Requisitos

- Node 20 o posterior y `npm install` en esta carpeta.
- Android Studio con el SDK de Android (API 34) y JDK 17 o posterior.
- Un proyecto de Firebase con la app Android `app.agoradigital.workspace`.

Este repositorio **no** incluye el SDK de Android: el APK se compila en una máquina que lo tenga.

## Notificaciones

1. En la consola de Firebase, añade una app Android con el identificador `app.agoradigital.workspace` y descarga `google-services.json`.
2. Copia ese archivo en `android/app/google-services.json`. No se versiona: está en `.gitignore`.
3. En el servidor, genera una cuenta de servicio del mismo proyecto (rol *Firebase Cloud Messaging API Admin*), guarda el JSON **fuera de `public_html`** y apunta `FCM_CREDENTIALS_FILE` a esa ruta en `config/local.php`.
4. Añade el cron `*/1 * * * * php /ruta/agora-private/bin/send-push.php` con el usuario del sitio.

Sin el paso 3 Ágora no envía nada y lo dice en el diagnóstico; sin el paso 2 la app no obtiene identificador de dispositivo y no se registra. Ninguna credencial viaja al navegador ni entra en Git.

## Compilar

```bash
npm install
npx cap sync android
cd android && ./gradlew assembleRelease      # APK sin firmar
```

El APK queda en `android/app/build/outputs/apk/release/`. Para publicar en Play hay que firmarlo con tu propio almacén de claves (`*.keystore`, también fuera de Git) y subir un `bundleRelease` (AAB).

Para probar en un teléfono conectado: `cd android && ./gradlew installDebug`.

## Qué se ha comprobado aquí y qué no

- La estructura del proyecto, la configuración, el manifiesto con el permiso de notificaciones de Android 13 y la sincronización de Capacitor: comprobados.
- La compilación del APK y una notificación real llegando a un teléfono: **no**, porque este entorno no tiene el SDK de Android ni acceso a Firebase. La entrega está probada contra un servicio simulado, con la forma de petición que documenta FCM HTTP v1.
