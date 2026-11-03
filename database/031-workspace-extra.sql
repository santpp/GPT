-- 1.11.0 · lo que las pantallas nuevas guardan en Ágora: tareas, recordatorios de seguimiento,
-- el registro de auditoría (sólo se agrega), la foto diaria de la operación y los datos de
-- quien entra con correo y clave (nombre, activo y los cinco roles).
CREATE TABLE IF NOT EXISTS workspace_tasks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 connection_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 detail VARCHAR(500) NULL,
 assignee_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 assignee_name VARCHAR(190) NULL,
 due_at DATETIME NULL,
 state ENUM('pendiente','hecha') NOT NULL DEFAULT 'pendiente',
 origin ENUM('recordatorio','manual','sistema','convenio') NOT NULL DEFAULT 'manual',
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 contact_name VARCHAR(190) NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 created_by VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 done_at TIMESTAMP NULL,
 KEY by_tenant_due (tenant_id,due_at),
 KEY by_assignee (tenant_id,assignee_id,state),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS conversation_reminders (
 connection_id BIGINT UNSIGNED NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 remind_at DATETIME NOT NULL,
 note VARCHAR(140) NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 contact_name VARCHAR(190) NULL,
 task_id BIGINT UNSIGNED NULL,
 created_by VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,conversation_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
-- Auditoría: sólo se agrega. Nada de la aplicación edita ni borra una fila.
CREATE TABLE IF NOT EXISTS audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 connection_id BIGINT UNSIGNED NULL,
 at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actor_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 actor_name VARCHAR(190) NOT NULL,
 actor_role VARCHAR(40) NULL,
 action VARCHAR(20) CHARACTER SET ascii NOT NULL,
 action_text VARCHAR(120) NOT NULL,
 module VARCHAR(20) CHARACTER SET ascii NOT NULL,
 object_label VARCHAR(120) NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 contact_name VARCHAR(190) NULL,
 device VARCHAR(120) NULL,
 ip VARCHAR(45) CHARACTER SET ascii NULL,
 origin VARCHAR(10) CHARACTER SET ascii NOT NULL DEFAULT 'sistema',
 before_json JSON NULL,
 after_json JSON NULL,
 KEY by_tenant_time (tenant_id,at),
 KEY by_module (tenant_id,module,at),
 KEY by_connection (tenant_id,connection_id,at),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
-- La foto de cada día: sirve para decir «frente a ayer» sin guardar cada mensaje.
CREATE TABLE IF NOT EXISTS daily_metrics (
 connection_id BIGINT UNSIGNED NOT NULL,
 day DATE NOT NULL,
 kind VARCHAR(20) CHARACTER SET ascii NOT NULL,
 payload JSON NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,day,kind),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
-- La identidad del espacio: nombre y logo (guardado aquí, no en un archivo, para que sobreviva a las actualizaciones).
CREATE TABLE IF NOT EXISTS workspace_branding (
 tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 name VARCHAR(80) NULL,
 logo MEDIUMBLOB NULL,
 logo_type VARCHAR(20) CHARACTER SET ascii NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(190) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS active BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users MODIFY COLUMN role ENUM('administrator','supervisor','seller','admin','viewer') NOT NULL;
