-- Agora New App 2.14.0 · Widgets: fórmulas propias, variables por mes y tableros guardados.
-- Las fórmulas y variables son de cada subcuenta. Las crea solo la administración (Administración, Supervisión y dueños).
CREATE TABLE IF NOT EXISTS agora_widget_formulas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  nombre_clave VARCHAR(60) NOT NULL,
  expresion VARCHAR(500) NOT NULL,
  formato VARCHAR(12) NOT NULL DEFAULT 'auto',
  decimales TINYINT UNSIGNED NOT NULL DEFAULT 2,
  descripcion VARCHAR(190) NOT NULL DEFAULT '',
  creado_por BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY nombre_unico (tenant_id, nombre_clave),
  KEY tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS agora_widget_variables (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  nombre_clave VARCHAR(60) NOT NULL,
  periodo CHAR(7) NOT NULL DEFAULT '',
  valor DECIMAL(20,6) NOT NULL,
  unidad VARCHAR(12) NOT NULL DEFAULT 'numero',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY variable_mes (tenant_id, nombre_clave, periodo),
  KEY tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS agora_dashboards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  modulo VARCHAR(40) NOT NULL,
  alcance VARCHAR(12) NOT NULL DEFAULT 'equipo',
  user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  config LONGTEXT NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY tablero (tenant_id, modulo, alcance, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
