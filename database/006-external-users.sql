CREATE TABLE IF NOT EXISTS external_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 connection_id BIGINT UNSIGNED NOT NULL,
 external_user_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 email VARCHAR(190) NULL,
 display_name VARCHAR(190) NOT NULL,
 role_type VARCHAR(40) NULL,
 role_name VARCHAR(40) NULL,
 permissions JSON NOT NULL,
 scopes JSON NOT NULL,
 assigned_data_only BOOLEAN NOT NULL DEFAULT FALSE,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY external_user (connection_id,external_user_id),
 KEY external_email (email),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS external_identity_links (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 external_user_row_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider_subject VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY provider_identity (provider,provider_subject),
 UNIQUE KEY user_provider (external_user_row_id,provider),
 FOREIGN KEY (external_user_row_id) REFERENCES external_users(id) ON DELETE CASCADE
);
