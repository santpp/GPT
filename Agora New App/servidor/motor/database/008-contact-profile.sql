CREATE TABLE IF NOT EXISTS contact_profile_cache (
 connection_id BIGINT UNSIGNED NOT NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NOT NULL,
 fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,contact_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contact_field_cache (
 connection_id BIGINT UNSIGNED PRIMARY KEY,
 payload JSON NOT NULL,
 fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
