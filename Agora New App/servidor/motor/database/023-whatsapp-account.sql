-- La cuenta de WhatsApp Business (WABA) de la subcuenta, si sus plantillas se
-- leen con ese identificador en vez del del número que envía.
ALTER TABLE ghl_connections
 ADD COLUMN IF NOT EXISTS whatsapp_account_id VARCHAR(40) CHARACTER SET ascii NULL;
