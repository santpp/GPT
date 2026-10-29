-- Conversations are fetched fresh on every load, so the displayed list changes
-- with the filters. Authorization cannot depend on that list: this index keeps
-- every conversation the account has shown, so an open chat never becomes
-- unreachable just because a filter excludes it.
CREATE TABLE IF NOT EXISTS conversation_index (
 connection_id BIGINT UNSIGNED NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NOT NULL,
 seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,conversation_id),
 KEY recent (connection_id,seen_at),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
