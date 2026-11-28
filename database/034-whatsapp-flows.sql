-- Ágora Mobile 1.10.36 · Plantillas de WhatsApp por flujos de trabajo de la cuenta.
-- El propietario elige en Conexiones qué flujos son plantillas; la Bandeja mete al contacto en el flujo y la cuenta envía la plantilla.
CREATE TABLE IF NOT EXISTS whatsapp_flows (
 connection_id BIGINT UNSIGNED NOT NULL,
 workflow_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label VARCHAR(120) NOT NULL,
 account_name VARCHAR(200) NOT NULL DEFAULT '',
 preview TEXT NULL,
 position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 account_status VARCHAR(20) CHARACTER SET ascii NOT NULL DEFAULT 'published',
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (connection_id,workflow_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS whatsapp_flow_sends (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 connection_id BIGINT UNSIGNED NOT NULL,
 request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 workflow_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 conversation_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label VARCHAR(120) NOT NULL DEFAULT '',
 author_name VARCHAR(120) NOT NULL DEFAULT '',
 external_user_id VARCHAR(80) CHARACTER SET ascii NULL,
 state VARCHAR(20) CHARACTER SET ascii NOT NULL DEFAULT 'sending',
 error_code VARCHAR(40) CHARACTER SET ascii NULL,
 message_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
 requested_at INT UNSIGNED NOT NULL,
 seen_at INT UNSIGNED NULL,
 UNIQUE KEY wa_flow_request (connection_id,request_id),
 KEY wa_flow_recent (connection_id,contact_id,workflow_id,requested_at),
 KEY wa_flow_month (connection_id,requested_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
