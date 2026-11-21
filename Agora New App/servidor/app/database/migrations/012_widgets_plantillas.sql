-- Agora New App 2.18.0 · Plantillas de widgets por rubro. Las crean los dueños (Master y Superadmin) a partir de una subcuenta
-- y la administración de cualquier subcuenta las aplica. Guardan tableros, fórmulas y variables (sin datos de clientes).
CREATE TABLE IF NOT EXISTS agora_widget_plantillas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  rubro VARCHAR(60) NOT NULL DEFAULT '',
  descripcion VARCHAR(240) NOT NULL DEFAULT '',
  datos MEDIUMTEXT NOT NULL,
  creado_por BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY rubro (rubro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
