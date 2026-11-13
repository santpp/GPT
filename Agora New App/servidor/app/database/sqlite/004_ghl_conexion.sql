CREATE TABLE IF NOT EXISTS agora_ghl_connections (
 tenant_id INTEGER NOT NULL PRIMARY KEY,
 location_id TEXT NOT NULL,
 company_id TEXT NULL,
 token_ciphertext TEXT NOT NULL,
 token_hint TEXT NOT NULL DEFAULT '',
 status TEXT NOT NULL DEFAULT 'sin_probar',
 location_name TEXT NULL,
 checked_at TEXT NULL,
 last_error TEXT NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
