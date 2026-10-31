-- Sesión que dura en el dispositivo y entrada con huella o cara.
-- De cada sesión sólo se guarda la huella SHA-256 de su secreto; de cada llave de
-- acceso, sólo su parte pública. Nada de esto permite entrar si se filtra.
CREATE TABLE IF NOT EXISTS device_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 identity_type VARCHAR(16) CHARACTER SET ascii NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 selector CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 verifier_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 previous_hash CHAR(64) CHARACTER SET ascii NULL,
 rotated_at INT UNSIGNED NOT NULL,
 csrf CHAR(64) CHARACTER SET ascii NOT NULL,
 label VARCHAR(120) NULL,
 lock_minutes SMALLINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_used_at TIMESTAMP NULL,
 expires_at INT UNSIGNED NOT NULL,
 revoked_at TIMESTAMP NULL,
 UNIQUE KEY device_selector (selector),
 KEY device_actor (tenant_id,identity_type,actor_id)
);
CREATE TABLE IF NOT EXISTS passkeys (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 identity_type VARCHAR(16) CHARACTER SET ascii NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 credential_id VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 public_key TEXT NOT NULL,
 algorithm SMALLINT NOT NULL,
 sign_count INT UNSIGNED NOT NULL DEFAULT 0,
 label VARCHAR(120) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_used_at TIMESTAMP NULL,
 UNIQUE KEY passkey_credential (credential_id),
 KEY passkey_actor (tenant_id,identity_type,actor_id)
);
