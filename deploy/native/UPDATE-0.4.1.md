# Ágora 0.4.1 · pantalla de trabajo y actualización automática

1. Extraer agora-update-0.4.1.zip en la carpeta que contiene public_html y agora-private, reemplazando el código.
2. Abrir /conexiones.html y después Conversaciones. /version.json debe indicar 0.4.1.
3. No hay nuevas tablas respecto de 0.4.0. Si se viene de una versión anterior y aparece Actualizar base de datos en Conexiones, pulsarlo.

Se eliminan la cabecera promocional y el pie. La bandeja ocupa el alto disponible; lista e historial tienen desplazamiento independiente y el formulario permanece en el área de chat.

La pantalla abierta actualiza al intervalo de la cuenta, mínimo 60 s y 120 s por defecto, conservando chat y borrador. Cuando cambia la última actividad del chat seleccionado, consulta sus mensajes recientes sin eliminar el historial anterior cargado. Comparte bloqueos, presupuesto y pausas del backend con el cron y otros dispositivos. No se reenvían mensajes automáticamente. Se pausa al ocultar la pestaña o perder conexión y cancela sus temporizadores al cambiar de sección/cerrar sesión.

**Con la app cerrada sigue siendo necesario el cron del VPS**, y activar las consultas programadas de la cuenta. Esta actualización no instala un cron en CyberPanel ni añade push. No se ha comprobado desde desarrollo si ese cron existe en el VPS. Véase README.md de despliegue nativo para su configuración.

Los errores de historial y envío muestran códigos seguros (HTTP y códigos internos) en lugar de una explicación genérica. No se ha confirmado la causa del fallo de historial/correo mostrado en las capturas del VPS; los nuevos códigos permitirán distinguir respuesta no JSON del servidor, rechazo del canal y formato de historial no válido. No se exponen cuerpos remotos ni credenciales.
