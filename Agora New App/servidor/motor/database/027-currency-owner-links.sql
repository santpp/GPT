-- Moneda de cada subcuenta (sólo euros o dólares) y, para quien administra Ágora,
-- qué usuario de la subcuenta es él (para «mis leads» y «mis ventas»).
ALTER TABLE ghl_connections
 ADD COLUMN IF NOT EXISTS currency CHAR(3) CHARACTER SET ascii NOT NULL DEFAULT 'USD';
CREATE TABLE IF NOT EXISTS owner_links (
 user_id BIGINT UNSIGNED NOT NULL,
 connection_id BIGINT UNSIGNED NOT NULL,
 external_user_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,connection_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
