-- Agora New App · conexión de cada subcuenta con su cuenta de GoHighLevel.
-- El token se guarda cifrado (XChaCha20-Poly1305, ligado a subcuenta y Location ID). Solo se ven sus 4 últimos caracteres.
CREATE TABLE IF NOT EXISTS agora_ghl_connections (
 tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 location_id VARCHAR(64) NOT NULL,
 company_id VARCHAR(64) NULL,
 token_ciphertext TEXT NOT NULL,
 token_hint VARCHAR(8) NOT NULL DEFAULT '',
 status VARCHAR(20) NOT NULL DEFAULT 'sin_probar',
 location_name VARCHAR(190) NULL,
 checked_at DATETIME NULL,
 last_error VARCHAR(200) NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT ghl_conn_tenant FOREIGN KEY (tenant_id) REFERENCES agora_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
