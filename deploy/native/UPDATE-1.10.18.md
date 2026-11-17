# Actualizar Ágora a 1.10.18

Respuestas guardadas propias en la bandeja. Incluye todo lo de 1.10.17 (adjuntos y snippets).

## Pasos

1. Extraer `agora-update-1.10.18.zip` sobre las carpetas existentes.
2. **Hay una tabla nueva** (`quick_replies`, migración `032-quick-replies.sql`). Se crea sola al primer uso; no hay que hacer nada.
3. Si vienes de 1.10.16 o anterior, revisa también el límite de archivos de PHP (16 MB), como dice la nota de 1.10.17.
4. El pie debe decir **v1.10.18**.

## Qué cambia

- **Cada persona crea sus respuestas guardadas** desde la bandeja: «Respuestas guardadas» → «+ Nueva». Se abre con lo que ya escribiste, se le pone un nombre y se pueden insertar datos que se rellenan solos al usarla (nombre, nombre completo, teléfono y correo del contacto, y tu nombre). Puede llevar los archivos que tenías adjuntos.
- Las propias solo las ve quien las creó. **Quien administra la subcuenta** puede marcar «Compartir con todo el equipo».
- Cada quien cambia o borra las suyas (lápiz); las del equipo, quien administra.
- La lista se agrupa en «Tuyas», «Del equipo» y «De la subcuenta» (los snippets que ya existían en la cuenta).
- Si no hay ninguna, el aviso ya no dice dónde se crean en la cuenta: invita a crear la primera.
- Se guardan en Ágora porque la cuenta no permite crear snippets desde fuera.
- Pruebas: `tests/attachments.php` (32) y `tests/attach-ui.py`.
