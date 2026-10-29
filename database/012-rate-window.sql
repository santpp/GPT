-- Replaces the fixed one-second gap with a rolling burst window per connection.
ALTER TABLE ghl_sync_state
 ADD COLUMN IF NOT EXISTS rate_window_start BIGINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS rate_window_count INT UNSIGNED NOT NULL DEFAULT 0;
