-- El número de WhatsApp de cada subcuenta, tal como lo nombra Meta. Sirve para
-- leer sus plantillas aprobadas y para enviarlas desde ese número.
ALTER TABLE ghl_connections
 ADD COLUMN IF NOT EXISTS whatsapp_number_id VARCHAR(40) CHARACTER SET ascii NULL;
