ALTER TABLE agora_tenants ADD COLUMN plan VARCHAR(80) NOT NULL DEFAULT '' AFTER industry, ADD COLUMN license_expires_at DATE NULL AFTER plan, ADD KEY license_expiry(license_expires_at);
ALTER TABLE agora_users ADD COLUMN last_login_at DATETIME NULL AFTER auth_version, ADD KEY tenant_login(local_tenant_id,last_login_at)
