-- Tipos de aviso: el mensaje nuevo (lo de siempre), «te asignaron una conversación»
-- y el recordatorio de un mensaje que lleva rato sin leerse. `target` es el usuario
-- de la subcuenta al que va dirigido (vacío: a los administradores).
ALTER TABLE push_queue
 ADD COLUMN IF NOT EXISTS kind VARCHAR(12) CHARACTER SET ascii NOT NULL DEFAULT 'message',
 ADD COLUMN IF NOT EXISTS target VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL;
-- A los cuántos minutos se recuerda un mensaje sin leer en esta subcuenta (0 = no recordar).
ALTER TABLE ghl_connections
 ADD COLUMN IF NOT EXISTS unread_reminder_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10;
