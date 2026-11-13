-- TICA: payout account review + organiser authenticator 2FA.
-- Run once in Supabase > SQL Editor (project nviayeyourcmlziehmrj). Safe to run twice.

ALTER TABLE organizer_payout_accounts ADD COLUMN IF NOT EXISTS review_reason varchar(500) NULL;
ALTER TABLE organizer_payout_accounts ADD COLUMN IF NOT EXISTS reviewed_by_admin_id bigint NULL;
ALTER TABLE organizer_payout_accounts ADD COLUMN IF NOT EXISTS reviewed_at timestamp(0) without time zone NULL;

INSERT INTO migrations (migration, batch)
SELECT '2026_11_13_120000_add_review_fields_to_organizer_payout_accounts', COALESCE(MAX(batch), 0) + 1 FROM migrations
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_13_120000_add_review_fields_to_organizer_payout_accounts');

-- Authenticator-app two-factor for organisers (secret and recovery codes are stored encrypted by the app).
ALTER TABLE organizers ADD COLUMN IF NOT EXISTS totp_secret text NULL;
ALTER TABLE organizers ADD COLUMN IF NOT EXISTS totp_recovery_codes text NULL;
ALTER TABLE organizers ADD COLUMN IF NOT EXISTS totp_confirmed_at timestamp(0) without time zone NULL;
ALTER TABLE organizers ADD COLUMN IF NOT EXISTS totp_last_step bigint NULL;

INSERT INTO migrations (migration, batch)
SELECT '2026_11_13_130000_add_totp_to_organizers', COALESCE(MAX(batch), 0) + 1 FROM migrations
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_13_130000_add_totp_to_organizers');
