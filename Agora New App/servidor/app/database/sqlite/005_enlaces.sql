CREATE TABLE IF NOT EXISTS agora_user_links (
 tenant_id INTEGER NOT NULL,
 user_id INTEGER NOT NULL,
 external_user_id TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,user_id),
 UNIQUE (tenant_id,external_user_id)
);
