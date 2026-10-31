-- Para saber dónde se corta la cadena de un aviso sin entrar al servidor: cuándo
-- corrió por última vez la entrega y con qué resultado, y por qué falló cada
-- aviso que no llegó a salir.
CREATE TABLE IF NOT EXISTS app_state (
 name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 value JSON NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
ALTER TABLE push_queue
 ADD COLUMN IF NOT EXISTS last_error VARCHAR(80) NULL;
