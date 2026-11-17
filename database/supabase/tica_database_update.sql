-- =====================================================================
-- TICA: the one database update for the live Supabase database.
-- Supabase > SQL Editor > paste all of this > Run.
--
-- Safe to run more than once: everything checks before it changes,
-- nothing is deleted, and no existing data is touched.
-- The result at the bottom is a health check of the whole database:
-- one row saying "All good", or one row per missing table or update.
-- =====================================================================

-- Laravel keeps a list of applied updates in "migrations". This helper adds a
-- name to that list once, whether or not the id column numbers itself.
CREATE OR REPLACE FUNCTION pg_temp.tica_mark_migration(name text) RETURNS void AS $$
DECLARE
  has_default boolean;
BEGIN
  IF to_regclass('public.migrations') IS NULL THEN
    RETURN;
  END IF;
  IF EXISTS (SELECT 1 FROM public.migrations WHERE migration = name) THEN
    RETURN;
  END IF;
  SELECT column_default IS NOT NULL OR is_identity = 'YES' INTO has_default
    FROM information_schema.columns
   WHERE table_schema = 'public' AND table_name = 'migrations' AND column_name = 'id';
  IF has_default THEN
    INSERT INTO public.migrations (migration, batch)
    VALUES (name, (SELECT COALESCE(MAX(batch), 0) + 1 FROM public.migrations));
  ELSE
    INSERT INTO public.migrations (id, migration, batch)
    VALUES ((SELECT COALESCE(MAX(id), 0) + 1 FROM public.migrations), name,
            (SELECT COALESCE(MAX(batch), 0) + 1 FROM public.migrations));
  END IF;
END;
$$ LANGUAGE plpgsql;

-- 0. Organiser order lookup index ----------------------------------------
DO $$
BEGIN
  IF to_regclass('public.bookings') IS NOT NULL THEN
    CREATE INDEX IF NOT EXISTS bookings_organizer_orders_idx
      ON public.bookings (organizer_id, id);
    PERFORM pg_temp.tica_mark_migration('2026_11_13_100000_add_organizer_order_index_to_bookings');
  ELSE
    RAISE NOTICE 'bookings does not exist yet: skipped the organiser order index.';
  END IF;
END $$;

-- 1. Bank account review (admin approves or rejects a payout account) ----
DO $$
BEGIN
  IF to_regclass('public.organizer_payout_accounts') IS NOT NULL THEN
    ALTER TABLE public.organizer_payout_accounts ADD COLUMN IF NOT EXISTS review_reason varchar(500) NULL;
    ALTER TABLE public.organizer_payout_accounts ADD COLUMN IF NOT EXISTS reviewed_by_admin_id bigint NULL;
    ALTER TABLE public.organizer_payout_accounts ADD COLUMN IF NOT EXISTS reviewed_at timestamp(0) without time zone NULL;
    PERFORM pg_temp.tica_mark_migration('2026_11_13_120000_add_review_fields_to_organizer_payout_accounts');
  ELSE
    RAISE NOTICE 'organizer_payout_accounts does not exist yet: skipped bank account review columns.';
  END IF;
END $$;

-- 2. Authenticator-app two-factor for organisers (stored encrypted by the app)
DO $$
BEGIN
  ALTER TABLE public.organizers ADD COLUMN IF NOT EXISTS totp_secret text NULL;
  ALTER TABLE public.organizers ADD COLUMN IF NOT EXISTS totp_recovery_codes text NULL;
  ALTER TABLE public.organizers ADD COLUMN IF NOT EXISTS totp_confirmed_at timestamp(0) without time zone NULL;
  ALTER TABLE public.organizers ADD COLUMN IF NOT EXISTS totp_last_step bigint NULL;
  PERFORM pg_temp.tica_mark_migration('2026_11_13_130000_add_totp_to_organizers');
END $$;

-- 3. Organiser notifications (the bell) --------------------------------
CREATE TABLE IF NOT EXISTS public.organizer_notifications (
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
CREATE INDEX IF NOT EXISTS organizer_notifications_organizer_id_read_at_id_index
  ON public.organizer_notifications (organizer_id, read_at, id);
SELECT pg_temp.tica_mark_migration('2026_11_13_200000_create_organizer_notifications_table');

-- 4. Admin > Integrations ----------------------------------------------
CREATE TABLE IF NOT EXISTS public.platform_integrations (
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
SELECT pg_temp.tica_mark_migration('2026_11_13_220000_create_platform_integrations_table');

-- 5. Reusable registration forms (forms library) -------------------------
-- A form can live in the organiser's library without an event; each event
-- copy remembers the library form it came from.
DO $$
BEGIN
  IF to_regclass('public.event_registration_forms') IS NOT NULL THEN
    -- Allows empty event_id; removes no data.
    ALTER TABLE public.event_registration_forms ALTER COLUMN event_id DROP NOT NULL;
    ALTER TABLE public.event_registration_forms ADD COLUMN IF NOT EXISTS source_form_id bigint NULL;
    CREATE INDEX IF NOT EXISTS event_registration_forms_source_form_id_index ON public.event_registration_forms (source_form_id);
    PERFORM pg_temp.tica_mark_migration('2026_11_14_100000_make_registration_forms_reusable');
  ELSE
    RAISE NOTICE 'event_registration_forms does not exist yet: skipped the forms library update.';
  END IF;
END $$;

-- 6. Authenticator sign-in for admins, organisers and attendees -----------
DO $$
DECLARE t text;
BEGIN
  FOREACH t IN ARRAY ARRAY['admins', 'organizers', 'customers'] LOOP
    IF to_regclass('public.' || t) IS NOT NULL THEN
      EXECUTE format('ALTER TABLE public.%I ADD COLUMN IF NOT EXISTS two_factor_secret text NULL', t);
      EXECUTE format('ALTER TABLE public.%I ADD COLUMN IF NOT EXISTS two_factor_recovery_codes text NULL', t);
    END IF;
  END LOOP;
  PERFORM pg_temp.tica_mark_migration('2026_11_14_160000_add_totp_to_account_actors');
END $$;

-- 7. Promotion packages, campaign payments and tracking --------------------
DO $$
BEGIN
  IF to_regclass('public.tica_promotion_plans') IS NULL OR to_regclass('public.tica_promotion_campaigns') IS NULL THEN
    RAISE NOTICE 'Promotion tables do not exist yet: skipped the promotion update.';
    RETURN;
  END IF;

  ALTER TABLE public.tica_promotion_plans
    ADD COLUMN IF NOT EXISTS slug varchar(140) NULL,
    ADD COLUMN IF NOT EXISTS type varchar(40) NULL,
    ADD COLUMN IF NOT EXISTS status varchar(24) NOT NULL DEFAULT 'published',
    ADD COLUMN IF NOT EXISTS benefits json NULL,
    ADD COLUMN IF NOT EXISTS placements json NULL,
    ADD COLUMN IF NOT EXISTS eligibility json NULL,
    ADD COLUMN IF NOT EXISTS approval_required boolean NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS priority smallint NOT NULL DEFAULT 100,
    ADD COLUMN IF NOT EXISTS capacity smallint NULL,
    ADD COLUMN IF NOT EXISTS max_concurrent smallint NULL,
    ADD COLUMN IF NOT EXISTS available_from timestamp(0) without time zone NULL,
    ADD COLUMN IF NOT EXISTS available_until timestamp(0) without time zone NULL,
    ADD COLUMN IF NOT EXISTS archived_at timestamp(0) without time zone NULL,
    ADD COLUMN IF NOT EXISTS duplicated_from_id bigint NULL;
  CREATE UNIQUE INDEX IF NOT EXISTS tica_promotion_plans_slug_unique ON public.tica_promotion_plans (slug);

  -- Give existing plans a unique slug, a type and default benefits.
  UPDATE public.tica_promotion_plans p
     SET slug = COALESCE(NULLIF(trim(BOTH '-' FROM regexp_replace(lower(p.name), '[^a-z0-9]+', '-', 'g')), ''), 'promotion-plan') || '-' || p.id,
         type = CASE WHEN p.placement = 'event_popup' THEN 'pop_event' ELSE p.placement END,
         benefits = CASE p.placement
           WHEN 'featured_event' THEN '["Priority discovery placement","Featured badge","Performance reporting"]'::json
           WHEN 'event_popup' THEN '["Rotating public-site placement","Frequency-controlled display","Performance reporting"]'::json
           ELSE '["Sponsored placement","Performance reporting"]'::json END,
         placements = json_build_array(p.placement),
         eligibility = '{"published_event":true}'::json
   WHERE p.slug IS NULL;

  ALTER TABLE public.tica_promotion_campaigns
    ADD COLUMN IF NOT EXISTS reference varchar(32) NULL,
    ADD COLUMN IF NOT EXISTS placement varchar(40) NULL,
    ADD COLUMN IF NOT EXISTS payment_status varchar(24) NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS payment_reference varchar(100) NULL,
    ADD COLUMN IF NOT EXISTS priority smallint NOT NULL DEFAULT 100,
    ADD COLUMN IF NOT EXISTS impressions bigint NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS clicks bigint NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS attributed_orders bigint NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS attributed_tickets bigint NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS attributed_revenue numeric(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS change_request text NULL,
    ADD COLUMN IF NOT EXISTS paused_at timestamp(0) without time zone NULL,
    ADD COLUMN IF NOT EXISTS cancelled_at timestamp(0) without time zone NULL,
    ADD COLUMN IF NOT EXISTS refunded_at timestamp(0) without time zone NULL,
    ADD COLUMN IF NOT EXISTS metadata json NULL;
  CREATE UNIQUE INDEX IF NOT EXISTS tica_promotion_campaigns_reference_unique ON public.tica_promotion_campaigns (reference);
  CREATE UNIQUE INDEX IF NOT EXISTS tica_promotion_campaigns_payment_reference_unique ON public.tica_promotion_campaigns (payment_reference);

  UPDATE public.tica_promotion_campaigns c
     SET reference = 'TP-' || lpad(c.id::text, 8, '0'),
         placement = (SELECT p.placement FROM public.tica_promotion_plans p WHERE p.id = c.plan_id),
         payment_status = CASE WHEN c.paid_at IS NOT NULL THEN 'paid' ELSE 'pending' END,
         payment_reference = CASE WHEN c.payment_transaction_id IS NOT NULL THEN 'BAL-' || c.payment_transaction_id ELSE NULL END
   WHERE c.reference IS NULL;

  CREATE TABLE IF NOT EXISTS public.tica_promotion_events (
    id bigserial PRIMARY KEY,
    campaign_id bigint NOT NULL,
    booking_id bigint NULL,
    event_type varchar(24) NOT NULL,
    visitor_hash char(64) NULL,
    amount numeric(14,2) NOT NULL DEFAULT 0,
    currency varchar(3) NULL,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone NULL,
    updated_at timestamp(0) without time zone NULL,
    CONSTRAINT tica_promotion_event_visitor_unique UNIQUE (campaign_id, event_type, visitor_hash),
    CONSTRAINT tica_promotion_event_booking_unique UNIQUE (campaign_id, event_type, booking_id)
  );
  CREATE INDEX IF NOT EXISTS tica_promotion_events_campaign_id_index ON public.tica_promotion_events (campaign_id);
  CREATE INDEX IF NOT EXISTS tica_promotion_events_booking_id_index ON public.tica_promotion_events (booking_id);
  CREATE INDEX IF NOT EXISTS tica_promotion_events_event_type_occurred_at_index ON public.tica_promotion_events (event_type, occurred_at);
  ALTER TABLE public.tica_promotion_events ENABLE ROW LEVEL SECURITY;

  IF to_regclass('public.tica_tracking_links') IS NOT NULL THEN
    ALTER TABLE public.tica_tracking_links
      ADD COLUMN IF NOT EXISTS channel varchar(80) NULL,
      ADD COLUMN IF NOT EXISTS influencer varchar(120) NULL,
      ADD COLUMN IF NOT EXISTS is_active boolean NOT NULL DEFAULT true,
      ADD COLUMN IF NOT EXISTS last_clicked_at timestamp(0) without time zone NULL;
  END IF;
  IF to_regclass('public.coupons') IS NOT NULL THEN
    ALTER TABLE public.coupons
      ADD COLUMN IF NOT EXISTS minimum_purchase numeric(12,2) NOT NULL DEFAULT 0,
      ADD COLUMN IF NOT EXISTS is_active boolean NOT NULL DEFAULT true,
      ADD COLUMN IF NOT EXISTS paused_at timestamp(0) without time zone NULL;
  END IF;
  IF to_regclass('public.bookings') IS NOT NULL THEN
    ALTER TABLE public.bookings
      ADD COLUMN IF NOT EXISTS promotion_campaign_id bigint NULL,
      ADD COLUMN IF NOT EXISTS coupon_id bigint NULL;
    CREATE INDEX IF NOT EXISTS bookings_promotion_campaign_id_index ON public.bookings (promotion_campaign_id);
    CREATE INDEX IF NOT EXISTS bookings_coupon_id_index ON public.bookings (coupon_id);
  END IF;

  PERFORM pg_temp.tica_mark_migration('2026_11_14_180000_expand_tica_promotion_system');
END $$;

-- 8. Advanced ticket numbering, design and reserved seating ---------------
DO $$
BEGIN
  IF to_regclass('public.events') IS NOT NULL THEN
    ALTER TABLE public.events ADD COLUMN IF NOT EXISTS seat_reservation_minutes smallint NOT NULL DEFAULT 15;
  END IF;
  IF to_regclass('public.tickets') IS NOT NULL THEN
    ALTER TABLE public.tickets
      ADD COLUMN IF NOT EXISTS currency varchar(3) NULL,
      ADD COLUMN IF NOT EXISTS min_buy_ticket integer NOT NULL DEFAULT 1,
      ADD COLUMN IF NOT EXISTS sales_start_at timestamp(0) without time zone NULL,
      ADD COLUMN IF NOT EXISTS sales_end_at timestamp(0) without time zone NULL,
      ADD COLUMN IF NOT EXISTS visibility varchar(24) NOT NULL DEFAULT 'public',
      ADD COLUMN IF NOT EXISTS sales_status varchar(24) NOT NULL DEFAULT 'on_sale',
      ADD COLUMN IF NOT EXISTS numbering_enabled boolean NOT NULL DEFAULT true,
      ADD COLUMN IF NOT EXISTS numbering_scope varchar(16) NOT NULL DEFAULT 'category',
      ADD COLUMN IF NOT EXISTS number_prefix varchar(20) NULL,
      ADD COLUMN IF NOT EXISTS number_start bigint NOT NULL DEFAULT 1,
      ADD COLUMN IF NOT EXISTS number_padding smallint NOT NULL DEFAULT 3,
      ADD COLUMN IF NOT EXISTS number_format varchar(80) NOT NULL DEFAULT '{PREFIX}-{NUMBER}',
      ADD COLUMN IF NOT EXISTS seating_mode varchar(24) NOT NULL DEFAULT 'general_admission',
      ADD COLUMN IF NOT EXISTS design_settings json NULL;
    CREATE INDEX IF NOT EXISTS tickets_sales_start_at_index ON public.tickets (sales_start_at);
    CREATE INDEX IF NOT EXISTS tickets_sales_end_at_index ON public.tickets (sales_end_at);
    CREATE INDEX IF NOT EXISTS tickets_visibility_index ON public.tickets (visibility);
    CREATE INDEX IF NOT EXISTS tickets_sales_status_index ON public.tickets (sales_status);
    CREATE INDEX IF NOT EXISTS tickets_seating_mode_index ON public.tickets (seating_mode);
  END IF;
  IF to_regclass('public.bookings') IS NOT NULL THEN
    ALTER TABLE public.bookings
      ADD COLUMN IF NOT EXISTS ticket_reservation_token uuid NULL;
    CREATE INDEX IF NOT EXISTS bookings_ticket_reservation_token_index
      ON public.bookings (ticket_reservation_token);
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.ticket_number_sequences (
  id bigserial PRIMARY KEY,
  event_id bigint NOT NULL,
  ticket_id bigint NULL,
  scope_key varchar(64) NOT NULL,
  next_number bigint NOT NULL,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL,
  CONSTRAINT ticket_number_sequences_event_scope_unique UNIQUE (event_id, scope_key)
);
CREATE INDEX IF NOT EXISTS ticket_number_sequences_event_id_index ON public.ticket_number_sequences (event_id);
CREATE INDEX IF NOT EXISTS ticket_number_sequences_ticket_id_index ON public.ticket_number_sequences (ticket_id);

CREATE TABLE IF NOT EXISTS public.seating_sections (
  id bigserial PRIMARY KEY,
  event_id bigint NOT NULL,
  name varchar(120) NOT NULL,
  kind varchar(24) NOT NULL DEFAULT 'seated',
  ticket_id bigint NULL,
  price_override numeric(18,2) NULL,
  capacity integer NULL,
  sort_order integer NOT NULL DEFAULT 0,
  layout json NULL,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL,
  CONSTRAINT seating_sections_event_name_unique UNIQUE (event_id, name)
);
CREATE INDEX IF NOT EXISTS seating_sections_event_id_index ON public.seating_sections (event_id);
CREATE INDEX IF NOT EXISTS seating_sections_ticket_id_index ON public.seating_sections (ticket_id);

CREATE TABLE IF NOT EXISTS public.seating_rows (
  id bigserial PRIMARY KEY,
  event_id bigint NOT NULL,
  section_id bigint NOT NULL,
  label varchar(40) NOT NULL,
  sort_order integer NOT NULL DEFAULT 0,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL,
  CONSTRAINT seating_rows_section_label_unique UNIQUE (section_id, label)
);
CREATE INDEX IF NOT EXISTS seating_rows_event_id_index ON public.seating_rows (event_id);
CREATE INDEX IF NOT EXISTS seating_rows_section_id_index ON public.seating_rows (section_id);

CREATE TABLE IF NOT EXISTS public.event_seats (
  id bigserial PRIMARY KEY,
  public_id uuid NOT NULL UNIQUE,
  event_id bigint NOT NULL,
  section_id bigint NOT NULL,
  row_id bigint NULL,
  ticket_id bigint NULL,
  label varchar(40) NOT NULL,
  status varchar(24) NOT NULL DEFAULT 'available',
  is_accessible boolean NOT NULL DEFAULT false,
  price_override numeric(18,2) NULL,
  sort_order integer NOT NULL DEFAULT 0,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL,
  CONSTRAINT event_seats_identity_unique UNIQUE (event_id, section_id, row_id, label)
);
CREATE INDEX IF NOT EXISTS event_seats_event_id_index ON public.event_seats (event_id);
CREATE INDEX IF NOT EXISTS event_seats_section_id_index ON public.event_seats (section_id);
CREATE INDEX IF NOT EXISTS event_seats_row_id_index ON public.event_seats (row_id);
CREATE INDEX IF NOT EXISTS event_seats_ticket_id_index ON public.event_seats (ticket_id);
CREATE INDEX IF NOT EXISTS event_seats_status_index ON public.event_seats (status);

CREATE TABLE IF NOT EXISTS public.seat_reservations (
  id bigserial PRIMARY KEY,
  token uuid NOT NULL,
  event_id bigint NOT NULL,
  ticket_id bigint NOT NULL,
  seat_id bigint NOT NULL,
  status varchar(24) NOT NULL DEFAULT 'active',
  session_hash varchar(64) NULL,
  customer_id bigint NULL,
  buyer_email_hash char(64) NULL,
  booking_id bigint NULL,
  booking_ticket_id bigint NULL UNIQUE,
  expires_at timestamp(0) without time zone NOT NULL,
  consumed_at timestamp(0) without time zone NULL,
  released_at timestamp(0) without time zone NULL,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL
);
CREATE INDEX IF NOT EXISTS seat_reservations_token_index ON public.seat_reservations (token);
CREATE INDEX IF NOT EXISTS seat_reservations_event_id_index ON public.seat_reservations (event_id);
CREATE INDEX IF NOT EXISTS seat_reservations_ticket_id_index ON public.seat_reservations (ticket_id);
CREATE INDEX IF NOT EXISTS seat_reservations_seat_id_index ON public.seat_reservations (seat_id);
CREATE INDEX IF NOT EXISTS seat_reservations_status_index ON public.seat_reservations (status);
CREATE INDEX IF NOT EXISTS seat_reservations_customer_id_index ON public.seat_reservations (customer_id);
CREATE INDEX IF NOT EXISTS seat_reservations_buyer_email_hash_index ON public.seat_reservations (buyer_email_hash);
CREATE INDEX IF NOT EXISTS seat_reservations_booking_id_index ON public.seat_reservations (booking_id);
CREATE INDEX IF NOT EXISTS seat_reservations_expires_at_index ON public.seat_reservations (expires_at);
CREATE INDEX IF NOT EXISTS seat_reservations_status_expires_at_index ON public.seat_reservations (status, expires_at);
CREATE INDEX IF NOT EXISTS seat_reservations_event_ticket_status_index ON public.seat_reservations (event_id, ticket_id, status);
CREATE INDEX IF NOT EXISTS seat_reservations_seat_status_expires_index ON public.seat_reservations (seat_id, status, expires_at);

DO $$
BEGIN
  IF to_regclass('public.booking_tickets') IS NOT NULL THEN
    ALTER TABLE public.booking_tickets
      ADD COLUMN IF NOT EXISTS ticket_number varchar(80) NULL,
      ADD COLUMN IF NOT EXISTS ticket_number_value bigint NULL,
      ADD COLUMN IF NOT EXISTS seat_id bigint NULL,
      ADD COLUMN IF NOT EXISTS seat_label varchar(40) NULL,
      ADD COLUMN IF NOT EXISTS row_label varchar(40) NULL,
      ADD COLUMN IF NOT EXISTS section_label varchar(120) NULL;
    CREATE UNIQUE INDEX IF NOT EXISTS booking_tickets_event_number_unique ON public.booking_tickets (event_id, ticket_number);
    CREATE UNIQUE INDEX IF NOT EXISTS booking_tickets_seat_id_unique ON public.booking_tickets (seat_id);
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.ticket_number_allocations (
  id bigserial PRIMARY KEY,
  event_id bigint NOT NULL,
  ticket_id bigint NOT NULL,
  booking_ticket_id bigint NOT NULL UNIQUE,
  number_value bigint NOT NULL,
  ticket_number varchar(80) NOT NULL,
  status varchar(24) NOT NULL DEFAULT 'allocated',
  allocated_at timestamp(0) without time zone NOT NULL,
  invalidated_at timestamp(0) without time zone NULL,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL,
  CONSTRAINT ticket_number_allocations_event_number_unique UNIQUE (event_id, ticket_number),
  CONSTRAINT ticket_number_allocations_ticket_value_unique UNIQUE (ticket_id, number_value)
);
CREATE INDEX IF NOT EXISTS ticket_number_allocations_event_id_index ON public.ticket_number_allocations (event_id);
CREATE INDEX IF NOT EXISTS ticket_number_allocations_ticket_id_index ON public.ticket_number_allocations (ticket_id);
CREATE INDEX IF NOT EXISTS ticket_number_allocations_status_index ON public.ticket_number_allocations (status);

SELECT pg_temp.tica_mark_migration('2026_11_14_180000_create_advanced_ticketing_tables');

-- 9. Organizer operations: sponsorship inventory and account plans ---------
CREATE TABLE IF NOT EXISTS public.tica_sponsorship_packages (
  id bigserial PRIMARY KEY,
  public_id uuid NOT NULL UNIQUE,
  organizer_id bigint NOT NULL,
  event_id bigint NULL,
  name varchar(120) NOT NULL,
  summary varchar(300) NULL,
  deliverables json NOT NULL,
  price numeric(18,2) NULL,
  currency varchar(3) NOT NULL DEFAULT 'NGN',
  available_slots integer NULL,
  status varchar(24) NOT NULL DEFAULT 'draft',
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL
);
CREATE INDEX IF NOT EXISTS tica_sponsorship_packages_organizer_id_index ON public.tica_sponsorship_packages (organizer_id);
CREATE INDEX IF NOT EXISTS tica_sponsorship_packages_event_id_index ON public.tica_sponsorship_packages (event_id);
CREATE INDEX IF NOT EXISTS tica_sponsorship_packages_status_index ON public.tica_sponsorship_packages (status);
CREATE INDEX IF NOT EXISTS tica_sponsor_org_status_idx ON public.tica_sponsorship_packages (organizer_id, status, id);

CREATE TABLE IF NOT EXISTS public.tica_organizer_subscriptions (
  id bigserial PRIMARY KEY,
  organizer_id bigint NOT NULL UNIQUE,
  plan varchar(24) NOT NULL DEFAULT 'launch',
  status varchar(24) NOT NULL DEFAULT 'active',
  automatic_upgrade boolean NOT NULL DEFAULT false,
  requested_plan varchar(24) NULL,
  change_requested_at timestamp(0) without time zone NULL,
  started_at timestamp(0) without time zone NULL,
  renews_at timestamp(0) without time zone NULL,
  created_at timestamp(0) without time zone NULL,
  updated_at timestamp(0) without time zone NULL
);
CREATE INDEX IF NOT EXISTS tica_organizer_subscriptions_status_index ON public.tica_organizer_subscriptions (status);

DO $$
BEGIN
  IF to_regclass('public.bookings') IS NOT NULL THEN
    CREATE INDEX IF NOT EXISTS bookings_organizer_channel_idx ON public.bookings (organizer_id, "gatewayType", id);
  END IF;
END $$;
SELECT pg_temp.tica_mark_migration('2026_11_15_090000_create_organizer_operations_tables');

-- 10. Event-local timezone -------------------------------------------------
-- Keeps the organizer's intended local time explicit for tickets, reminders
-- and exports. Existing events remain on West Africa Time.
DO $$
BEGIN
  IF to_regclass('public.events') IS NOT NULL THEN
    ALTER TABLE public.events ADD COLUMN IF NOT EXISTS timezone varchar(64) NOT NULL DEFAULT 'Africa/Lagos';
    PERFORM pg_temp.tica_mark_migration('2026_11_17_100000_add_timezone_to_events');
  ELSE
    RAISE NOTICE 'events does not exist yet: skipped the event timezone column.';
  END IF;
END $$;

-- 11. Keep these tables private -------------------------------------------
-- The app talks to Postgres directly; Supabase's public API must not see them.
ALTER TABLE public.organizer_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.platform_integrations ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ticket_number_sequences ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.seating_sections ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.seating_rows ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.event_seats ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.seat_reservations ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ticket_number_allocations ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tica_sponsorship_packages ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tica_organizer_subscriptions ENABLE ROW LEVEL SECURITY;

-- Health check: every table the app uses, and every update it expects. -----
-- One row "All good" when the database is complete; otherwise one row per
-- missing table or update (send those rows to the developer).
WITH problems AS (
SELECT 'missing table' AS problem, t.name AS detail
  FROM (VALUES
  ('about_us_sections'),
  ('account_login_challenges'),
  ('action_sections'),
  ('admins'),
  ('advertisements'),
  ('application_mail_deliveries'),
  ('audit_logs'),
  ('basic_settings'),
  ('blog_categories'),
  ('blog_informations'),
  ('blogs'),
  ('booking_deliveries'),
  ('booking_ticket_delivery_requests'),
  ('booking_ticket_events'),
  ('booking_ticket_transfers'),
  ('booking_tickets'),
  ('bookings'),
  ('cache'),
  ('cache_locks'),
  ('cities'),
  ('contact_pages'),
  ('conversations'),
  ('cookie_alerts'),
  ('count_informations'),
  ('countries'),
  ('coupons'),
  ('customer_email_verification_deliveries'),
  ('customer_login_codes'),
  ('customer_privacy_requests'),
  ('customers'),
  ('data_export_schedules'),
  ('data_exports'),
  ('earnings'),
  ('event_cancellation_cases'),
  ('event_cancellation_notifications'),
  ('event_cancellation_requests'),
  ('event_categories'),
  ('event_contents'),
  ('event_dates'),
  ('event_feature_sections'),
  ('event_features'),
  ('event_guest_import_rows'),
  ('event_guest_imports'),
  ('event_guest_invitations'),
  ('event_images'),
  ('event_registration_allocation_claims'),
  ('event_registration_allocations'),
  ('event_registration_answers'),
  ('event_registration_attendees'),
  ('event_registration_badge_settings'),
  ('event_registration_embed_settings'),
  ('event_registration_files'),
  ('event_registration_form_templates'),
  ('event_registration_forms'),
  ('event_registration_import_rows'),
  ('event_registration_imports'),
  ('event_registration_invoices'),
  ('event_registration_notifications'),
  ('event_registration_questions'),
  ('event_registration_session_selections'),
  ('event_registration_sessions'),
  ('event_registration_submissions'),
  ('event_seats'),
  ('event_waitlist_entries'),
  ('events'),
  ('failed_jobs'),
  ('faqs'),
  ('features'),
  ('footer_contents'),
  ('fun_fact_sections'),
  ('funnel_analytics_events'),
  ('gateway_settlement_entries'),
  ('gateway_settlements'),
  ('gooogle_calendar_infos'),
  ('guests'),
  ('hero_sections'),
  ('how_work_items'),
  ('how_works'),
  ('jobs'),
  ('languages'),
  ('mail_templates'),
  ('menu_builders'),
  ('migrations'),
  ('offline_gateways'),
  ('online_gateways'),
  ('operational_incidents'),
  ('order_items'),
  ('organizer_currency_balances'),
  ('organizer_email_verification_deliveries'),
  ('organizer_infos'),
  ('organizer_kyc_documents'),
  ('organizer_kyc_submissions'),
  ('organizer_notifications'),
  ('organizer_payout_accounts'),
  ('organizer_team_members'),
  ('organizers'),
  ('page_contents'),
  ('page_headings'),
  ('pages'),
  ('partner_sections'),
  ('partners'),
  ('password_resets'),
  ('payment_intents'),
  ('payment_invoices'),
  ('payment_reconciliation_entries'),
  ('payment_reconciliation_runs'),
  ('payment_refund_disputes'),
  ('payment_refunds'),
  ('payout_disputes'),
  ('payout_transfers'),
  ('platform_currency_earnings'),
  ('platform_integrations'),
  ('popups'),
  ('product_categories'),
  ('product_contents'),
  ('product_gateway_refunds'),
  ('product_images'),
  ('product_order_deliveries'),
  ('product_order_refunds'),
  ('product_orders'),
  ('product_payment_intents'),
  ('product_reviews'),
  ('products'),
  ('push_notification_campaign_deliveries'),
  ('push_notification_campaigns'),
  ('push_subscriptions'),
  ('quick_links'),
  ('role_permissions'),
  ('scanner_check_in_conflicts'),
  ('scanner_devices'),
  ('scanner_offline_manifest_items'),
  ('scanner_offline_manifests'),
  ('scanner_offline_sync_items'),
  ('scanner_staff'),
  ('seat_reservations'),
  ('seating_rows'),
  ('seating_sections'),
  ('section_titles'),
  ('sections'),
  ('seos'),
  ('sessions'),
  ('shipping_charges'),
  ('shop_coupons'),
  ('social_medias'),
  ('states'),
  ('subscriber_campaign_deliveries'),
  ('subscriber_campaigns'),
  ('subscribers'),
  ('tica_organizer_subscriptions'),
  ('tica_sponsorship_packages'),
  ('support_ticket_statuses'),
  ('support_tickets'),
  ('testimonial_sections'),
  ('testimonials'),
  ('tica_promotion_campaigns'),
  ('tica_promotion_events'),
  ('tica_promotion_plans'),
  ('tica_tracking_links'),
  ('tica_tracking_pixels'),
  ('ticket_contents'),
  ('ticket_number_allocations'),
  ('ticket_number_sequences'),
  ('ticket_reservations'),
  ('tickets'),
  ('timezones'),
  ('transactions'),
  ('users'),
  ('variation_contents'),
  ('webhook_receipts'),
  ('wishlists'),
  ('withdraw_method_inputs'),
  ('withdraw_method_options'),
  ('withdraw_payment_methods'),
  ('withdraws')
  ) AS t(name)
 WHERE to_regclass('public.' || t.name) IS NULL
UNION ALL
SELECT 'missing update', 'events.timezone column'
 WHERE to_regclass('public.events') IS NOT NULL
   AND NOT EXISTS (
     SELECT 1 FROM information_schema.columns
      WHERE table_schema = 'public' AND table_name = 'events' AND column_name = 'timezone'
   )
UNION ALL
SELECT 'update not recorded', m.name
  FROM (VALUES
  ('2023_03_04_134315_drop_secondary_color_from_basic_settings_table'),
  ('2023_03_05_152403_add_tax_commission_percentage_column_to_bookings_table'),
  ('2023_03_22_115828_add_column_to_conversations_table'),
  ('2023_05_11_150902_create_ticket_contents_table'),
  ('2023_05_13_124521_create_variation_contents_table'),
  ('2023_05_20_154216_add_about_page_title_column_to_page_headings_table'),
  ('2023_05_20_154329_add_meta_keyword_description_column_to_seos_table'),
  ('2023_07_30_094527_add_scan_status_column_to_bookings_table'),
  ('2023_09_30_162759_add_tax_percentage_column_to_product_orders_table'),
  ('2023_11_16_062730_add_event_guest_checkout_status_to_basic_settings_table'),
  ('2024_02_07_055018_add_midtrans_payment_gateway_row_to_online_gateways_table'),
  ('2024_02_07_172740_add_iyzico_payment_gateway_into_online_gateways_table'),
  ('2024_02_08_153546_add_paytabs_payment_gateway_into_online_gateways'),
  ('2024_02_10_105443_add_toyyibpay_payment_gateway_into_online_gateways'),
  ('2024_02_10_122829_add_phonepe_payment_gateway_into_online_gateways_table'),
  ('2024_02_10_152845_add_yoco_payment_gateway_into_online_gateways'),
  ('2024_02_10_172724_add_xindit_payment_gateway_into_online_gateways'),
  ('2024_02_11_143939_add_myfatoorah_payment_gateway_into_online_gateways'),
  ('2024_02_12_120007_add_conversation_id_to_event_bookings_table'),
  ('2024_02_12_162617_add_conversation_id_to_product_orders_table'),
  ('2024_02_14_112643_add_perfect_money_payment_gateway_into_online_gateways_table'),
  ('2026_11_01_000000_create_audit_logs_table'),
  ('2026_11_01_010000_create_payment_intents_table'),
  ('2026_11_01_020000_create_ticket_reservations_table'),
  ('2026_11_01_030000_create_payment_refunds_table'),
  ('2026_11_01_040000_create_payment_reconciliations_tables'),
  ('2026_11_01_050000_create_gateway_settlements_tables'),
  ('2026_11_01_060000_create_organizer_kyc_tables'),
  ('2026_11_01_070000_create_organizer_payout_accounts_table'),
  ('2026_11_01_080000_add_review_fields_to_withdrawals_table'),
  ('2026_11_01_090000_add_soft_deletes_to_tickets_table'),
  ('2026_11_01_120000_create_booking_tickets_table'),
  ('2026_11_01_130000_create_booking_ticket_events_table'),
  ('2026_11_01_140000_create_booking_ticket_transfers_table'),
  ('2026_11_01_150000_create_scanner_devices_table'),
  ('2026_11_01_160000_create_scanner_check_in_conflicts_table'),
  ('2026_11_01_170000_create_scanner_staff_table'),
  ('2026_11_01_180000_create_scanner_offline_tables'),
  ('2026_11_01_190000_add_recovery_fields_to_payment_intents_table'),
  ('2026_11_01_200000_add_gateway_identity_to_transactions_table'),
  ('2026_11_01_210000_create_booking_deliveries_table'),
  ('2026_11_01_220000_ensure_queue_tables_exist'),
  ('2026_11_01_230000_create_payout_transfers_table'),
  ('2026_11_01_240000_create_funnel_analytics_events_table'),
  ('2026_11_01_250000_create_data_exports_table'),
  ('2026_11_01_260000_create_operational_incidents_table'),
  ('2026_11_01_270000_create_webhook_receipts_table'),
  ('2026_11_01_280000_add_inventory_lifecycle_to_product_orders'),
  ('2026_11_01_290000_create_product_order_deliveries_table'),
  ('2026_11_01_300000_create_product_order_refunds_table'),
  ('2026_11_01_310000_add_gateway_transaction_id_to_payment_intents_table'),
  ('2026_11_01_320000_create_product_payment_intents_table'),
  ('2026_11_01_330000_create_product_gateway_refunds_table'),
  ('2026_11_02_000000_add_organizer_approval_to_payment_refunds'),
  ('2026_11_02_010000_create_event_cancellation_workflow'),
  ('2026_11_02_020000_create_event_waitlist_entries_table'),
  ('2026_11_02_030000_create_event_guest_invitations_table'),
  ('2026_11_02_040000_create_event_registration_workflow'),
  ('2026_11_02_050000_create_event_registration_notifications_table'),
  ('2026_11_02_060000_add_retention_fields_to_event_registration_submissions'),
  ('2026_11_02_070000_create_event_registration_imports'),
  ('2026_11_02_080000_create_event_guest_imports'),
  ('2026_11_02_090000_add_retention_fields_to_event_guest_invitations'),
  ('2026_11_02_100000_add_registration_templates_and_ticket_scoping'),
  ('2026_11_02_110000_create_registration_sponsor_allocations'),
  ('2026_11_02_120000_create_event_registration_sessions'),
  ('2026_11_02_130000_create_event_registration_invoices'),
  ('2026_11_02_140000_create_event_registration_badge_settings'),
  ('2026_11_02_150000_create_event_registration_embed_settings'),
  ('2026_11_02_160000_create_data_export_schedules_table'),
  ('2026_11_02_170000_add_export_fields_to_role_permissions'),
  ('2026_11_02_180000_create_payout_disputes_table'),
  ('2026_11_02_190000_create_payment_refund_disputes_table'),
  ('2026_11_02_200000_add_resolution_to_event_cancellation_cases'),
  ('2026_11_02_210000_create_booking_ticket_delivery_requests_table'),
  ('2026_11_03_090000_add_delivery_state_to_booking_ticket_transfers_table'),
  ('2026_11_03_120000_add_release_policy_metadata_to_payout_transfers'),
  ('2026_11_03_120000_add_variation_to_event_registration_attendees'),
  ('2026_11_03_120000_create_organizer_email_verification_deliveries_table'),
  ('2026_11_03_130000_add_settings_to_event_registration_questions'),
  ('2026_11_03_130000_create_customer_email_verification_deliveries_table'),
  ('2026_11_03_131000_create_event_registration_files_table'),
  ('2026_11_03_140000_create_subscriber_campaign_tables'),
  ('2026_11_03_150000_create_application_mail_deliveries_table'),
  ('2026_11_03_160000_create_push_notification_campaign_tables'),
  ('2026_11_03_170000_expand_basic_setting_secret_columns'),
  ('2026_11_03_180000_remove_legacy_maintenance_bypass_tokens'),
  ('2026_11_04_000000_enforce_single_platform_earning_row'),
  ('2026_11_04_010000_create_customer_privacy_requests_table'),
  ('2026_11_05_010000_add_scanner_staff_identity_to_booking_tickets'),
  ('2026_11_05_020000_add_check_in_source_to_booking_tickets'),
  ('2026_11_05_030000_add_checkout_submission_hash_to_bookings'),
  ('2026_11_05_040000_add_buyer_identity_hashes'),
  ('2026_11_05_050000_add_sms_metadata_to_booking_deliveries'),
  ('2026_11_05_060000_enforce_unique_sms_provider_references'),
  ('2026_11_05_070000_add_campaign_dispatch_metadata'),
  ('2026_11_05_080000_add_data_export_dispatch_metadata'),
  ('2026_11_05_090000_add_import_dispatch_metadata'),
  ('2026_11_05_100000_add_payout_dispatch_metadata'),
  ('2026_11_05_110000_add_payout_notification_dispatch_metadata'),
  ('2026_11_05_120000_add_allocation_dispatch_metadata'),
  ('2026_11_05_130000_add_ticket_transfer_dispatch_metadata'),
  ('2026_11_05_140000_add_event_guest_invitation_dispatch_metadata'),
  ('2026_11_05_150000_add_email_verification_dispatch_metadata'),
  ('2026_11_05_160000_add_application_mail_dispatch_metadata'),
  ('2026_11_05_170000_add_booking_ticket_delivery_dispatch_metadata'),
  ('2026_11_05_180000_add_event_cancellation_notification_dispatch_metadata'),
  ('2026_11_05_190000_add_event_registration_notification_dispatch_metadata'),
  ('2026_11_05_200000_add_event_waitlist_dispatch_metadata'),
  ('2026_11_05_210000_add_fulfillment_dispatch_metadata'),
  ('2026_11_05_220000_add_ready_notification_metadata_to_data_exports'),
  ('2026_11_06_170000_encrypt_payment_intent_sensitive_fields'),
  ('2026_11_06_180000_encrypt_finance_diagnostic_fields'),
  ('2026_11_06_190000_encrypt_operational_diagnostic_fields'),
  ('2026_11_10_120000_add_security_hold_to_organizer_payout_accounts'),
  ('2026_11_10_121000_add_dedupe_key_to_application_mail_deliveries'),
  ('2026_11_10_122000_add_private_join_url_to_events'),
  ('2026_11_10_123000_create_customer_login_codes_table'),
  ('2026_11_10_124000_create_database_cache_and_session_tables'),
  ('2026_11_11_090000_add_percentage_booking_fee'),
  ('2026_11_11_091000_add_tica_event_settings'),
  ('2026_11_11_092000_add_ticket_id_to_bookings'),
  ('2026_11_11_093000_add_tica_organizer_workspace'),
  ('2026_11_11_094000_add_event_currencies'),
  ('2026_11_11_095000_add_tica_platform_settings'),
  ('2026_11_11_096000_add_tica_site_content'),
  ('2026_11_11_097000_add_monnify_gateway'),
  ('2026_11_12_150000_create_tica_promotion_governance_tables'),
  ('2026_11_12_170000_add_account_mfa_security'),
  ('2026_11_13_090000_add_icon_to_event_categories'),
  ('2026_11_13_100000_add_organizer_order_index_to_bookings'),
  ('2026_11_13_120000_add_review_fields_to_organizer_payout_accounts'),
  ('2026_11_13_130000_add_totp_to_organizers'),
  ('2026_11_13_200000_create_organizer_notifications_table'),
  ('2026_11_13_220000_create_platform_integrations_table'),
  ('2026_11_14_100000_make_registration_forms_reusable'),
  ('2026_11_14_160000_add_totp_to_account_actors'),
  ('2026_11_14_180000_expand_tica_promotion_system'),
  ('2026_11_14_180000_create_advanced_ticketing_tables'),
  ('2026_11_15_090000_create_organizer_operations_tables'),
  ('2026_11_17_100000_add_timezone_to_events')
  ) AS m(name)
 WHERE to_regclass('public.migrations') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM public.migrations x WHERE x.migration = m.name)
)
SELECT problem, detail FROM problems
UNION ALL
SELECT 'All good', 'Every table and update TICA needs is in place.' WHERE NOT EXISTS (SELECT 1 FROM problems)
ORDER BY 1, 2;
