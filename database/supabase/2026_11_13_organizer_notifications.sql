-- TICA: in-app notifications for organisers (the bell in the organiser dashboard).
-- Run once in Supabase > SQL Editor (project nviayeyourcmlziehmrj). Safe to run twice.
-- Until this runs, the bell simply stays empty; nothing else changes.

CREATE TABLE IF NOT EXISTS organizer_notifications (
  id bigserial PRIMARY KEY,
  organizer_id bigint NOT NULL,
  kind varchar(40) NOT NULL,
  title varchar(191) NOT NULL,
  body varchar(500) NULL,
  url varchar(500) NULL,
  read_at timestamp(0) without time zone NULL,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL
);
CREATE INDEX IF NOT EXISTS organizer_notifications_organizer_id_read_at_id_index ON organizer_notifications (organizer_id, read_at, id);

INSERT INTO migrations (migration, batch)
SELECT '2026_11_13_200000_create_organizer_notifications_table', COALESCE(MAX(batch), 0) + 1 FROM migrations
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_13_200000_create_organizer_notifications_table');
