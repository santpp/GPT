-- Redes por las que ha escrito el contacto en una conversación (Instagram, Messenger),
-- aunque el último mensaje haya sido por otro canal. Sólo se añaden, nunca se quitan.
CREATE TABLE IF NOT EXISTS conversation_networks (
 connection_id BIGINT UNSIGNED NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 channel VARCHAR(4) CHARACTER SET ascii NOT NULL,
 seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,conversation_id,channel),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
