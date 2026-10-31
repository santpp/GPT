# Actualizar Ágora a 0.23.0

La sesión dura 60 días en cada dispositivo, se puede entrar con la huella, Face ID o el PIN del teléfono, y hay un bloqueo opcional al volver a la app.

## Pasos

1. Extraer `agora-update-0.23.0.zip` sobre las carpetas existentes.
2. Nada más en el servidor. La base de datos se actualiza sola en la siguiente revisión (cron o dirección externa), o al abrir Conexiones. Añade dos tablas, `device_sessions` y `passkeys`, sin tocar las existentes.
3. En el teléfono, cerrar Ágora del todo y volver a abrirla. El pie debe decir **v0.23.0**.
4. Entrar una vez con el correo y el código, o con la contraseña. Ágora ofrecerá «Entra con tu huella o tu cara». Pulsar **Activar**.

Requisito que ya se cumple: `APP_ORIGIN` es la dirección `https` exacta de Ágora. Las llaves de acceso quedan ligadas a ese dominio. Si algún día cambia el dominio, cada persona tendrá que volver a activarlas.

## Sesión de 60 días

- Al entrar, el dispositivo queda recordado. Ya no hace falta volver a entrar cada vez que se cierra la app o pasa un rato.
- Cada uso alarga los 60 días. Un dispositivo que no se usa en 60 días tendrá que volver a entrar.
- La cookie no la puede leer la página, solo viaja por HTTPS y nunca se manda desde otras webs. En la base de datos solo queda la huella SHA-256 de su secreto, que cambia una vez al día.
- **Cerrar sesión** en un dispositivo lo olvida del todo.
- Si se da de baja a alguien en la cuenta, deja de entrar aunque tenga la sesión recordada.

## Huella, Face ID o PIN

- Funciona en iPhone (iOS 16 o posterior) y en Android (9 o posterior, con Chrome), desde la app instalada o desde el navegador. También en ordenadores con Touch ID o Windows Hello.
- La huella o la cara nunca salen del teléfono. Ágora guarda solo la parte pública de la llave y comprueba una firma. Una web falsa no obtiene una firma válida para Ágora.
- En la pantalla de acceso de un dispositivo que ya tiene llave sale **Entrar con huella o Face ID**. No hay que escribir el correo.
- Los usuarios que entran con código por correo pueden activarla igual. A partir de ahí, la llave sustituye al código.
- El APK de Android carga la web, pero el visor que usa no siempre admite llaves de acceso. Allí sigue funcionando la sesión de 60 días; para la huella, mejor la app instalada desde Chrome.

## Bloqueo al volver (opcional)

- En **Mi cuenta → Seguridad y sesión**: «Pedir la huella o la cara al volver», tras 5, 15, 30 o 60 minutos sin usar la app. Por defecto, nunca.
- Lo decide el servidor: mientras la app está bloqueada, no devuelve ningún dato hasta que se desbloquea con una llave de esa misma persona.
- Si se quitan todas las llaves, el bloqueo se apaga solo, para que nadie se quede fuera.

## Seguridad y sesión

Desde el círculo con las iniciales (arriba a la derecha en el teléfono; abajo a la izquierda en el ordenador):

- activar la huella o Face ID en este dispositivo;
- elegir el bloqueo;
- ver las llaves de acceso y quitar la de un teléfono perdido;
- ver los dispositivos con sesión abierta y cerrar cualquiera desde otro dispositivo; se cierra en su siguiente petición;
- el administrador ve también los dispositivos y llaves del equipo y puede cerrarlos;
- **Cerrar sesión en este dispositivo**.

## Contraseñas

Ágora no guarda contraseñas en el teléfono. Los campos de acceso están marcados para que el llavero de iCloud o el gestor de Google los guarden y rellenen.
