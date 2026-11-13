CREATE TABLE IF NOT EXISTS conversation_message_cache (
 connection_id BIGINT UNSIGNED NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NOT NULL,
 last_message_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 has_more BOOLEAN NOT NULL DEFAULT FALSE,
 fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,conversation_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
