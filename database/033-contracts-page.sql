-- Página de contratos de la subcuenta (por ejemplo contratos.agoradigital.app). Vacía = sin botón «Contratos».
ALTER TABLE ghl_connections
 ADD COLUMN IF NOT EXISTS contracts_url VARCHAR(255) NULL;
