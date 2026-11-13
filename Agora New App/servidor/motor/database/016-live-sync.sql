-- Near-live updates without webhooks. The browser asks Ágora, not the account:
-- a pulse reads these columns and only changes when the conversations really
-- changed, so a screen refreshes when there is something new instead of every
-- few seconds. One shared pass per connection reads the account, whatever the
-- number of people looking, and `pulse_next_at` is what spaces those passes.
ALTER TABLE ghl_sync_state
 ADD COLUMN IF NOT EXISTS pulse_next_at BIGINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE conversation_index
 ADD COLUMN IF NOT EXISTS content_hash CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN IF NOT EXISTS content_changed_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 ADD COLUMN IF NOT EXISTS unread_count INT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS assigned_to VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL;
