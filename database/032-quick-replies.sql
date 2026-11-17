-- Respuestas guardadas propias de Ágora (las de la cuenta se leen aparte). Personales o compartidas con el equipo de la subcuenta.
CREATE TABLE IF NOT EXISTS quick_replies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 connection_id BIGINT UNSIGNED NOT NULL,
 identity_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 shared TINYINT(1) NOT NULL DEFAULT 0,
 name VARCHAR(120) NOT NULL,
 body TEXT NOT NULL,
 attachments TEXT NULL,
 created_at INT UNSIGNED NOT NULL,
 updated_at INT UNSIGNED NOT NULL,
 KEY quick_replies_team (connection_id,shared),
 KEY quick_replies_owner (connection_id,identity_type,actor_id)
);
