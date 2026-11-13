-- Días que una venta ganada puede estar sin monto antes de que la alerta deje de poder
-- cerrarse (0 = nunca bloquea). Lo fija el propietario por subcuenta.
ALTER TABLE ghl_connections
 ADD COLUMN IF NOT EXISTS won_alert_days SMALLINT UNSIGNED NOT NULL DEFAULT 7;
