-- Opportunity context inside the conversation: short-lived per-contact results
-- and a longer-lived directory of real pipeline and stage names.
CREATE TABLE IF NOT EXISTS opportunity_cache (
 connection_id BIGINT UNSIGNED NOT NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NOT NULL,
 fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,contact_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pipeline_cache (
 connection_id BIGINT UNSIGNED PRIMARY KEY,
 payload JSON NOT NULL,
 fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
