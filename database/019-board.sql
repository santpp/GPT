-- Las cifras que no viven en la cuenta: lo invertido en publicidad, los costos
-- fijos, el porcentaje de comisión y las metas del equipo. Son del mes y de la
-- subcuenta, las escribe el propietario y nadie más, y viven aquí porque
-- ninguna API las conoce.
CREATE TABLE IF NOT EXISTS board_settings (
 connection_id BIGINT UNSIGNED NOT NULL,
 period CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload JSON NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,period),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
