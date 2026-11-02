# Actualizar Ágora a 1.8.6

Corrección: un usuario nuevo entraba y decía que no tenía las conversaciones activadas.

## Pasos

1. Extraer `agora-update-1.8.6.zip` sobre las carpetas existentes. No hay migraciones nuevas.
2. Cerrar Ágora del todo y volver a abrirla. El pie debe decir **v1.8.6**.

## Qué cambia

- La cuenta sólo lista en los permisos de un usuario los que se cambiaron; los demás están activados por defecto (conversaciones, contactos, oportunidades y citas). Ágora trataba los no listados como desactivados. Ahora los toma como activados, y lo que la cuenta desactiva de forma explícita sigue desactivado.
- Si un usuario sigue sin acceso: en **Conexiones → Equipo y permisos** se ve qué tiene activado. Si «conversaciones» aparece desactivado, hay que activarlo en los permisos de ese usuario en la subcuenta.
