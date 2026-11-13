-- «Entrar como»: cada vez que la persona propietaria mira Ágora como otro usuario queda anotado.
CREATE TABLE IF NOT EXISTS view_as_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 connection_id BIGINT UNSIGNED NOT NULL,
 external_user_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 started_at INT UNSIGNED NOT NULL,
 ended_at INT UNSIGNED NULL,
 KEY view_as_tenant (tenant_id,started_at)
);
