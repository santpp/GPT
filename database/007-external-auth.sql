CREATE TABLE IF NOT EXISTS external_identities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL,
 verified_at TIMESTAMP NULL,
 last_login_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY tenant_email (tenant_id,email),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS external_identity_memberships (
 identity_id BIGINT UNSIGNED NOT NULL,
 external_user_row_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (identity_id,external_user_row_id),
 UNIQUE KEY external_user_identity (external_user_row_id),
 FOREIGN KEY (identity_id) REFERENCES external_identities(id) ON DELETE CASCADE,
 FOREIGN KEY (external_user_row_id) REFERENCES external_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS external_login_challenges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 identity_id BIGINT UNSIGNED NOT NULL,
 code_hash VARCHAR(255) NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 expires_at BIGINT UNSIGNED NOT NULL,
 consumed_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY active_challenge (identity_id,consumed_at,expires_at),
 FOREIGN KEY (identity_id) REFERENCES external_identities(id) ON DELETE CASCADE
);

ALTER TABLE ghl_sync_state
 ADD COLUMN IF NOT EXISTS users_next_sync_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS users_last_success_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS users_last_error VARCHAR(40) NULL;
