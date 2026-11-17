-- API key grace expiry (2026-11-17)
-- Lets sharex-config.php rotate ShareX credentials without instantly revoking
-- the previous configuration: rotated keys keep working for 48 more hours and are
-- cleaned up a week later. Safe to run once; requires no downtime.
ALTER TABLE api_keys
    ADD COLUMN expires_at TIMESTAMP NULL DEFAULT NULL,
    ADD INDEX idx_api_keys_expires_at (expires_at);
