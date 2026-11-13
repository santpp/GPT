CREATE TABLE IF NOT EXISTS agora_schema (version VARCHAR(50) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE agora_tenants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, subaccount VARCHAR(120) NOT NULL, code VARCHAR(40) NOT NULL UNIQUE,
 status VARCHAR(20) NOT NULL DEFAULT 'activa', currency CHAR(3) NOT NULL DEFAULT 'USD', industry VARCHAR(100) NOT NULL DEFAULT '', notes TEXT NULL, logo MEDIUMTEXT NULL,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY status_id(status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE agora_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL UNIQUE, phone VARCHAR(40) NOT NULL DEFAULT '', avatar MEDIUMTEXT NULL,
 password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, local_tenant_id BIGINT UNSIGNED NULL, active TINYINT NOT NULL DEFAULT 1,
 owner_slot TINYINT GENERATED ALWAYS AS (CASE WHEN role='MASTER' THEN 1 ELSE NULL END) STORED,
 auth_version BIGINT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY single_owner(owner_slot), KEY tenant_page(local_tenant_id,id), KEY tenant_active(local_tenant_id,active,id), KEY role_page(role,id),
 FOREIGN KEY(local_tenant_id) REFERENCES agora_tenants(id),
 CHECK (role IN ('MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','USUARIO','CONSULTA','VISOR')),
 CHECK ((role IN ('MASTER','SUPERADMIN') AND local_tenant_id IS NULL) OR (role NOT IN ('MASTER','SUPERADMIN') AND local_tenant_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE agora_assignments (
 user_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(user_id,tenant_id), KEY tenant_user(tenant_id,user_id),
 FOREIGN KEY(user_id) REFERENCES agora_users(id) ON DELETE CASCADE, FOREIGN KEY(tenant_id) REFERENCES agora_tenants(id)
) ENGINE=InnoDB;
CREATE TABLE agora_sessions (
 token_hash CHAR(64) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, auth_version BIGINT UNSIGNED NOT NULL, active_tenant_id BIGINT UNSIGNED NULL,
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY expiry(expires_at), KEY user_sessions(user_id), FOREIGN KEY(user_id) REFERENCES agora_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE agora_rate_limits (bucket CHAR(64) PRIMARY KEY, hits INT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL, KEY expiry(expires_at)) ENGINE=InnoDB;
CREATE TABLE agora_records (
 seq BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(24) NOT NULL, uid VARCHAR(80) NOT NULL,
 owner_id BIGINT UNSIGNED NULL, label VARCHAR(254) NOT NULL DEFAULT '', status VARCHAR(32) NOT NULL DEFAULT '', period CHAR(7) NOT NULL DEFAULT '',
 data JSON NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY identity(tenant_id,kind,uid), KEY list_page(tenant_id,kind,seq), KEY owned_page(tenant_id,kind,owner_id,seq), KEY period_page(tenant_id,kind,period,seq), KEY status_page(tenant_id,kind,status,seq),
 FOREIGN KEY(tenant_id) REFERENCES agora_tenants(id), FOREIGN KEY(owner_id) REFERENCES agora_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE agora_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NULL, actor_id BIGINT UNSIGNED NULL, actor_name VARCHAR(120) NOT NULL,
 action VARCHAR(100) NOT NULL, module VARCHAR(40) NOT NULL, target VARCHAR(100) NOT NULL DEFAULT '', details JSON NOT NULL, ip VARCHAR(45) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY tenant_time(tenant_id,created_at,id), KEY actor_time(actor_id,created_at,id), KEY time_id(created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE agora_settings (
 tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0, setting_key VARCHAR(120) NOT NULL, data JSON NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE agora_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(40) NOT NULL, payload JSON NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'queued', attempts INT UNSIGNED NOT NULL DEFAULT 0, available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 locked_at DATETIME NULL, last_error VARCHAR(500) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY pending(status,available_at,id), KEY tenant_jobs(tenant_id,id), FOREIGN KEY(tenant_id) REFERENCES agora_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE agora_signatures (
 token_hash CHAR(64) PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, contract_uid VARCHAR(80) NOT NULL, contract_version INT NOT NULL,
 document_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL, revoked_at DATETIME NULL,
 KEY contract_tokens(tenant_id,contract_uid), KEY expiry(expires_at), FOREIGN KEY(tenant_id) REFERENCES agora_tenants(id)
) ENGINE=InnoDB;
CREATE TABLE agora_idempotency (
 tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,request_key VARCHAR(80) NOT NULL,request_hash CHAR(64) NOT NULL,response JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(tenant_id,user_id,request_key),KEY expiry(created_at)
) ENGINE=InnoDB;
