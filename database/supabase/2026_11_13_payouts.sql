-- TICA payouts: admin review of payout accounts whose bank name didn't match.
-- Run once in Supabase > SQL Editor (project nviayeyourcmlziehmrj). Safe to run twice.

ALTER TABLE organizer_payout_accounts ADD COLUMN IF NOT EXISTS review_reason varchar(500) NULL;
ALTER TABLE organizer_payout_accounts ADD COLUMN IF NOT EXISTS reviewed_by_admin_id bigint NULL;
ALTER TABLE organizer_payout_accounts ADD COLUMN IF NOT EXISTS reviewed_at timestamp(0) without time zone NULL;

INSERT INTO migrations (migration, batch)
SELECT '2026_11_13_120000_add_review_fields_to_organizer_payout_accounts', COALESCE(MAX(batch), 0) + 1 FROM migrations
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_13_120000_add_review_fields_to_organizer_payout_accounts');
