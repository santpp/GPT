-- Las ventas marcadas como ganadas desde Ágora, con su fecha de cierre. El
-- tablero las suma a las que devuelve la cuenta para el mes, sin repetirlas:
-- así una oportunidad creada otro mes y ganada hoy cuenta hoy.
CREATE TABLE IF NOT EXISTS won_sales (
 connection_id BIGINT UNSIGNED NOT NULL,
 opportunity_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 assigned_to VARCHAR(80) CHARACTER SET ascii NULL,
 value DECIMAL(14,2) NOT NULL DEFAULT 0,
 won_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,opportunity_id),
 KEY won_month (connection_id,won_at),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
