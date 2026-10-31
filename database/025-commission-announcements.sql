-- La comisión de cada subcuenta, que sólo cambia el administrador desde
-- Conexiones. Cada cambio vale desde el mes en que se hace; los meses
-- anteriores conservan la regla con la que se calcularon.
CREATE TABLE IF NOT EXISTS commission_rules (
 connection_id BIGINT UNSIGNED NOT NULL,
 effective_from CHAR(7) CHARACTER SET ascii NOT NULL,
 kind VARCHAR(16) CHARACTER SET ascii NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,effective_from),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
-- Las novedades que escribe el administrador y que cada persona ve una vez al entrar.
CREATE TABLE IF NOT EXISTS announcements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(120) NOT NULL,
 body TEXT NOT NULL,
 link_label VARCHAR(40) NULL,
 link_url VARCHAR(300) NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 expires_at INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY announcement_tenant (tenant_id,active,created_at)
);
CREATE TABLE IF NOT EXISTS announcement_reads (
 announcement_id BIGINT UNSIGNED NOT NULL,
 identity_type VARCHAR(16) CHARACTER SET ascii NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (announcement_id,identity_type,actor_id),
 FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
);
