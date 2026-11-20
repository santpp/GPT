-- Agora New App 2.12.0 · Tasas del día en tres fuentes (BCV dólar, BCV euro y USDT de Binance) y factura con precios en divisas.
-- La factura se emite en bolívares: guarda de qué moneda y con qué tasa se calcularon sus precios (ref_json) y, si se quiere, la hora de vencimiento.
CREATE TABLE IF NOT EXISTS agora_fiscal_tasas_ref (
  org_id BIGINT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  source VARCHAR(10) NOT NULL,
  rate DECIMAL(18,6) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (org_id, day, source),
  CONSTRAINT fk_aftref_org FOREIGN KEY (org_id) REFERENCES agora_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- Las columnas nuevas de agora_fiscal_documents (ref_json, due_time) las agrega Fiscal\Esquema::columnas(), que comprueba antes si existen (vale para MySQL y MariaDB).
