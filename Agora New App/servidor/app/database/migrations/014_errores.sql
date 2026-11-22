-- Agora New App 2.19.0 · Bitácora de errores (pedido del usuario): los errores del servidor con su código y su referencia,
-- y los reportes que manda cada persona desde la pantalla. Lo ven solo los dueños en Agencia, Errores.
CREATE TABLE IF NOT EXISTS agora_error_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  referencia VARCHAR(32) NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  status SMALLINT UNSIGNED NOT NULL,
  metodo VARCHAR(8) NOT NULL DEFAULT '',
  ruta VARCHAR(160) NOT NULL DEFAULT '',
  user_id BIGINT UNSIGNED NULL,
  tenant_id BIGINT UNSIGNED NULL,
  mensaje VARCHAR(300) NOT NULL DEFAULT '',
  tecnico VARCHAR(800) NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  KEY por_ref (referencia),
  KEY creado (created_at),
  KEY por_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS agora_error_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id BIGINT UNSIGNED NULL,
  tenant_id BIGINT UNSIGNED NULL,
  estado VARCHAR(12) NOT NULL DEFAULT 'nuevo',
  descripcion VARCHAR(1000) NOT NULL DEFAULT '',
  pantalla VARCHAR(160) NOT NULL DEFAULT '',
  version_app VARCHAR(20) NOT NULL DEFAULT '',
  dispositivo VARCHAR(160) NOT NULL DEFAULT '',
  errores MEDIUMTEXT NULL,
  lentas TEXT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  revisado_por BIGINT UNSIGNED NULL,
  revisado_at DATETIME NULL,
  KEY creado (created_at),
  KEY por_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
