CREATE TABLE tenants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL,
 location_id VARCHAR(80) NOT NULL UNIQUE
);
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('administrator','supervisor','seller') NOT NULL,
 ghl_user_id VARCHAR(80),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
CREATE TABLE webhook_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 webhook_id VARCHAR(190) NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 payload JSON NOT NULL,
 status ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
 attempts INT NOT NULL DEFAULT 0,
 received_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 processed_at TIMESTAMP(6) NULL,
 UNIQUE(tenant_id,webhook_id),
 INDEX(status,id),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
-- Read model: server-owned data, no GHL credentials. Full reconciliation is still required.
CREATE TABLE resources (
 tenant_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(40) NOT NULL,
 ghl_id VARCHAR(80) NOT NULL,
 assigned_to VARCHAR(80),
 payload JSON NOT NULL,
 updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(tenant_id,kind,ghl_id),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
CREATE TABLE notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 event_id BIGINT UNSIGNED NOT NULL UNIQUE,
 conversation_id VARCHAR(80) NOT NULL,
 status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (tenant_id) REFERENCES tenants(id),
 FOREIGN KEY (event_id) REFERENCES webhook_events(id)
);
CREATE TABLE login_attempts (
 key_hash CHAR(64) PRIMARY KEY,
 attempts INT NOT NULL DEFAULT 1,
 window_start BIGINT NOT NULL
);
