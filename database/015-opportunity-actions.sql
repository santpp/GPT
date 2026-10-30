-- Creating and editing an opportunity is not idempotent upstream: the same form
-- sent twice would open two. Each attempt is recorded under the key the browser
-- generates, the same way contact actions and message sending are, so a repeat
-- with an unresolved result is refused instead of duplicating work.
CREATE TABLE IF NOT EXISTS opportunity_action_attempts (
 connection_id BIGINT UNSIGNED NOT NULL,
 request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 subject_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 action VARCHAR(24) NOT NULL,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state ENUM('running','accepted','rejected','unknown') NOT NULL DEFAULT 'running',
 result_id VARCHAR(100) NULL,
 error_code VARCHAR(40) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,request_id),
 KEY repeated_action (connection_id,subject_id,request_hash),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
