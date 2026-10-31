-- Lo que Ágora envió como respuesta a un mensaje concreto. La cuenta publica el
-- campo para citar al enviar, pero no devuelve a qué mensaje responde cada uno
-- al leer el historial: se guarda aquí para poder enseñar la cita.
CREATE TABLE IF NOT EXISTS message_replies (
 connection_id BIGINT UNSIGNED NOT NULL,
 message_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reply_to_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 snippet VARCHAR(200) NULL,
 quoted_direction VARCHAR(8) CHARACTER SET ascii NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,message_id),
 KEY reply_conversation (connection_id,conversation_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
