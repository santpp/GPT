-- Agora New App 2.20.0 · Enlaces públicos de documentos (la factura que se envía por correo) y notificaciones dentro de Ágora.
-- El enlace guarda solo la huella del token, vence y anota cuándo lo abrió el cliente. Cada notificación es de una persona.
CREATE TABLE IF NOT EXISTS agora_doc_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  uid VARCHAR(40) NOT NULL,
  correo VARCHAR(190) NOT NULL DEFAULT '',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  visto_at DATETIME NULL,
  vistas INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY por_token (token_hash),
  KEY por_doc (tenant_id, uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS agora_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NULL,
  tipo VARCHAR(30) NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  texto VARCHAR(300) NOT NULL DEFAULT '',
  enlace VARCHAR(200) NOT NULL DEFAULT '',
  clave VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leida_at DATETIME NULL,
  UNIQUE KEY una_vez (user_id, clave),
  KEY por_usuario (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
