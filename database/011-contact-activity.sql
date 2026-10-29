-- Tasks, notes and appointments of a contact inside the conversation, plus the
-- record that makes a quick action safe to retry.
CREATE TABLE IF NOT EXISTS contact_activity_cache (
 connection_id BIGINT UNSIGNED NOT NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NOT NULL,
 fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,contact_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contact_action_attempts (
 connection_id BIGINT UNSIGNED NOT NULL,
 request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 action VARCHAR(24) NOT NULL,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state ENUM('running','accepted','rejected','unknown') NOT NULL DEFAULT 'running',
 result_id VARCHAR(100) NULL,
 error_code VARCHAR(40) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,request_id),
 KEY repeated_action (connection_id,contact_id,request_hash),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
