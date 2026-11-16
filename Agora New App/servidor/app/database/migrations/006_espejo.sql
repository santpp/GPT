-- Agora New App 2.9.0 · Espejo de la cuenta: contactos y oportunidades (incluidas las ganadas) tal como los informa la cuenta.
-- Cada lectura sobrescribe la fila del mismo identificador de la cuenta (nunca se duplica). Solo se lee: los cambios van primero a la cuenta.
CREATE TABLE IF NOT EXISTS agora_cuenta_contactos (
 tenant_id BIGINT UNSIGNED NOT NULL,
 ghl_id VARCHAR(80) NOT NULL,
 nombre VARCHAR(190) NOT NULL DEFAULT '',
 correo VARCHAR(190) NULL,
 telefono VARCHAR(60) NULL,
 asignado_a VARCHAR(80) NULL,
 etiquetas TEXT NULL,
 creado_en DATETIME NULL,
 datos LONGTEXT NOT NULL,
 leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,ghl_id),
 KEY cuenta_contacto_correo (tenant_id,correo),
 KEY cuenta_contacto_telefono (tenant_id,telefono),
 CONSTRAINT cuenta_contacto_tenant FOREIGN KEY (tenant_id) REFERENCES agora_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS agora_cuenta_oportunidades (
 tenant_id BIGINT UNSIGNED NOT NULL,
 ghl_id VARCHAR(80) NOT NULL,
 nombre VARCHAR(190) NOT NULL DEFAULT '',
 contacto_id VARCHAR(80) NULL,
 contacto_nombre VARCHAR(190) NULL,
 embudo_id VARCHAR(80) NULL,
 etapa_id VARCHAR(80) NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'open',
 valor DECIMAL(14,2) NOT NULL DEFAULT 0,
 asignado_a VARCHAR(80) NULL,
 ganada_en DATETIME NULL,
 creada_en DATETIME NULL,
 venta_id VARCHAR(80) NULL,
 datos LONGTEXT NOT NULL,
 leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,ghl_id),
 KEY cuenta_op_estado (tenant_id,estado,ganada_en),
 KEY cuenta_op_contacto (tenant_id,contacto_id),
 CONSTRAINT cuenta_op_tenant FOREIGN KEY (tenant_id) REFERENCES agora_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
