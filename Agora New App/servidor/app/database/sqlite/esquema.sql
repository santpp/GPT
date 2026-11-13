-- Ágora · esquema SQLite para el MODO PRUEBAS. Equivale a las migraciones 001, 002 y 003 de MySQL.
-- Si agregas una migración MySQL, agrega aquí su equivalente y sube la versión en Pruebas::ESQUEMA.
CREATE TABLE IF NOT EXISTS agora_schema (version TEXT PRIMARY KEY, checksum TEXT NOT NULL, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE agora_tenants (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, subaccount TEXT NOT NULL, code TEXT NOT NULL UNIQUE,
 status TEXT NOT NULL DEFAULT 'activa', currency TEXT NOT NULL DEFAULT 'USD', industry TEXT NOT NULL DEFAULT '',
 plan TEXT NOT NULL DEFAULT '', license_expires_at TEXT NULL, billing TEXT NULL,
 notes TEXT NULL, logo TEXT NULL, revision INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX tenants_status_id ON agora_tenants(status,id);
CREATE INDEX tenants_license_expiry ON agora_tenants(license_expires_at);
CREATE TABLE agora_users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE COLLATE NOCASE, phone TEXT NOT NULL DEFAULT '', avatar TEXT NULL,
 password_hash TEXT NOT NULL, role TEXT NOT NULL, local_tenant_id INTEGER NULL REFERENCES agora_tenants(id), active INTEGER NOT NULL DEFAULT 1,
 auth_version INTEGER NOT NULL DEFAULT 1, last_login_at TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK (role IN ('MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','USUARIO','CONSULTA','VISOR')),
 CHECK ((role IN ('MASTER','SUPERADMIN') AND local_tenant_id IS NULL) OR (role NOT IN ('MASTER','SUPERADMIN') AND local_tenant_id IS NOT NULL))
);
CREATE UNIQUE INDEX users_single_owner ON agora_users(role) WHERE role='MASTER';
CREATE INDEX users_tenant_page ON agora_users(local_tenant_id,id);
CREATE INDEX users_tenant_active ON agora_users(local_tenant_id,active,id);
CREATE INDEX users_role_page ON agora_users(role,id);
CREATE INDEX users_tenant_login ON agora_users(local_tenant_id,last_login_at);
CREATE TABLE agora_assignments (
 user_id INTEGER NOT NULL REFERENCES agora_users(id) ON DELETE CASCADE, tenant_id INTEGER NOT NULL REFERENCES agora_tenants(id), PRIMARY KEY(user_id,tenant_id)
);
CREATE INDEX assignments_tenant_user ON agora_assignments(tenant_id,user_id);
CREATE TABLE agora_sessions (
 token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES agora_users(id) ON DELETE CASCADE, auth_version INTEGER NOT NULL, active_tenant_id INTEGER NULL,
 expires_at TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX sessions_expiry ON agora_sessions(expires_at);
CREATE INDEX sessions_user ON agora_sessions(user_id);
CREATE TABLE agora_rate_limits (bucket TEXT PRIMARY KEY, hits INTEGER NOT NULL, expires_at TEXT NOT NULL);
CREATE INDEX rate_expiry ON agora_rate_limits(expires_at);
CREATE TABLE agora_records (
 seq INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL REFERENCES agora_tenants(id), kind TEXT NOT NULL, uid TEXT NOT NULL,
 owner_id INTEGER NULL REFERENCES agora_users(id), label TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT '', period TEXT NOT NULL DEFAULT '',
 data TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(tenant_id,kind,uid)
);
CREATE INDEX records_list_page ON agora_records(tenant_id,kind,seq);
CREATE INDEX records_owned_page ON agora_records(tenant_id,kind,owner_id,seq);
CREATE INDEX records_period_page ON agora_records(tenant_id,kind,period,seq);
CREATE INDEX records_status_page ON agora_records(tenant_id,kind,status,seq);
CREATE TABLE agora_audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NULL, actor_id INTEGER NULL, actor_name TEXT NOT NULL,
 action TEXT NOT NULL, module TEXT NOT NULL, target TEXT NOT NULL DEFAULT '', details TEXT NOT NULL, ip TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX audit_tenant_time ON agora_audit(tenant_id,created_at,id);
CREATE INDEX audit_actor_time ON agora_audit(actor_id,created_at,id);
CREATE INDEX audit_time_id ON agora_audit(created_at,id);
CREATE TABLE agora_settings (
 tenant_id INTEGER NOT NULL DEFAULT 0, setting_key TEXT NOT NULL, data TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,setting_key)
);
CREATE TABLE agora_jobs (
 id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL REFERENCES agora_tenants(id), kind TEXT NOT NULL, payload TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'queued', attempts INTEGER NOT NULL DEFAULT 0, available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 locked_at TEXT NULL, last_error TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX jobs_pending ON agora_jobs(status,available_at,id);
CREATE INDEX jobs_tenant ON agora_jobs(tenant_id,id);
CREATE TABLE agora_signatures (
 token_hash TEXT PRIMARY KEY, tenant_id INTEGER NOT NULL REFERENCES agora_tenants(id), contract_uid TEXT NOT NULL, contract_version INTEGER NOT NULL,
 document_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used_at TEXT NULL, revoked_at TEXT NULL
);
CREATE INDEX signatures_contract ON agora_signatures(tenant_id,contract_uid);
CREATE INDEX signatures_expiry ON agora_signatures(expires_at);
CREATE TABLE agora_idempotency (
 tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL, request_key TEXT NOT NULL, request_hash TEXT NOT NULL, response TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(tenant_id,user_id,request_key)
);
CREATE INDEX idempotency_expiry ON agora_idempotency(created_at);
