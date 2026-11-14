-- Agora New App · enlace explícito entre la persona de Ágora y su usuario en la cuenta conectada de la subcuenta.
-- Sin fila, el enlace se deduce por correo. Con fila, manda la fila (el correo de Ágora puede ser distinto).
CREATE TABLE IF NOT EXISTS agora_user_links (
 tenant_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 external_user_id VARCHAR(80) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,user_id),
 UNIQUE KEY link_external (tenant_id,external_user_id),
 CONSTRAINT link_tenant FOREIGN KEY (tenant_id) REFERENCES agora_tenants(id) ON DELETE CASCADE,
 CONSTRAINT link_user FOREIGN KEY (user_id) REFERENCES agora_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
