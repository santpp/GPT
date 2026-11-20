# Actualizar Ágora a 1.10.34

Cambio: los avisos del teléfono ya no se pierden ni se vuelven a pedir cada rato (Android). Incluye 1.10.17 a 1.10.33.

## Pasos

1. Extraer `agora-update-1.10.34.zip` sobre las carpetas existentes.
2. El pie debe decir **v1.10.34**.
3. A la persona afectada: abrir Ágora una vez y, si lo pide, tocar «Activar avisos» por última vez. Desde ahí, el teléfono conserva sus avisos.

## Qué estaba mal (error nuestro, desde 1.7.1)

- Al volver a Ágora, la app pregunta al servidor si ese teléfono sigue registrado. Esa pregunta llegaba al diagnóstico de la propietaria, que la contestaba antes de tiempo: a una vendedora le decía «acceso restringido» y a la propietaria le daba otra cosa.
- La app entendía «no registrado», **borraba la suscripción de avisos del teléfono y pedía otra**, cada vez que se volvía a la app. En Android, rehacerla tantas veces a veces falla: el teléfono se quedaba sin avisos, no llegaban notificaciones y la app volvía a pedir «Activar avisos».

## Qué cambia

- El servidor contesta esa pregunta a cualquiera, con su propia respuesta. El diagnóstico de la propietaria no cambia.
- La app ya no borra nunca la suscripción del teléfono. Si el servidor no la tiene, la vuelve a registrar tal cual; si no se pudo preguntar, no toca nada; solo crea una nueva si el teléfono no tiene ninguna.
- Pruebas nuevas: `tests/push-heal.php` (reproduce el error con el código anterior) y `tests/push-heal-ui.py`.
