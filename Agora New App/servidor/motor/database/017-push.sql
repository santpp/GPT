-- Android notifications. A device registers the token its own system gives it;
-- Ágora never stores anything else about the device. The queue exists so a
-- notice is prepared by the pass that discovered the message and delivered by a
-- cron, and so the same message can never be sent twice.
CREATE TABLE IF NOT EXISTS push_devices (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 identity_type ENUM('native','external') NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 token VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 platform ENUM('android','web') NOT NULL DEFAULT 'android',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY one_per_token (token),
 KEY owner (tenant_id,identity_type,actor_id),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS push_queue (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 connection_id BIGINT UNSIGNED NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fingerprint CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 title VARCHAR(190) NOT NULL,
 body VARCHAR(300) NOT NULL,
 assigned_to VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 state ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 not_before BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE KEY once_per_message (connection_id,fingerprint),
 KEY pending (state,not_before),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
