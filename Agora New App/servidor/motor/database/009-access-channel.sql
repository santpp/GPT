-- Access channel: one connected account delivers the team access codes.
CREATE TABLE IF NOT EXISTS access_channels (
 tenant_id BIGINT UNSIGNED PRIMARY KEY,
 connection_id BIGINT UNSIGNED NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);

-- One internal access contact per address and connection. The unique contact key
-- stops two different team addresses from sharing a single resolved contact.
CREATE TABLE IF NOT EXISTS access_contacts (
 connection_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL,
 contact_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_by_agora BOOLEAN NOT NULL DEFAULT FALSE,
 tagged BOOLEAN NOT NULL DEFAULT FALSE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_delivery_at TIMESTAMP NULL,
 PRIMARY KEY (connection_id,email),
 UNIQUE KEY connection_contact (connection_id,contact_id),
 FOREIGN KEY (connection_id) REFERENCES ghl_connections(id) ON DELETE CASCADE
);
