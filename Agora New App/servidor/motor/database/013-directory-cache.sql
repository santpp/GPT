-- Shared short-lived cache for the list screens (contacts, opportunity board).
CREATE TABLE IF NOT EXISTS directory_cache (
 connection_id BIGINT UNSIGNED NOT NULL,
 cache_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NOT NULL,
 fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,cache_key),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
