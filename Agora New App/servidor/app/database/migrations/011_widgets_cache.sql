-- Agora New App 2.16.0 · Caché corto de las lecturas de los widgets que consultan la cuenta (cifras del mes).
-- Un gráfico de varios meses no debe gastar la cuota de la cuenta. Es por usuario (cada rol ve lo suyo) y vence en minutos.
CREATE TABLE IF NOT EXISTS agora_widget_cache (
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  clave VARCHAR(80) NOT NULL,
  datos MEDIUMTEXT NOT NULL,
  expira DATETIME NOT NULL,
  PRIMARY KEY (tenant_id, user_id, clave),
  KEY expira (expira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
