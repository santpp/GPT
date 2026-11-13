CREATE TABLE IF NOT EXISTS connection_preferences (
 connection_id BIGINT UNSIGNED PRIMARY KEY,
 inbox_limit INT NOT NULL DEFAULT 20,
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS message_send_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 connection_id BIGINT UNSIGNED NOT NULL,
 request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state VARCHAR(16) NOT NULL,
 message_id VARCHAR(100) NULL,
 delivery_status VARCHAR(20) NULL,
 error_code VARCHAR(40) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY send_request (connection_id,request_id),
 KEY send_content (connection_id,conversation_id,request_hash,state),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
