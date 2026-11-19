# Actualizar Ágora a 1.10.29

Cambio: las sesiones caducan a los 15 días. Incluye 1.10.17 a 1.10.28.

## Pasos

1. Extraer `agora-update-1.10.29.zip` sobre las carpetas existentes.
2. El pie debe decir **v1.10.29**.

## Qué cambia

- **Una sesión dura 15 días desde que se entra.** Usar la app no la alarga (antes eran 60 días y se renovaban cada día, así que en la práctica no caducaba). Al cumplirse, Ágora pide entrar otra vez.
- **Las sesiones ya abiertas también se recortan:** la que se abrió hace más de 15 días se cierra al instalar esta versión.
- **Con huella o cara activada, volver a entrar es un toque:** el botón de huella o cara entra sin escribir la contraseña.
- El bloqueo con huella o cara (pedirla tras unos minutos sin usar la app) sigue igual; se revisó y funciona.
- Prueba nueva: `tests/session-days.php`.
