<?php

namespace App\Services;

class ReleaseReadinessService
{
    public function legacyPublicDocumentsCheck(
        string $id,
        string $label,
        int $legacyReferences,
        ?int $publicFiles,
        string $migrationCommand,
    ): array {
        if ($publicFiles === null) {
            return [
                'id' => $id,
                'status' => 'critical',
                'message' => 'The legacy public checkout-document directories could not be inspected. Review protected logs and filesystem permissions.',
            ];
        }

        $passes = $legacyReferences === 0 && $publicFiles === 0;

        return $this->check(
            $id,
            $passes,
            'critical',
            $passes
                ? 'Ready.'
                : sprintf(
                    '%d %s record(s) still reference public documents and %d file(s) remain publicly accessible. Run %s, reconcile private copies, then run documents:purge-legacy-public --apply.',
                    $legacyReferences,
                    $label,
                    $publicFiles,
                    $migrationCommand,
                )
        );
    }

    /**
     * Tables that make up the application-owned transactional safety contract.
     * A deploy with code ahead of these migrations must fail before traffic is
     * shifted, even when the database itself is reachable.
     */
    public function requiredTransactionalTables(): array
    {
        return [
            'audit_logs',
            'application_mail_deliveries',
            'booking_deliveries',
            'booking_tickets',
            'booking_ticket_delivery_requests',
            'booking_ticket_transfers',
            'customer_email_verification_deliveries',
            'event_cancellation_cases',
            'event_cancellation_notifications',
            'event_cancellation_requests',
            'event_guest_invitations',
            'event_guest_import_rows',
            'event_guest_imports',
            'event_registration_answers',
            'event_registration_allocations',
            'event_registration_allocation_claims',
            'event_registration_attendees',
            'event_registration_badge_settings',
            'event_registration_embed_settings',
            'event_registration_files',
            'event_registration_forms',
            'event_registration_form_templates',
            'event_registration_import_rows',
            'event_registration_imports',
            'event_registration_invoices',
            'event_registration_notifications',
            'event_registration_questions',
            'event_registration_sessions',
            'event_registration_session_selections',
            'event_registration_submissions',
            'event_waitlist_entries',
            'data_export_schedules',
            'gateway_settlement_entries',
            'gateway_settlements',
            'operational_incidents',
            'organizer_email_verification_deliveries',
            'payment_intents',
            'payment_refunds',
            'payment_refund_disputes',
            'payout_disputes',
            'payout_transfers',
            'product_gateway_refunds',
            'product_order_deliveries',
            'product_order_refunds',
            'product_payment_intents',
            'push_notification_campaign_deliveries',
            'push_notification_campaigns',
            'scanner_check_in_conflicts',
            'scanner_devices',
            'scanner_offline_manifest_items',
            'scanner_offline_manifests',
            'scanner_offline_sync_items',
            'scanner_staff',
            'subscriber_campaign_deliveries',
            'subscriber_campaigns',
            'ticket_reservations',
        ];
    }

    public function transactionalSchemaCheck(array $existingTables): array
    {
        $existing = array_fill_keys(array_map(
            static fn ($table) => strtolower(trim((string) $table)),
            $existingTables
        ), true);
        $missing = array_values(array_filter(
            $this->requiredTransactionalTables(),
            static fn (string $table): bool => !isset($existing[$table])
        ));

        return $this->check(
            'database.transactional_schema',
            $missing === [],
            'critical',
            $missing === []
                ? 'Ready.'
                : 'Run the pending application migrations before serving traffic. Missing: '.implode(', ', $missing).'.'
        );
    }

    /**
     * Columns introduced by safety-critical migrations. Checking only table
     * existence is insufficient when an older production schema has the same
     * tables but not the columns expected by current models and workflows.
     */
    public function requiredTransactionalColumns(): array
    {
        return [
            'basic_settings' => [
                'smtp_password', 'google_recaptcha_secret_key',
                'facebook_app_secret', 'google_client_secret',
            ],
            'application_mail_deliveries' => [
                'public_id', 'kind', 'source_type', 'source_id', 'created_by_type', 'created_by_id',
                'recipient_hash', 'recipient_email', 'subject', 'body', 'attachments', 'reply_to',
                'status', 'attempts', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'last_error', 'processing_at', 'sent_at', 'failed_at', 'alerted_at',
            ],
            'bookings' => [
                'checkout_submission_hash', 'buyer_email_hash', 'buyer_email_hash_version',
                'refunded_amount', 'refund_status',
            ],
            'booking_deliveries' => [
                'booking_id', 'kind', 'channel', 'recipient_hash', 'provider_reference',
                'status', 'attempts', 'dispatch_attempts', 'dispatch_last_attempt_at',
                'dispatch_last_error', 'last_error', 'processing_at', 'sent_at', 'alerted_at',
            ],
            'booking_tickets' => [
                'checked_in_at', 'checked_in_by_organizer_id',
                'checked_in_scanner_device_id', 'checked_in_scanner_staff_id', 'checked_in_source',
            ],
            'earnings' => ['singleton_key'],
            'booking_ticket_delivery_requests' => [
                'public_id', 'booking_id', 'requested_by_type', 'requested_by_id',
                'recipient_email_hash', 'status', 'attempts',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error', 'last_error',
                'processing_at', 'sent_at', 'alerted_at',
            ],
            'booking_ticket_transfers' => [
                'booking_ticket_id', 'from_customer_id', 'recipient_email', 'token_hash',
                'dispatch_token', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'status', 'expires_at', 'delivery_status', 'delivery_attempts', 'last_error',
                'processing_at', 'sent_at', 'alerted_at',
            ],
            'event_cancellation_notifications' => [
                'event_cancellation_request_id', 'booking_id', 'status', 'attempts',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'processing_at', 'sent_at', 'alerted_at', 'last_error',
            ],
            'organizer_email_verification_deliveries' => [
                'public_id', 'organizer_id', 'email_hash', 'status', 'attempts', 'last_error',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'processing_at', 'sent_at', 'verified_at', 'expires_at', 'alerted_at',
            ],
            'product_order_deliveries' => [
                'product_order_id', 'kind', 'status', 'attempts',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'last_error', 'processing_at', 'sent_at', 'alerted_at',
            ],
            'customer_email_verification_deliveries' => [
                'public_id', 'customer_id', 'email_hash', 'status', 'attempts', 'last_error',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'processing_at', 'sent_at', 'verified_at', 'expires_at', 'alerted_at',
            ],
            'data_exports' => [
                'public_id', 'requested_by_type', 'requested_by_id', 'export_type', 'status',
                'attempts', 'dispatch_attempts', 'dispatch_last_attempt_at',
                'dispatch_last_error', 'dispatch_alerted_at', 'schedule_id', 'expires_at',
                'ready_notification_delivery_id', 'ready_notification_attempts',
                'ready_notification_last_attempt_at', 'ready_notification_last_error',
                'ready_notification_alerted_at', 'ready_notified_at',
            ],
            'event_guest_imports' => [
                'dispatch_attempts', 'dispatch_last_attempt_at',
                'dispatch_last_error', 'dispatch_alerted_at',
            ],
            'event_registration_imports' => [
                'dispatch_attempts', 'dispatch_last_attempt_at',
                'dispatch_last_error', 'dispatch_alerted_at',
            ],
            'event_registration_notifications' => [
                'submission_id', 'event_id', 'kind', 'status', 'attempts',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'processing_at', 'sent_at', 'alerted_at', 'last_error',
            ],
            'event_waitlist_entries' => [
                'public_id', 'event_id', 'ticket_id', 'selection_key', 'status',
                'notification_pending_at', 'notification_expires_at', 'attempts',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'processing_at', 'alerted_at', 'last_error',
            ],
            'ticket_reservations' => [
                'token', 'event_id', 'ticket_id', 'variation_name', 'quantity', 'status',
                'session_hash', 'customer_id', 'buyer_email_hash', 'buyer_email_hash_version', 'expires_at',
                'consumed_at', 'released_at',
            ],
            'subscriber_campaigns' => [
                'public_id', 'created_by_admin_id', 'subject', 'body', 'status',
                'total_count', 'sent_count', 'failed_count', 'started_at', 'completed_at',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error', 'dispatch_alerted_at',
            ],
            'subscriber_campaign_deliveries' => [
                'campaign_id', 'subscriber_id', 'recipient_hash', 'recipient_email', 'status',
                'attempts', 'last_error', 'processing_at', 'sent_at', 'failed_at', 'alerted_at',
            ],
            'push_notification_campaigns' => [
                'public_id', 'created_by_admin_id', 'title', 'message', 'button_name', 'button_url',
                'status', 'total_count', 'sent_count', 'failed_count', 'started_at', 'completed_at',
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error', 'dispatch_alerted_at',
            ],
            'push_notification_campaign_deliveries' => [
                'campaign_id', 'guest_id', 'status', 'attempts', 'last_error',
                'processing_at', 'sent_at', 'failed_at', 'alerted_at',
            ],
            'scanner_check_in_conflicts' => [
                'organizer_id', 'event_id', 'booking_ticket_id',
                'original_scanner_device_id', 'attempted_scanner_device_id',
                'original_scanner_staff_id', 'attempted_scanner_staff_id',
                'source', 'occurrences', 'first_detected_at', 'last_detected_at',
                'resolved_at', 'resolved_by_organizer_id', 'resolution_note',
            ],
            'scanner_devices' => [
                'public_id', 'organizer_id', 'name', 'secret_hash', 'status',
                'last_seen_at', 'revoked_at', 'revoked_by_organizer_id',
            ],
            'scanner_offline_manifests' => [
                'public_id', 'organizer_id', 'event_id', 'scanner_device_id',
                'scanner_staff_id', 'status', 'snapshot_hash', 'ticket_count',
                'generated_at', 'expires_at', 'last_synced_at', 'revoked_at',
            ],
            'scanner_offline_manifest_items' => [
                'scanner_offline_manifest_id', 'booking_ticket_id', 'payload_hash',
            ],
            'scanner_offline_sync_items' => [
                'client_scan_id', 'scanner_offline_manifest_id', 'organizer_id',
                'scanner_device_id', 'scanner_staff_id', 'booking_ticket_id',
                'payload_hash', 'result', 'http_status', 'client_scanned_at', 'received_at',
            ],
            'scanner_staff' => [
                'organizer_id', 'name', 'email', 'password', 'role', 'status',
                'auth_version', 'last_login_at', 'last_seen_at', 'revoked_at',
            ],
            'customer_privacy_requests' => [
                'public_id', 'customer_id', 'request_type', 'status', 'customer_email', 'reason',
                'policy_version', 'reviewed_by_admin_id', 'resolution', 'evidence_reference',
                'submitted_at', 'reviewed_at', 'completed_at',
            ],
            'data_export_schedules' => [
                'requested_by_type', 'requested_by_id', 'organizer_id', 'export_type', 'format', 'filters',
                'language_id', 'frequency', 'timezone', 'date_window_days', 'active', 'next_run_at',
                'last_run_at', 'last_export_id', 'consecutive_failures', 'last_error',
            ],
            'events' => ['lifecycle_status', 'sales_closed_at', 'cancelled_at', 'cancellation_reason', 'registration_mode'],
            'event_cancellation_cases' => [
                'event_cancellation_request_id', 'booking_id', 'payment_intent_id', 'payment_refund_id',
                'payment_refund_dispute_id', 'resolved_by_admin_id', 'external_refund_reference',
                'status', 'amount', 'reason', 'resolution', 'resolved_at',
            ],
            'event_registration_submissions' => [
                'anonymized_at', 'anonymization_reason', 'source_import_id', 'source_import_key',
                'source_allocation_claim_id',
            ],
            'event_registration_questions' => ['ticket_id', 'settings'],
            'event_registration_attendees' => ['ticket_id', 'variation_name', 'booking_ticket_id'],
            'event_registration_allocations' => [
                'dispatch_token', 'dispatch_attempts', 'dispatch_last_attempt_at',
                'dispatch_last_error', 'anonymized_at', 'anonymization_reason',
            ],
            'event_registration_allocation_claims' => [
                'dispatch_token', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                'source_attestation_version', 'source_attested_at', 'anonymized_at', 'anonymization_reason',
            ],
            'event_registration_invoices' => [
                'submission_id', 'booking_id', 'reservation_token', 'status', 'payment_reference', 'invoice_path',
            ],
            'event_registration_badge_settings' => [
                'form_id', 'event_id', 'organizer_id', 'company_question_key', 'role_question_key', 'accent_color',
            ],
            'event_registration_embed_settings' => [
                'form_id', 'event_id', 'organizer_id', 'enabled', 'allowed_origins', 'accent_color', 'background_color',
            ],
            'event_registration_files' => [
                'public_id', 'answer_id', 'submission_id', 'attendee_id', 'question_id',
                'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
            ],
            'event_guest_invitations' => [
                'source_import_id', 'source_import_row', 'anonymized_at', 'anonymization_reason',
                'dispatch_token', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
            ],
            'payment_intents' => ['gateway_transaction_id'],
            'payment_refunds' => [
                'requested_by_organizer_id',
                'reviewed_by_admin_id',
                'reviewed_at',
                'rejection_reason',
                'event_cancellation_request_id',
                'payment_refund_dispute_id',
            ],
            'payment_refund_disputes' => [
                'payment_intent_id', 'booking_id', 'organizer_id', 'requested_by_organizer_id',
                'requested_by_admin_id', 'reviewed_by_admin_id', 'payment_refund_id', 'category',
                'description', 'status', 'requested_amount', 'currency', 'checked_in_ticket_count',
                'resolution', 'opened_at', 'reviewed_at', 'closed_at',
            ],
            'payout_disputes' => [
                'withdrawal_id', 'organizer_id', 'resolved_by_admin_id', 'category', 'description',
                'status', 'withdrawal_status', 'amount', 'resolution', 'opened_at', 'reviewed_at', 'closed_at',
            ],
            'payout_transfers' => [
                'requested_by_admin_id', 'release_source', 'policy_version',
                'dispatch_attempts', 'dispatch_last_attempt_at',
                'dispatch_last_error', 'dispatch_alerted_at',
                'notification_dispatch_attempts', 'notification_dispatch_last_attempt_at',
                'notification_dispatch_last_error', 'notification_dispatch_alerted_at',
            ],
            'tickets' => ['deleted_at'],
            'role_permissions' => ['export_fields'],
            'transactions' => ['gateway', 'gateway_reference', 'booking_id'],
            'gateway_settlements' => [
                'gateway', 'gateway_settlement_id', 'reconciliation_status', 'settlement_date', 'fetched_at',
            ],
            'gateway_settlement_entries' => [
                'gateway_settlement_id', 'booking_id', 'result', 'gateway_fee', 'checked_at',
            ],
        ];
    }

    public function transactionalColumnCheck(array $existingColumnsByTable): array
    {
        $missing = [];
        foreach ($this->requiredTransactionalColumns() as $table => $requiredColumns) {
            $existing = array_fill_keys(array_map(
                static fn ($column) => strtolower(trim((string) $column)),
                $existingColumnsByTable[$table] ?? []
            ), true);
            foreach ($requiredColumns as $column) {
                if (!isset($existing[strtolower($column)])) {
                    $missing[] = $table.'.'.$column;
                }
            }
        }

        return $this->check(
            'database.transactional_columns',
            $missing === [],
            'critical',
            $missing === []
                ? 'Ready.'
                : 'Run the pending application migrations before serving traffic. Missing: '.implode(', ', $missing).'.'
        );
    }

    /**
     * Database constraints are part of the transactional contract. A column
     * can be present while a partially applied migration leaves the exact-once
     * identity unenforced, so inspect named indexes independently.
     */
    public function requiredTransactionalIndexes(): array
    {
        return [
            'booking_deliveries' => [
                'booking_delivery_provider_reference_unique',
            ],
        ];
    }

    public function transactionalIndexCheck(array $existingIndexesByTable): array
    {
        $missing = [];
        foreach ($this->requiredTransactionalIndexes() as $table => $requiredIndexes) {
            $existing = array_fill_keys(array_map(
                static fn ($index) => strtolower(trim((string) $index)),
                $existingIndexesByTable[$table] ?? []
            ), true);
            foreach ($requiredIndexes as $index) {
                if (!isset($existing[strtolower($index)])) {
                    $missing[] = $table.'.'.$index;
                }
            }
        }

        return $this->check(
            'database.transactional_indexes',
            $missing === [],
            'critical',
            $missing === []
                ? 'Ready.'
                : 'Run the pending application migrations before serving traffic. Missing constraint: '.implode(', ', $missing).'.'
        );
    }

    /**
     * The singleton column prevents a second valid row after migration, but a
     * privileged/manual deletion could still leave the platform without an
     * authoritative revenue balance. Validate the live data, not only schema.
     *
     * @param  array<int, mixed>  $singletonKeys
     */
    public function platformEarningSingletonCheck(array $singletonKeys): array
    {
        $keys = array_values(array_map(
            static fn ($key): int => (int) $key,
            $singletonKeys
        ));
        $ready = count($keys) === 1 && $keys[0] === 1;

        return $this->check(
            'database.platform_earning_singleton',
            $ready,
            'critical',
            $ready
                ? 'Ready.'
                : 'The earnings table must contain exactly one authoritative row with singleton_key=1. Reconcile finance evidence before serving traffic.'
        );
    }

    public function guestBuyerIdentityIntegrityCheck(
        int $unidentifiedGuestBookings,
        int $unidentifiedActiveGuestReservations,
    ): array {
        $unidentifiedGuestBookings = max(0, $unidentifiedGuestBookings);
        $unidentifiedActiveGuestReservations = max(0, $unidentifiedActiveGuestReservations);
        $ready = $unidentifiedGuestBookings === 0 && $unidentifiedActiveGuestReservations === 0;

        return $this->check(
            'tickets.guest_buyer_identity_integrity',
            $ready,
            'critical',
            $ready
                ? 'Ready.'
                : sprintf(
                    'Guest buyer identity backfill is incomplete: %d booking(s), %d active reservation(s). Run migrations, reconcile invalid guest emails, and allow or release legacy holds before serving checkout traffic.',
                    $unidentifiedGuestBookings,
                    $unidentifiedActiveGuestReservations,
                ),
        );
    }

    /**
     * @param  array{total_bookings?: int, affected_bookings?: int, issue_counts?: array<string, int>}  $report
     */
    public function ticketUnitIntegrityCheck(array $report): array
    {
        $affected = max(0, (int) ($report['affected_bookings'] ?? 0));
        $issueCounts = array_filter(
            (array) ($report['issue_counts'] ?? []),
            static fn ($count): bool => (int) $count > 0
        );
        ksort($issueCounts);
        $details = implode(', ', array_map(
            static fn (string $issue, $count): string => $issue.'='.(int) $count,
            array_keys($issueCounts),
            array_values($issueCounts)
        ));
        $onlyMissing = $issueCounts !== [] && array_keys($issueCounts) === ['missing_units'];

        return $this->check(
            'tickets.booking_unit_integrity',
            $affected === 0,
            'critical',
            $affected === 0
                ? 'Ready.'
                : ($affected.' booking(s) have unsafe ticket-unit history'.($details !== '' ? ': '.$details : '').'. '
                    .($onlyMissing
                        ? 'Run `php artisan tickets:audit-booking-units`, then `php artisan tickets:issue-booking-units`; review every failure and re-run readiness.'
                        : 'Run `php artisan tickets:audit-booking-units`. Do not auto-repair partial or contradictory units; reconcile them from booking and payment evidence, then re-run readiness.'))
        );
    }

    public function frontendAssetCheck(
        string $manifestPath,
        string $requiredEntry = 'resources/css/site-chrome.css'
    ): array {
        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            return $this->check(
                'frontend.assets',
                false,
                'critical',
                'Build frontend assets with `npm ci && npm run build` before serving traffic.'
            );
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->check(
                'frontend.assets',
                false,
                'critical',
                'The Vite manifest is unreadable or malformed; rebuild frontend assets before serving traffic.'
            );
        }

        $asset = is_array($manifest) ? ($manifest[$requiredEntry]['file'] ?? null) : null;
        $buildRoot = dirname($manifestPath);
        $assetPath = is_string($asset) && $asset !== ''
            ? $buildRoot.DIRECTORY_SEPARATOR.ltrim($asset, '/\\')
            : '';
        $ready = $assetPath !== ''
            && is_file($assetPath)
            && is_readable($assetPath)
            && $this->pathIsWithin($assetPath, $buildRoot);

        return $this->check(
            'frontend.assets',
            $ready,
            'critical',
            "The Vite manifest must reference a readable built asset for {$requiredEntry}. Run `npm ci && npm run build`."
        );
    }

    public function __construct(
        private readonly ?string $phpVersion = null,
        private readonly ?string $frameworkVersion = null,
    ) {
    }

    public function evaluate(array $enabledGateways = []): array
    {
        $checks = [];
        $appKey = (string) config('app.key', '');
        $ticketKey = (string) config('tickets.signing_key', '');
        $buyerIdentityKey = (string) config('tickets.buyer_identity_hash_key', '');
        $url = (string) config('app.url', '');

        $runtime = $this->phpVersion ?? PHP_VERSION;
        $framework = $this->frameworkVersion ?? app()->version();
        $checks[] = $this->check(
            'runtime.php',
            version_compare($runtime, '8.3.0', '>=') && version_compare($runtime, '8.6.0', '<'),
            'critical',
            'PHP 8.3 through 8.5 is required for the supported Laravel 13 runtime.'
        );
        $checks[] = $this->check(
            'runtime.framework',
            (int) explode('.', ltrim($framework, 'v'))[0] === 13,
            'critical',
            'Laravel 13 is required; unsupported framework releases must not be deployed.'
        );

        $checks[] = $this->check('app.environment', config('app.env') === 'production', 'critical',
            'APP_ENV must be production.');
        $checks[] = $this->check('app.debug', config('app.debug') === false, 'critical',
            'APP_DEBUG must be false.');
        $checks[] = $this->check('app.url', str_starts_with(strtolower($url), 'https://'), 'critical',
            'APP_URL must use HTTPS.');
        $checks[] = $this->check('app.key', $this->secretLength($appKey) >= 32, 'critical',
            'APP_KEY must contain at least 32 bytes of key material.');
        $checks[] = $this->check(
            'security.web_installer',
            !(bool) config('installer.webInstallerEnabled', false) && !(bool) config('installer.updaterEnabled', false),
            'critical',
            'WEB_INSTALLER_ENABLED and WEB_UPDATER_ENABLED must both be false; deploy and migrate through authenticated CLI/CI.'
        );
        $checks[] = $this->check(
            'tickets.signing_key',
            $this->secretLength($ticketKey) >= 32 && $this->secretsAreDistinct($appKey, $ticketKey),
            'critical',
            'TICKET_SIGNING_KEY must be a dedicated stable secret of at least 32 bytes.'
        );
        $checks[] = $this->check(
            'tickets.buyer_identity_hash_key',
            $this->secretLength($buyerIdentityKey) >= 32
                && $this->secretsAreDistinct($appKey, $buyerIdentityKey)
                && $this->secretsAreDistinct($ticketKey, $buyerIdentityKey),
            'critical',
            'TICKET_BUYER_IDENTITY_HASH_KEY must be a dedicated stable secret of at least 32 bytes and must not be changed during routine APP_KEY rotation.',
        );

        $queue = (string) config('queue.default');
        $checks[] = $this->check('queue.driver', !in_array($queue, ['', 'sync', 'null'], true), 'critical',
            'QUEUE_CONNECTION must use a durable asynchronous backend.');
        $session = (string) config('session.driver');
        $checks[] = $this->check('session.driver', in_array($session, ['redis', 'database'], true), 'critical',
            'SESSION_DRIVER must be redis or database for a multi-instance deployment.');
        $cache = (string) config('cache.default');
        $checks[] = $this->check('cache.driver', in_array($cache, ['redis', 'memcached', 'database'], true), 'critical',
            'CACHE_DRIVER must use a shared backend for locks, rate limits, payment coordination and worker health.');

        $documentDisk = (string) config('filesystems.product_order_documents_disk', 'local');
        $documentDiskConfig = (array) config('filesystems.disks.'.$documentDisk, []);
        $usesPublicRoot = isset($documentDiskConfig['root'])
            && $this->pathIsWithin((string) $documentDiskConfig['root'], public_path());
        $checks[] = $this->check(
            'storage.product_order_documents',
            $documentDiskConfig !== []
                && $documentDisk !== 'public'
                && ($documentDiskConfig['visibility'] ?? 'private') !== 'public'
                && !$usesPublicRoot,
            'critical',
            'PRODUCT_ORDER_DOCUMENT_DISK must be configured on private, non-public storage.'
        );

        $eventDocumentDisk = (string) config('filesystems.event_booking_documents_disk', 'local');
        $eventDocumentDiskConfig = (array) config('filesystems.disks.'.$eventDocumentDisk, []);
        $eventUsesPublicRoot = isset($eventDocumentDiskConfig['root'])
            && $this->pathIsWithin((string) $eventDocumentDiskConfig['root'], public_path());
        $checks[] = $this->check(
            'storage.event_booking_documents',
            $eventDocumentDiskConfig !== []
                && $eventDocumentDisk !== 'public'
                && ($eventDocumentDiskConfig['visibility'] ?? 'private') !== 'public'
                && !$eventUsesPublicRoot,
            'critical',
            'EVENT_BOOKING_DOCUMENT_DISK must be configured on private, non-public storage.'
        );

        $registrationImportDisk = (string) config('registration.import_disk', 'local');
        $registrationImportDiskConfig = (array) config('filesystems.disks.'.$registrationImportDisk, []);
        $registrationImportUsesPublicRoot = isset($registrationImportDiskConfig['root'])
            && $this->pathIsWithin((string) $registrationImportDiskConfig['root'], public_path());
        $checks[] = $this->check(
            'storage.registration_imports',
            $registrationImportDiskConfig !== []
                && $registrationImportDisk !== 'public'
                && ($registrationImportDiskConfig['visibility'] ?? 'private') !== 'public'
                && !$registrationImportUsesPublicRoot,
            'critical',
            'EVENT_REGISTRATION_IMPORT_DISK must be configured on private, non-public storage.'
        );

        $registrationUploadDisk = (string) config('registration.upload_disk', 'local');
        $registrationUploadDiskConfig = (array) config('filesystems.disks.'.$registrationUploadDisk, []);
        $registrationUploadUsesPublicRoot = isset($registrationUploadDiskConfig['root'])
            && $this->pathIsWithin((string) $registrationUploadDiskConfig['root'], public_path());
        $checks[] = $this->check(
            'storage.registration_uploads',
            $registrationUploadDiskConfig !== []
                && $registrationUploadDisk !== 'public'
                && ($registrationUploadDiskConfig['visibility'] ?? 'private') !== 'public'
                && !$registrationUploadUsesPublicRoot,
            'critical',
            'EVENT_REGISTRATION_UPLOAD_DISK must be configured on private, non-public storage.'
        );

        $registrationInvoiceDisk = (string) config('registration.invoice_disk', 'local');
        $registrationInvoiceDiskConfig = (array) config('filesystems.disks.'.$registrationInvoiceDisk, []);
        $registrationInvoiceUsesPublicRoot = isset($registrationInvoiceDiskConfig['root'])
            && $this->pathIsWithin((string) $registrationInvoiceDiskConfig['root'], public_path());
        $checks[] = $this->check(
            'storage.registration_invoices',
            $registrationInvoiceDiskConfig !== []
                && $registrationInvoiceDisk !== 'public'
                && ($registrationInvoiceDiskConfig['visibility'] ?? 'private') !== 'public'
                && !$registrationInvoiceUsesPublicRoot,
            'critical',
            'EVENT_REGISTRATION_INVOICE_DISK must be configured on private, non-public storage.'
        );

        $guestImportDisk = (string) config('tickets.guest_import_disk', 'local');
        $guestImportDiskConfig = (array) config('filesystems.disks.'.$guestImportDisk, []);
        $guestImportUsesPublicRoot = isset($guestImportDiskConfig['root'])
            && $this->pathIsWithin((string) $guestImportDiskConfig['root'], public_path());
        $checks[] = $this->check(
            'storage.guest_imports',
            $guestImportDiskConfig !== []
                && $guestImportDisk !== 'public'
                && ($guestImportDiskConfig['visibility'] ?? 'private') !== 'public'
                && !$guestImportUsesPublicRoot,
            'critical',
            'TICKET_GUEST_IMPORT_DISK must be configured on private, non-public storage.'
        );

        $mailer = (string) config('mail.default');
        $checks[] = $this->check('mail.driver', !in_array($mailer, ['', 'array', 'log'], true), 'critical',
            'Configure a production mail transport.');
        $checks[] = $this->check('mail.from', filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false, 'critical',
            'MAIL_FROM_ADDRESS must be a valid deliverable address.');
        $smsBaseUrl = rtrim(trim((string) config('ticket_delivery.sms.base_url')), '/');
        $smsSender = trim((string) config('ticket_delivery.sms.sender_id'));
        $smsCountryCode = preg_replace('/\D+/', '', (string) config('ticket_delivery.sms.default_country_code')) ?? '';
        $checks[] = $this->check(
            'tickets.sms_delivery',
            (bool) config('ticket_delivery.sms.enabled', false)
                && config('ticket_delivery.sms.driver') === 'termii'
                && filter_var($smsBaseUrl, FILTER_VALIDATE_URL) !== false
                && strtolower((string) parse_url($smsBaseUrl, PHP_URL_SCHEME)) === 'https'
                && strlen(trim((string) config('ticket_delivery.sms.api_key'))) >= 20
                && preg_match('/^[A-Za-z0-9]{2,11}$/', $smsSender) === 1
                && in_array(config('ticket_delivery.sms.channel'), ['dnd', 'generic'], true)
                && preg_match('/^[1-9][0-9]{0,3}$/', $smsCountryCode) === 1,
            'critical',
            'Enable durable ticket SMS delivery and configure an HTTPS Termii base URL, deployment-managed API key, approved sender ID, channel and default country code.'
        );
        $smsDrillReference = trim((string) config('ticket_delivery.sms.production_drill_reference'));
        $checks[] = $this->check(
            'tickets.sms_delivery_drill',
            filter_var((string) config('ticket_delivery.sms.owner_email'), FILTER_VALIDATE_EMAIL) !== false
                && $smsDrillReference !== ''
                && mb_strlen($smsDrillReference) <= 191
                && $this->evidenceDateIsCurrent(
                    (string) config('ticket_delivery.sms.production_drill_at'),
                    (int) config('ticket_delivery.sms.production_drill_max_age_days', 180)
                )
                && (bool) config('ticket_delivery.sms.drill_local_number_verified', false)
                && (bool) config('ticket_delivery.sms.drill_international_number_verified', false)
                && (bool) config('ticket_delivery.sms.drill_rejection_verified', false)
                && (bool) config('ticket_delivery.sms.drill_timeout_verified', false)
                && (bool) config('ticket_delivery.sms.drill_signed_link_verified', false)
                && (bool) config('ticket_delivery.sms.drill_worker_sla_verified', false),
            'critical',
            'Assign the SMS owner and complete a current production-like Termii drill covering local and international numbers, rejection, timeout ambiguity, exact signed-link access and payments-worker delivery time; record a non-secret evidence reference.'
        );
        $vapidSubject = trim((string) config('webpush.vapid.subject'));
        $vapidPublic = trim((string) config('webpush.vapid.public_key'));
        $vapidPrivate = trim((string) config('webpush.vapid.private_key'));
        $mailtoAddress = str_starts_with(strtolower($vapidSubject), 'mailto:')
            ? substr($vapidSubject, 7)
            : '';
        $validVapidSubject = ($mailtoAddress !== '' && filter_var($mailtoAddress, FILTER_VALIDATE_EMAIL) !== false)
            || (filter_var($vapidSubject, FILTER_VALIDATE_URL) !== false
                && strtolower((string) parse_url($vapidSubject, PHP_URL_SCHEME)) === 'https');
        $checks[] = $this->check(
            'webpush.vapid',
            $validVapidSubject
                && strlen($vapidPublic) >= 40
                && strlen($vapidPrivate) >= 40
                && !hash_equals($vapidPublic, $vapidPrivate),
            'critical',
            'Configure deployment-managed VAPID_SUBJECT, VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY; never generate or mutate push credentials from a web request.'
        );
        $auditRetentionDays = (int) config('audit.retention_days', 0);
        $checks[] = $this->check(
            'audit.retention_policy',
            (bool) config('audit.retention_enabled', false)
                && $auditRetentionDays >= 365
                && $auditRetentionDays <= 3650,
            'critical',
            'Approve an audit-evidence retention period between 365 and 3650 days, then enable AUDIT_LOG_RETENTION_ENABLED.'
        );
        $checks[] = $this->check(
            'privacy.registration_retention',
            (bool) config('registration.retention_enabled', false),
            'warning',
            'Approve the corporate-registration retention period, then enable EVENT_REGISTRATION_RETENTION_ENABLED.'
        );
        $checks[] = $this->check(
            'privacy.guest_retention',
            (bool) config('tickets.guest_retention_enabled', false),
            'warning',
            'Approve the guest-contact retention period, then enable TICKET_GUEST_RETENTION_ENABLED.'
        );
        $privacy = (array) config('privacy', []);
        $privacyOwner = (string) ($privacy['owner_email'] ?? '');
        $privacyPolicyVersion = trim((string) ($privacy['policy_version'] ?? ''));
        $registrationRetentionDays = (int) config('registration.retention_days', 0);
        $guestRetentionDays = (int) config('tickets.guest_retention_days', 0);
        $exportRetentionDays = (int) config('exports.retention_days', 0);
        $checks[] = $this->check(
            'privacy.governance_policy',
            filter_var($privacyOwner, FILTER_VALIDATE_EMAIL) !== false
                && $privacyPolicyVersion !== ''
                && trim((string) ($privacy['breach_plan_reference'] ?? '')) !== ''
                && (bool) config('registration.retention_enabled', false)
                && (bool) config('tickets.guest_retention_enabled', false)
                && $registrationRetentionDays >= 30 && $registrationRetentionDays <= 3650
                && $guestRetentionDays >= 30 && $guestRetentionDays <= 3650
                && $exportRetentionDays >= 1 && $exportRetentionDays <= 30,
            'critical',
            'Record a privacy owner, policy version and breach-plan reference; approve bounded registration, guest and export retention; then enable both scheduled retention controls.'
        );
        $privacyReviewMaxAge = (int) ($privacy['review_max_age_days'] ?? 365);
        $checks[] = $this->check(
            'privacy.launch_review',
            trim((string) ($privacy['launch_review_reference'] ?? '')) !== ''
                && $this->evidenceDateIsCurrent((string) ($privacy['vendor_dpa_review_at'] ?? ''), $privacyReviewMaxAge)
                && $this->evidenceDateIsCurrent((string) ($privacy['launch_review_at'] ?? ''), $privacyReviewMaxAge),
            'critical',
            'Complete the vendor-DPA and privacy launch reviews, record their UTC dates, and provide a non-secret sign-off evidence reference.'
        );
        $accessibility = (array) config('accessibility', []);
        $checks[] = $this->check(
            'accessibility.wcag_audit',
            filter_var((string) ($accessibility['owner_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
                && strtoupper(trim((string) ($accessibility['audit_standard'] ?? ''))) === 'WCAG 2.2 AA'
                && trim((string) ($accessibility['audit_reference'] ?? '')) !== ''
                && $this->evidenceDateIsCurrent(
                    (string) ($accessibility['audit_at'] ?? ''),
                    (int) ($accessibility['audit_max_age_days'] ?? 180)
                )
                && (bool) ($accessibility['keyboard_verified'] ?? false)
                && (bool) ($accessibility['screen_reader_verified'] ?? false)
                && (bool) ($accessibility['checkout_verified'] ?? false)
                && (bool) ($accessibility['scanner_verified'] ?? false),
            'critical',
            'Complete and record a current WCAG 2.2 AA audit with an owner, keyboard/screen-reader evidence, and explicit checkout and scanner journey verification.'
        );
        $checks[] = $this->check('security.csp', (bool) config('security.enforce_content_security_policy', false), 'critical',
            'CSP must be enforced after reviewing and resolving staging violation reports.');
        $securityOwner = (string) config('security.owner_email', '');
        $checks[] = $this->check(
            'security.penetration_test',
            filter_var($securityOwner, FILTER_VALIDATE_EMAIL) !== false
                && trim((string) config('security.penetration_test_reference', '')) !== ''
                && $this->evidenceDateIsCurrent(
                    (string) config('security.penetration_test_at', ''),
                    (int) config('security.penetration_test_max_age_days', 180)
                ),
            'critical',
            'Record a named security owner and a current penetration-test report reference after blocking findings are remediated and retested.'
        );
        $checks[] = $this->check(
            'security.key_rotation',
            trim((string) config('security.key_rotation_reference', '')) !== ''
                && $this->evidenceDateIsCurrent(
                    (string) config('security.key_rotation_at', ''),
                    (int) config('security.key_rotation_max_age_days', 365)
                ),
            'critical',
            'Complete launch key rotation with a ticket-signing migration plan and record the UTC date plus a non-secret change reference.'
        );
        $checks[] = $this->check(
            'security.incident_drill',
            trim((string) config('security.incident_drill_reference', '')) !== ''
                && $this->evidenceDateIsCurrent(
                    (string) config('security.incident_drill_at', ''),
                    (int) config('security.incident_drill_max_age_days', 180)
                ),
            'critical',
            'Exercise the incident-response and off-server alert path, then record the UTC date and non-secret drill reference.'
        );
        $checks[] = $this->check('logging.level', strtolower((string) config('logging.channels.single.level', 'debug')) !== 'debug', 'warning',
            'Production log level should not be debug.');
        $incidentEmail = (string) config('operations.incident_email', '');
        $incidentWebhook = (string) config('operations.incident_webhook_url', '');
        $validIncidentEmail = filter_var($incidentEmail, FILTER_VALIDATE_EMAIL) !== false;
        $validIncidentWebhook = filter_var($incidentWebhook, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($incidentWebhook, PHP_URL_SCHEME)) === 'https';
        $checks[] = $this->check(
            'operations.incident_destination',
            $validIncidentEmail || $validIncidentWebhook,
            'critical',
            'Configure OPS_INCIDENT_EMAIL or a secure OPS_INCIDENT_WEBHOOK_URL so critical failures leave the server.'
        );
        if ($incidentWebhook !== '') {
            $checks[] = $this->check(
                'operations.incident_webhook',
                $validIncidentWebhook && strlen((string) config('operations.incident_webhook_secret', '')) >= 32,
                'critical',
                'OPS_INCIDENT_WEBHOOK_URL must use HTTPS and OPS_INCIDENT_WEBHOOK_SECRET must be at least 32 characters.'
            );
        }

        $backup = (array) config('operations.backup', []);
        $backupOwner = (string) ($backup['owner_email'] ?? '');
        $backupProvider = trim((string) ($backup['provider'] ?? ''));
        $backupRpo = (int) ($backup['rpo_minutes'] ?? 0);
        $backupRto = (int) ($backup['rto_minutes'] ?? 0);
        $backupPolicyReady = filter_var($backupOwner, FILTER_VALIDATE_EMAIL) !== false
            && $backupProvider !== ''
            && (bool) ($backup['database_enabled'] ?? false)
            && (bool) ($backup['private_files_enabled'] ?? false)
            && (bool) ($backup['encryption_enabled'] ?? false)
            && (bool) ($backup['offsite_enabled'] ?? false)
            && $backupRpo >= 1 && $backupRpo <= 1440
            && $backupRto >= 1 && $backupRto <= 1440;
        $checks[] = $this->check(
            'operations.backup_policy',
            $backupPolicyReady,
            'critical',
            'Record a named backup owner/provider, encrypted off-site database and private-file coverage, and RPO/RTO targets no greater than 24 hours.'
        );

        $restoreDrillReference = trim((string) ($backup['restore_drill_reference'] ?? ''));
        $checks[] = $this->check(
            'operations.restore_drill',
            $restoreDrillReference !== '' && $this->evidenceDateIsCurrent(
                (string) ($backup['restore_drill_at'] ?? ''),
                (int) ($backup['restore_drill_max_age_days'] ?? 90)
            ),
            'critical',
            'Complete a restore drill within the configured age limit and record BACKUP_RESTORE_DRILL_AT plus a non-secret evidence reference.'
        );

        $monitoring = (array) config('operations.monitoring', []);
        $appBaseUrl = rtrim($url, '/');
        $livenessUrl = rtrim((string) ($monitoring['liveness_url'] ?? ''), '/');
        $readinessUrl = rtrim((string) ($monitoring['readiness_url'] ?? ''), '/');
        $checks[] = $this->check(
            'operations.external_monitoring',
            filter_var((string) ($monitoring['owner_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
                && (bool) ($monitoring['external_uptime_enabled'] ?? false)
                && (bool) ($monitoring['error_tracking_enabled'] ?? false)
                && (bool) ($monitoring['synthetic_checkout_enabled'] ?? false)
                && $livenessUrl === $appBaseUrl.'/health/live'
                && $readinessUrl === $appBaseUrl.'/health/ready',
            'critical',
            'Configure an accountable external uptime/error-tracking/synthetic-checkout service against this production host’s exact /health/live and /health/ready URLs.'
        );
        $checks[] = $this->check(
            'operations.monitoring_exercise',
            trim((string) ($monitoring['exercise_reference'] ?? '')) !== ''
                && $this->evidenceDateIsCurrent(
                    (string) ($monitoring['exercise_at'] ?? ''),
                    (int) ($monitoring['exercise_max_age_days'] ?? 90)
                ),
            'critical',
            'Exercise external probes, error capture and the synthetic checkout alert, then record a current non-secret evidence reference.'
        );

        if (in_array('flutterwave', $enabledGateways, true)) {
            $checks[] = $this->check(
                'flutterwave.webhook_secret',
                strlen((string) config('services.flutterwave.secret_hash')) >= 32,
                'critical',
                'FLUTTERWAVE_SECRET_HASH must be at least 32 characters and match the dashboard.'
            );
        }

        $unsafeGateways = (new PaymentGatewayPolicy())->unsafeEnabledGateways($enabledGateways);
        $checks[] = $this->check(
            'payments.production_contract',
            $unsafeGateways === [],
            'critical',
            $unsafeGateways === []
                ? 'Ready.'
                : 'Disable or harden gateways without the durable production contract: ' . implode(', ', $unsafeGateways) . '.'
        );
        $checks[] = $this->check(
            'payments.legacy_non_production_disabled',
            !(bool) config('payments.allow_legacy_non_production', false),
            'critical',
            'Set ALLOW_LEGACY_NON_PRODUCTION_GATEWAYS=false before release.'
        );
        $certifiedEnabledGateways = array_values(array_intersect(
            ['paystack', 'flutterwave'],
            $enabledGateways
        ));
        if ($certifiedEnabledGateways !== []) {
            $paymentDrillReference = trim((string) config('payments.production_drill_reference'));
            $providerEvidenceReady = (!in_array('paystack', $certifiedEnabledGateways, true)
                    || ((bool) config('payments.drill_paystack_verified', false)
                        && (bool) config('payments.drill_shop_paystack_verified', false)))
                && (!in_array('flutterwave', $certifiedEnabledGateways, true)
                    || (bool) config('payments.drill_flutterwave_verified', false));
            $checks[] = $this->check(
                'payments.production_drill',
                filter_var((string) config('payments.operations_owner_email'), FILTER_VALIDATE_EMAIL) !== false
                    && $paymentDrillReference !== ''
                    && mb_strlen($paymentDrillReference) <= 191
                    && $this->evidenceDateIsCurrent(
                        (string) config('payments.production_drill_at'),
                        (int) config('payments.production_drill_max_age_days', 180)
                    )
                    && $providerEvidenceReady
                    && (bool) config('payments.drill_event_checkout_verified', false)
                    && (bool) config('payments.drill_webhook_verified', false)
                    && (bool) config('payments.drill_lost_callback_verified', false)
                    && (bool) config('payments.drill_reservation_expiry_verified', false)
                    && (bool) config('payments.drill_refund_verified', false)
                    && (bool) config('payments.drill_reconciliation_verified', false),
                'critical',
                'Assign the payment operations owner and complete a current production-like drill for every enabled certified gateway, including event checkout, Paystack shop checkout when enabled, signed webhooks, replay, lost-callback recovery, reservation expiry, refund lifecycle and reconciliation; record a non-secret evidence reference.'
            );
        }

        if ((bool) config('payouts.paystack_transfers_enabled', false)) {
            $checks[] = $this->check(
                'payouts.paystack_gateway',
                in_array('paystack', $enabledGateways, true),
                'critical',
                'Paystack must be enabled before organizer payout transfers can be enabled.'
            );
            $checks[] = $this->check(
                'payouts.finance_email',
                filter_var(config('payouts.finance_email'), FILTER_VALIDATE_EMAIL) !== false,
                'critical',
                'PAYOUT_FINANCE_EMAIL must be configured before organizer payout transfers are enabled.'
            );
            $checks[] = $this->check(
                'payouts.production_drill',
                trim((string) config('payouts.production_drill_reference', '')) !== ''
                    && $this->evidenceDateIsCurrent(
                        (string) config('payouts.production_drill_at', ''),
                        (int) config('payouts.production_drill_max_age_days', 180)
                    ),
                'critical',
                'Complete a production-like Paystack recipient, transfer, signed-webhook, reconciliation, reversal and notification drill, then record its UTC date and non-secret evidence reference.'
            );
        }
        $automaticRelease = (bool) config('payouts.automatic_release_enabled', false);
        $policy = (array) config('payouts.eligibility_policy', []);
        $policyIssues = (new PayoutEligibilityService())->configurationIssues($policy);
        $automaticReleaseReady = !$automaticRelease || (
            (bool) config('payouts.paystack_transfers_enabled', false)
            && (bool) ($policy['approved'] ?? false)
            && trim((string) ($policy['approval_reference'] ?? '')) !== ''
            && $policyIssues === []
        );
        $checks[] = $this->check(
            'payouts.automatic_release',
            $automaticReleaseReady,
            'critical',
            !$automaticRelease
                ? 'Automatic release is disabled; manual finance approval remains authoritative.'
                : ($automaticReleaseReady
                    ? 'Versioned payout eligibility, risk limits, ownership and approval evidence are configured.'
                    : 'Automatic release requires Paystack transfers, an approved versioned policy, approval reference, settlement owner and valid risk limits. Issues: '
                        . implode(', ', $policyIssues ?: ['policy_or_transfers_not_approved']) . '.')
        );

        return $checks;
    }

    public function summary(array $checks): array
    {
        return [
            'passed' => count(array_filter($checks, fn ($check) => $check['status'] === 'pass')),
            'warnings' => count(array_filter($checks, fn ($check) => $check['status'] === 'warning')),
            'critical' => count(array_filter($checks, fn ($check) => $check['status'] === 'critical')),
        ];
    }

    private function check(string $id, bool $passes, string $failureStatus, string $message): array
    {
        return [
            'id' => $id,
            'status' => $passes ? 'pass' : $failureStatus,
            'message' => $passes ? 'Ready.' : $message,
        ];
    }

    private function secretLength(string $secret): int
    {
        return strlen($this->secretMaterial($secret) ?? '');
    }

    private function secretsAreDistinct(string $left, string $right): bool
    {
        $leftMaterial = $this->secretMaterial($left);
        $rightMaterial = $this->secretMaterial($right);

        return $leftMaterial !== null
            && $rightMaterial !== null
            && !hash_equals($leftMaterial, $rightMaterial);
    }

    private function secretMaterial(string $secret): ?string
    {
        if (!str_starts_with($secret, 'base64:')) {
            return $secret;
        }

        $decoded = base64_decode(substr($secret, 7), true);

        return $decoded === false ? null : $decoded;
    }

    private function evidenceDateIsCurrent(string $value, int $maxAgeDays): bool
    {
        if (trim($value) === '' || $maxAgeDays < 1) {
            return false;
        }

        try {
            $drill = new \DateTimeImmutable($value);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return false;
        }

        return $drill <= $now && $drill >= $now->modify('-'.$maxAgeDays.' days');
    }

    private function pathIsWithin(string $candidate, string $root): bool
    {
        $candidatePath = realpath($candidate) ?: $this->normalizeAbsolutePath($candidate);
        $rootPath = realpath($root) ?: $this->normalizeAbsolutePath($root);

        return $candidatePath !== '' && $rootPath !== ''
            && str_starts_with(rtrim($candidatePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $prefix = str_starts_with($path, DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
        $parts = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return $prefix.implode(DIRECTORY_SEPARATOR, $parts);
    }
}
