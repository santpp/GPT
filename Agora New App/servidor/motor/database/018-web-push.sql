-- Notifications on the phone without an APK. A browser subscription is an
-- address plus two keys; the address is long, so the column grows and the keys
-- get their own place. `last_sent_at` is what the diagnostics row shows.
ALTER TABLE push_devices
 MODIFY COLUMN token VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 ADD COLUMN IF NOT EXISTS p256dh VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN IF NOT EXISTS auth VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN IF NOT EXISTS last_sent_at TIMESTAMP NULL DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS label VARCHAR(80) NULL;
