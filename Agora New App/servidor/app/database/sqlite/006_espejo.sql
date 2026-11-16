CREATE TABLE IF NOT EXISTS agora_cuenta_contactos (
 tenant_id INTEGER NOT NULL, ghl_id TEXT NOT NULL, nombre TEXT NOT NULL DEFAULT '', correo TEXT NULL, telefono TEXT NULL, asignado_a TEXT NULL,
 etiquetas TEXT NULL, creado_en DATETIME NULL, datos TEXT NOT NULL, leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,ghl_id)
);
CREATE TABLE IF NOT EXISTS agora_cuenta_oportunidades (
 tenant_id INTEGER NOT NULL, ghl_id TEXT NOT NULL, nombre TEXT NOT NULL DEFAULT '', contacto_id TEXT NULL, contacto_nombre TEXT NULL, embudo_id TEXT NULL,
 etapa_id TEXT NULL, estado TEXT NOT NULL DEFAULT 'open', valor REAL NOT NULL DEFAULT 0, asignado_a TEXT NULL, ganada_en DATETIME NULL, creada_en DATETIME NULL,
 venta_id TEXT NULL, datos TEXT NOT NULL, leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,ghl_id)
)
