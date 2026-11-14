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

-- 5. Keep these tables private --------------------------------------------
-- The app talks to Postgres directly; Supabase's public API must not see them.
ALTER TABLE public.organizer_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.platform_integrations ENABLE ROW LEVEL SECURITY;

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
  ('support_ticket_statuses'),
  ('support_tickets'),
  ('testimonial_sections'),
  ('testimonials'),
  ('tica_promotion_campaigns'),
  ('tica_promotion_plans'),
  ('tica_tracking_links'),
  ('tica_tracking_pixels'),
  ('ticket_contents'),
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
  ('2026_11_13_220000_create_platform_integrations_table')
  ) AS m(name)
 WHERE to_regclass('public.migrations') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM public.migrations x WHERE x.migration = m.name)
)
SELECT problem, detail FROM problems
UNION ALL
SELECT 'All good', 'Every table and update TICA needs is in place.' WHERE NOT EXISTS (SELECT 1 FROM problems)
ORDER BY 1, 2;
