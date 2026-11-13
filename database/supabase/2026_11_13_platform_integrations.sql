-- TICA: Admin > Integrations (SMS, web push, storage and payout extras; last test of each integration).
-- Run once in Supabase > SQL Editor (project nviayeyourcmlziehmrj). Safe to run twice.
-- Until this runs, Admin > Integrations still shows and edits payment, email and sign-in settings;
-- the extra settings show "needs the database update".

CREATE TABLE IF NOT EXISTS platform_integrations (
  key varchar(60) PRIMARY KEY,
  enabled boolean NOT NULL DEFAULT false,
  settings text NULL,
  tested_at timestamp(0) without time zone NULL,
  test_ok boolean NULL,
  test_message varchar(500) NULL,
  updated_by_admin_id bigint NULL,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL
);

INSERT INTO migrations (migration, batch)
SELECT '2026_11_13_220000_create_platform_integrations_table', COALESCE(MAX(batch), 0) + 1 FROM migrations
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_13_220000_create_platform_integrations_table');
