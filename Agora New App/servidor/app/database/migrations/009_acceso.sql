-- Agora New App 2.13.0 · Acceso con código por correo y «¿Olvidaste tu clave?».
-- Los códigos (6 dígitos) se guardan solo como huella (HMAC con app_key), caducan, tienen intentos limitados y se usan una vez.
-- Un equipo de confianza es una cookie aleatoria (no la IP): tras el código, ese navegador no lo vuelve a pedir durante 14 días.
CREATE TABLE IF NOT EXISTS agora_access_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  challenge_hash CHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  purpose VARCHAR(10) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY challenge (challenge_hash),
  KEY user_purpose (user_id, purpose),
  KEY expiry (expires_at),
  FOREIGN KEY (user_id) REFERENCES agora_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS agora_trusted_devices (
  token_hash CHAR(64) PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  auth_version BIGINT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL DEFAULT '',
  expires_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY user_devices (user_id),
  KEY expiry (expires_at),
  FOREIGN KEY (user_id) REFERENCES agora_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
