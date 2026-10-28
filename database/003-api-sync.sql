CREATE TABLE IF NOT EXISTS ghl_sync_state (
 connection_id BIGINT UNSIGNED PRIMARY KEY,
 enabled BOOLEAN NOT NULL DEFAULT FALSE,
 next_sync_at BIGINT NOT NULL DEFAULT 0,
 request_not_before BIGINT NOT NULL DEFAULT 0,
 budget_day DATE NULL,
 requests_today INT NOT NULL DEFAULT 0,
 failures INT NOT NULL DEFAULT 0,
 last_success_at TIMESTAMP NULL,
 last_http_status INT NULL,
 last_error VARCHAR(40) NULL,
 quota JSON NULL,
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS ghl_inbox_snapshots (
 connection_id BIGINT UNSIGNED PRIMARY KEY,
 payload JSON NOT NULL,
 total INT UNSIGNED NOT NULL,
 synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
