<?php

namespace Tests\Unit;

use App\Services\ReleaseReadinessService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseReadinessServiceTest extends TestCase
{
    public function test_legacy_public_document_gate_requires_both_private_references_and_no_public_files(): void
    {
        $service = new ReleaseReadinessService();

        $this->assertSame('pass', $service->legacyPublicDocumentsCheck(
            'storage.test', 'booking', 0, 0, 'documents:migrate'
        )['status']);
        $this->assertSame('critical', $service->legacyPublicDocumentsCheck(
            'storage.test', 'booking', 1, 0, 'documents:migrate'
        )['status']);
        $this->assertSame('critical', $service->legacyPublicDocumentsCheck(
            'storage.test', 'booking', 0, 1, 'documents:migrate'
        )['status']);
        $this->assertSame('critical', $service->legacyPublicDocumentsCheck(
            'storage.test', 'booking', 0, null, 'documents:migrate'
        )['status']);
    }

    public function test_export_release_contract_keeps_dispatch_and_schedule_columns_together(): void
    {
        $columns = (new ReleaseReadinessService())->requiredTransactionalColumns()['data_exports'];

        $this->assertContains('dispatch_attempts', $columns);
        $this->assertContains('dispatch_last_error', $columns);
        $this->assertContains('schedule_id', $columns);
        $this->assertContains('expires_at', $columns);
    }

    public function test_production_configuration_passes_all_critical_checks(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService())->evaluate(['paystack', 'flutterwave']);
        $summary = (new ReleaseReadinessService())->summary($checks);

        $this->assertSame(0, $summary['critical']);
    }

    public function test_unsafe_launch_configuration_fails_closed(): void
    {
        $this->configureProduction();
        config()->set('app.debug', true);
        config()->set('app.url', 'http://example.test');
        config()->set('queue.default', 'sync');
        config()->set('session.driver', 'file');
        config()->set('cache.default', 'file');
        config()->set('tickets.signing_key', config('app.key'));
        config()->set('tickets.buyer_identity_hash_key', config('app.key'));
        config()->set('services.flutterwave.secret_hash', 'short');

        $checks = (new ReleaseReadinessService())->evaluate(['flutterwave']);
        $criticalIds = array_column(array_filter($checks, fn ($check) => $check['status'] === 'critical'), 'id');

        $this->assertContains('app.debug', $criticalIds);
        $this->assertContains('app.url', $criticalIds);
        $this->assertContains('queue.driver', $criticalIds);
        $this->assertContains('session.driver', $criticalIds);
        $this->assertContains('cache.driver', $criticalIds);
        $this->assertContains('tickets.signing_key', $criticalIds);
        $this->assertContains('tickets.buyer_identity_hash_key', $criticalIds);
        $this->assertContains('flutterwave.webhook_secret', $criticalIds);
    }

    public function test_dedicated_keys_compare_decoded_material_not_configuration_encoding(): void
    {
        $this->configureProduction();
        config()->set('tickets.signing_key', str_repeat('a', 32));
        config()->set('tickets.buyer_identity_hash_key', str_repeat('a', 32));

        $checks = collect((new ReleaseReadinessService())->evaluate(['paystack']));

        $this->assertSame('critical', $checks->firstWhere('id', 'tickets.signing_key')['status']);
        $this->assertSame('critical', $checks->firstWhere('id', 'tickets.buyer_identity_hash_key')['status']);

        $this->configureProduction();
        config()->set('tickets.buyer_identity_hash_key', str_repeat('b', 32));
        $checks = collect((new ReleaseReadinessService())->evaluate(['paystack']));

        $this->assertSame('critical', $checks->firstWhere('id', 'tickets.buyer_identity_hash_key')['status']);
    }

    public function test_missing_or_invalid_vapid_credentials_block_release(): void
    {
        $this->configureProduction();
        config()->set('webpush.vapid.private_key', null);

        $missing = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'webpush.vapid');
        $this->assertSame('critical', $missing['status']);

        config()->set('webpush.vapid.private_key', str_repeat('v', 43));
        config()->set('webpush.vapid.subject', 'http://insecure.example');
        $insecure = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'webpush.vapid');
        $this->assertSame('critical', $insecure['status']);
    }

    public function test_missing_or_insecure_ticket_sms_configuration_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('ticket_delivery.sms.enabled', false);

        $disabled = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'tickets.sms_delivery');
        $this->assertSame('critical', $disabled['status']);

        config()->set('ticket_delivery.sms.enabled', true);
        config()->set('ticket_delivery.sms.base_url', 'http://api.example.test');
        $insecure = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'tickets.sms_delivery');
        $this->assertSame('critical', $insecure['status']);
    }

    public function test_ticket_sms_requires_a_current_complete_production_like_drill(): void
    {
        $this->configureProduction();
        config()->set('ticket_delivery.sms.drill_timeout_verified', false);

        $incomplete = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'tickets.sms_delivery_drill');
        $this->assertSame('critical', $incomplete['status']);
        $this->assertStringContainsString('timeout ambiguity', $incomplete['message']);

        config()->set('ticket_delivery.sms.drill_timeout_verified', true);
        config()->set('ticket_delivery.sms.production_drill_at', now()->subDays(181)->toIso8601String());
        $stale = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'tickets.sms_delivery_drill');
        $this->assertSame('critical', $stale['status']);

        config()->set('ticket_delivery.sms.production_drill_at', now()->subDay()->toIso8601String());
        $ready = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'tickets.sms_delivery_drill');
        $this->assertSame('pass', $ready['status']);
    }

    public function test_flutterwave_secret_is_required_only_when_gateway_is_enabled(): void
    {
        $this->configureProduction();
        config()->set('services.flutterwave.secret_hash', null);

        $disabled = (new ReleaseReadinessService())->evaluate(['paystack']);
        $enabled = (new ReleaseReadinessService())->evaluate(['flutterwave']);

        $this->assertNotContains('flutterwave.webhook_secret', array_column($disabled, 'id'));
        $this->assertContains('flutterwave.webhook_secret', array_column($enabled, 'id'));
    }

    public function test_legacy_enabled_gateway_blocks_release(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService())->evaluate(['paystack', 'paypal']);
        $contract = collect($checks)->firstWhere('id', 'payments.production_contract');

        $this->assertSame('critical', $contract['status']);
        $this->assertStringContainsString('paypal', $contract['message']);
    }

    public function test_retired_gateway_cannot_be_restored_by_configuration(): void
    {
        $this->configureProduction();
        config()->set('payments.production_ready_gateways', ['paystack', 'paypal']);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack', 'paypal']);
        $contract = collect($checks)->firstWhere('id', 'payments.production_contract');

        $this->assertSame('critical', $contract['status']);
    }

    public function test_legacy_gateway_cannot_be_declared_production_ready_by_configuration(): void
    {
        $this->configureProduction();
        config()->set('payments.production_ready_gateways', ['paystack', 'razorpay']);

        $contract = collect((new ReleaseReadinessService())->evaluate(['paystack', 'razorpay']))
            ->firstWhere('id', 'payments.production_contract');

        $this->assertSame('critical', $contract['status']);
        $this->assertStringContainsString('razorpay', $contract['message']);
    }

    public function test_legacy_development_opt_in_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('payments.allow_legacy_non_production', true);

        $check = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'payments.legacy_non_production_disabled');

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('ALLOW_LEGACY_NON_PRODUCTION_GATEWAYS=false', $check['message']);
    }

    public function test_enabled_certified_gateways_require_a_current_complete_payment_drill(): void
    {
        $this->configureProduction();
        config()->set('payments.drill_lost_callback_verified', false);

        $incomplete = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'payments.production_drill');
        $this->assertSame('critical', $incomplete['status']);
        $this->assertStringContainsString('lost-callback recovery', $incomplete['message']);

        config()->set('payments.drill_lost_callback_verified', true);
        config()->set('payments.production_drill_at', now()->subDays(181)->toIso8601String());
        $stale = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'payments.production_drill');
        $this->assertSame('critical', $stale['status']);

        config()->set('payments.production_drill_at', now()->subDay()->toIso8601String());
        config()->set('payments.drill_flutterwave_verified', false);
        $paystackOnly = collect((new ReleaseReadinessService())->evaluate(['paystack']))
            ->firstWhere('id', 'payments.production_drill');
        $bothGateways = collect((new ReleaseReadinessService())->evaluate(['paystack', 'flutterwave']))
            ->firstWhere('id', 'payments.production_drill');
        $this->assertSame('pass', $paystackOnly['status']);
        $this->assertSame('critical', $bothGateways['status']);
    }

    public function test_unsupported_runtime_or_framework_blocks_release(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService('8.2.20', '12.0.0'))->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)->firstWhere('id', 'runtime.php')['status']);
        $this->assertSame('critical', collect($checks)->firstWhere('id', 'runtime.framework')['status']);
    }

    public function test_missing_transactional_migration_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff(
            $service->requiredTransactionalTables(),
            ['product_payment_intents', 'product_gateway_refunds']
        ));

        $check = $service->transactionalSchemaCheck($tables);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('product_payment_intents', $check['message']);
        $this->assertStringContainsString('product_gateway_refunds', $check['message']);
    }

    public function test_booking_unit_integrity_blocks_release_and_prescribes_safe_recovery(): void
    {
        $service = new ReleaseReadinessService();

        $ready = $service->ticketUnitIntegrityCheck([
            'total_bookings' => 10,
            'affected_bookings' => 0,
            'issue_counts' => [],
        ]);
        $this->assertSame('pass', $ready['status']);

        $missing = $service->ticketUnitIntegrityCheck([
            'total_bookings' => 10,
            'affected_bookings' => 2,
            'issue_counts' => ['missing_units' => 2],
        ]);
        $this->assertSame('critical', $missing['status']);
        $this->assertStringContainsString('tickets:audit-booking-units', $missing['message']);
        $this->assertStringContainsString('tickets:issue-booking-units', $missing['message']);

        $contradictory = $service->ticketUnitIntegrityCheck([
            'total_bookings' => 10,
            'affected_bookings' => 1,
            'issue_counts' => ['unit_count_mismatch' => 1, 'sequence_mismatch' => 1],
        ]);
        $this->assertSame('critical', $contradictory['status']);
        $this->assertStringContainsString('tickets:audit-booking-units', $contradictory['message']);
        $this->assertStringContainsString('Do not auto-repair', $contradictory['message']);
        $this->assertStringNotContainsString('tickets:issue-booking-units', $contradictory['message']);
    }

    public function test_missing_settlement_schema_blocks_finance_exports_from_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff(
            $service->requiredTransactionalTables(),
            ['gateway_settlements', 'gateway_settlement_entries']
        ));

        $check = $service->transactionalSchemaCheck($tables);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('gateway_settlements', $check['message']);
        $this->assertStringContainsString('gateway_settlement_entries', $check['message']);
    }

    public function test_missing_payout_dispute_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff($service->requiredTransactionalTables(), ['payout_disputes']));

        $tableCheck = $service->transactionalSchemaCheck($tables);
        $this->assertSame('critical', $tableCheck['status']);
        $this->assertStringContainsString('payout_disputes', $tableCheck['message']);

        $columns = $service->requiredTransactionalColumns();
        $columns['payout_disputes'] = array_values(array_diff($columns['payout_disputes'], ['resolution', 'closed_at']));
        $columnCheck = $service->transactionalColumnCheck($columns);
        $this->assertSame('critical', $columnCheck['status']);
        $this->assertStringContainsString('payout_disputes.resolution', $columnCheck['message']);
        $this->assertStringContainsString('payout_disputes.closed_at', $columnCheck['message']);
    }

    public function test_missing_checked_in_refund_dispute_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff($service->requiredTransactionalTables(), ['payment_refund_disputes']));

        $tableCheck = $service->transactionalSchemaCheck($tables);
        $this->assertSame('critical', $tableCheck['status']);
        $this->assertStringContainsString('payment_refund_disputes', $tableCheck['message']);

        $columns = $service->requiredTransactionalColumns();
        $columns['payment_refund_disputes'] = array_values(array_diff(
            $columns['payment_refund_disputes'],
            ['payment_refund_id', 'resolution', 'closed_at']
        ));
        $columnCheck = $service->transactionalColumnCheck($columns);
        $this->assertSame('critical', $columnCheck['status']);
        $this->assertStringContainsString('payment_refund_disputes.payment_refund_id', $columnCheck['message']);
    }

    public function test_missing_cancellation_case_resolution_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $columns = $service->requiredTransactionalColumns();
        $columns['event_cancellation_cases'] = array_values(array_diff(
            $columns['event_cancellation_cases'],
            ['payment_refund_dispute_id', 'external_refund_reference', 'resolution', 'resolved_at']
        ));

        $check = $service->transactionalColumnCheck($columns);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('event_cancellation_cases.payment_refund_dispute_id', $check['message']);
        $this->assertStringContainsString('event_cancellation_cases.external_refund_reference', $check['message']);
        $this->assertStringContainsString('event_cancellation_cases.resolved_at', $check['message']);
    }

    public function test_missing_ticket_resend_delivery_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff(
            $service->requiredTransactionalTables(),
            ['booking_ticket_delivery_requests']
        ));
        $tableCheck = $service->transactionalSchemaCheck($tables);
        $this->assertSame('critical', $tableCheck['status']);
        $this->assertStringContainsString('booking_ticket_delivery_requests', $tableCheck['message']);

        $columns = $service->requiredTransactionalColumns();
        $columns['booking_ticket_delivery_requests'] = array_values(array_diff(
            $columns['booking_ticket_delivery_requests'],
            ['recipient_email_hash', 'processing_at', 'alerted_at']
        ));
        $columnCheck = $service->transactionalColumnCheck($columns);
        $this->assertSame('critical', $columnCheck['status']);
        $this->assertStringContainsString('booking_ticket_delivery_requests.recipient_email_hash', $columnCheck['message']);
        $this->assertStringContainsString('booking_ticket_delivery_requests.alerted_at', $columnCheck['message']);
    }

    public function test_missing_ticket_transfer_delivery_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff(
            $service->requiredTransactionalTables(),
            ['booking_ticket_transfers']
        ));
        $tableCheck = $service->transactionalSchemaCheck($tables);
        $this->assertSame('critical', $tableCheck['status']);
        $this->assertStringContainsString('booking_ticket_transfers', $tableCheck['message']);

        $columns = $service->requiredTransactionalColumns();
        $columns['booking_ticket_transfers'] = array_values(array_diff(
            $columns['booking_ticket_transfers'],
            ['delivery_status', 'processing_at', 'alerted_at']
        ));
        $columnCheck = $service->transactionalColumnCheck($columns);
        $this->assertSame('critical', $columnCheck['status']);
        $this->assertStringContainsString('booking_ticket_transfers.delivery_status', $columnCheck['message']);
        $this->assertStringContainsString('booking_ticket_transfers.alerted_at', $columnCheck['message']);
    }

    public function test_missing_organizer_verification_delivery_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff(
            $service->requiredTransactionalTables(),
            ['organizer_email_verification_deliveries']
        ));
        $tableCheck = $service->transactionalSchemaCheck($tables);
        $this->assertSame('critical', $tableCheck['status']);
        $this->assertStringContainsString('organizer_email_verification_deliveries', $tableCheck['message']);

        $columns = $service->requiredTransactionalColumns();
        $columns['organizer_email_verification_deliveries'] = array_values(array_diff(
            $columns['organizer_email_verification_deliveries'],
            ['email_hash', 'verified_at', 'alerted_at']
        ));
        $columnCheck = $service->transactionalColumnCheck($columns);
        $this->assertSame('critical', $columnCheck['status']);
        $this->assertStringContainsString('organizer_email_verification_deliveries.email_hash', $columnCheck['message']);
        $this->assertStringContainsString('organizer_email_verification_deliveries.verified_at', $columnCheck['message']);
    }

    public function test_missing_customer_verification_delivery_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $tables = array_values(array_diff(
            $service->requiredTransactionalTables(),
            ['customer_email_verification_deliveries']
        ));
        $tableCheck = $service->transactionalSchemaCheck($tables);
        $this->assertSame('critical', $tableCheck['status']);
        $this->assertStringContainsString('customer_email_verification_deliveries', $tableCheck['message']);

        $columns = $service->requiredTransactionalColumns();
        $columns['customer_email_verification_deliveries'] = array_values(array_diff(
            $columns['customer_email_verification_deliveries'],
            ['email_hash', 'verified_at', 'alerted_at']
        ));
        $columnCheck = $service->transactionalColumnCheck($columns);
        $this->assertSame('critical', $columnCheck['status']);
        $this->assertStringContainsString('customer_email_verification_deliveries.email_hash', $columnCheck['message']);
        $this->assertStringContainsString('customer_email_verification_deliveries.verified_at', $columnCheck['message']);
    }

    public function test_missing_scanner_attribution_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $scannerTables = [
            'scanner_check_in_conflicts',
            'scanner_devices',
            'scanner_offline_manifest_items',
            'scanner_offline_manifests',
            'scanner_offline_sync_items',
            'scanner_staff',
        ];
        $tables = array_values(array_diff($service->requiredTransactionalTables(), $scannerTables));

        $tableCheck = $service->transactionalSchemaCheck($tables);

        $this->assertSame('critical', $tableCheck['status']);
        foreach ($scannerTables as $table) {
            $this->assertStringContainsString($table, $tableCheck['message']);
        }

        $columns = $service->requiredTransactionalColumns();
        $columns['booking_tickets'] = array_values(array_diff(
            $columns['booking_tickets'],
            ['checked_in_scanner_staff_id', 'checked_in_source']
        ));
        $columns['scanner_offline_manifest_items'] = array_values(array_diff(
            $columns['scanner_offline_manifest_items'],
            ['payload_hash']
        ));

        $columnCheck = $service->transactionalColumnCheck($columns);

        $this->assertSame('critical', $columnCheck['status']);
        $this->assertStringContainsString('booking_tickets.checked_in_scanner_staff_id', $columnCheck['message']);
        $this->assertStringContainsString('booking_tickets.checked_in_source', $columnCheck['message']);
        $this->assertStringContainsString('scanner_offline_manifest_items.payload_hash', $columnCheck['message']);
    }

    public function test_missing_free_checkout_idempotency_column_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $columns = $service->requiredTransactionalColumns();
        $columns['bookings'] = array_values(array_diff($columns['bookings'], ['checkout_submission_hash']));

        $check = $service->transactionalColumnCheck($columns);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('bookings.checkout_submission_hash', $check['message']);
    }

    public function test_missing_guest_buyer_identity_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $columns = $service->requiredTransactionalColumns();
        $columns['bookings'] = array_values(array_diff($columns['bookings'], ['buyer_email_hash']));
        $columns['ticket_reservations'] = array_values(array_diff(
            $columns['ticket_reservations'],
            ['buyer_email_hash'],
        ));

        $check = $service->transactionalColumnCheck($columns);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('bookings.buyer_email_hash', $check['message']);
        $this->assertStringContainsString('ticket_reservations.buyer_email_hash', $check['message']);
    }

    public function test_guest_buyer_identity_backfill_must_be_complete(): void
    {
        $service = new ReleaseReadinessService();

        $ready = $service->guestBuyerIdentityIntegrityCheck(0, 0);
        $blocked = $service->guestBuyerIdentityIntegrityCheck(3, 2);

        $this->assertSame('pass', $ready['status']);
        $this->assertSame('critical', $blocked['status']);
        $this->assertStringContainsString('3 booking(s)', $blocked['message']);
        $this->assertStringContainsString('2 active reservation(s)', $blocked['message']);
    }

    public function test_complete_transactional_schema_passes_release_check(): void
    {
        $service = new ReleaseReadinessService();

        $check = $service->transactionalSchemaCheck($service->requiredTransactionalTables());

        $this->assertSame('pass', $check['status']);
    }

    public function test_missing_transactional_column_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $columns = $service->requiredTransactionalColumns();
        $columns['tickets'] = [];

        $check = $service->transactionalColumnCheck($columns);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('tickets.deleted_at', $check['message']);

        $columns = $service->requiredTransactionalColumns();
        $columns['event_registration_questions'] = array_values(array_diff(
            $columns['event_registration_questions'], ['settings']
        ));
        $check = $service->transactionalColumnCheck($columns);
        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('event_registration_questions.settings', $check['message']);
    }

    public function test_missing_settlement_column_blocks_finance_exports_from_release(): void
    {
        $service = new ReleaseReadinessService();
        $columns = $service->requiredTransactionalColumns();
        $columns['gateway_settlement_entries'] = array_values(array_diff(
            $columns['gateway_settlement_entries'],
            ['gateway_fee', 'checked_at']
        ));

        $check = $service->transactionalColumnCheck($columns);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('gateway_settlement_entries.gateway_fee', $check['message']);
        $this->assertStringContainsString('gateway_settlement_entries.checked_at', $check['message']);
    }

    public function test_missing_recurring_export_contract_blocks_release(): void
    {
        $service = new ReleaseReadinessService();
        $columns = $service->requiredTransactionalColumns();
        $columns['data_export_schedules'] = array_values(array_diff(
            $columns['data_export_schedules'],
            ['next_run_at', 'requested_by_id']
        ));

        $check = $service->transactionalColumnCheck($columns);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('data_export_schedules.next_run_at', $check['message']);
        $this->assertStringContainsString('data_export_schedules.requested_by_id', $check['message']);
    }

    public function test_complete_transactional_column_contract_passes_release_check(): void
    {
        $service = new ReleaseReadinessService();

        $check = $service->transactionalColumnCheck($service->requiredTransactionalColumns());

        $this->assertSame('pass', $check['status']);
    }

    public function test_missing_transactional_identity_constraint_blocks_release(): void
    {
        $service = new ReleaseReadinessService();

        $missing = $service->transactionalIndexCheck(['booking_deliveries' => []]);
        $ready = $service->transactionalIndexCheck($service->requiredTransactionalIndexes());

        $this->assertSame('critical', $missing['status']);
        $this->assertStringContainsString(
            'booking_deliveries.booking_delivery_provider_reference_unique',
            $missing['message']
        );
        $this->assertSame('pass', $ready['status']);
    }

    public function test_platform_earning_singleton_requires_one_authoritative_row(): void
    {
        $service = new ReleaseReadinessService();

        $this->assertSame('pass', $service->platformEarningSingletonCheck([1])['status']);

        foreach ([[], [1, 1], [0], [2]] as $invalidKeys) {
            $check = $service->platformEarningSingletonCheck($invalidKeys);

            $this->assertSame('critical', $check['status']);
            $this->assertStringContainsString('exactly one authoritative row', $check['message']);
        }
    }

    public function test_missing_frontend_build_blocks_release(): void
    {
        $manifest = storage_path('framework/testing/missing-vite-build/manifest.json');
        File::deleteDirectory(dirname($manifest));

        $check = (new ReleaseReadinessService())->frontendAssetCheck($manifest);

        $this->assertSame('critical', $check['status']);
        $this->assertStringContainsString('npm ci', $check['message']);
    }

    public function test_frontend_manifest_must_reference_a_real_asset_inside_build_directory(): void
    {
        $directory = storage_path('framework/testing/invalid-vite-build');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/manifest.json', json_encode([
            'resources/css/site-chrome.css' => ['file' => '../outside.css'],
        ], JSON_THROW_ON_ERROR));
        File::put(dirname($directory).'/outside.css', 'unsafe');

        try {
            $check = (new ReleaseReadinessService())->frontendAssetCheck($directory.'/manifest.json');
            $this->assertSame('critical', $check['status']);
        } finally {
            File::delete(dirname($directory).'/outside.css');
            File::deleteDirectory($directory);
        }
    }

    public function test_complete_frontend_build_passes_release_check(): void
    {
        $directory = storage_path('framework/testing/valid-vite-build');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory.'/assets');
        File::put($directory.'/assets/site-chrome.css', ':root{}');
        File::put($directory.'/manifest.json', json_encode([
            'resources/css/site-chrome.css' => ['file' => 'assets/site-chrome.css'],
        ], JSON_THROW_ON_ERROR));

        try {
            $check = (new ReleaseReadinessService())->frontendAssetCheck($directory.'/manifest.json');
            $this->assertSame('pass', $check['status']);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_paystack_payouts_cannot_be_enabled_without_paystack_checkout(): void
    {
        $this->configureProduction();
        config()->set('payouts.paystack_transfers_enabled', true);

        $checks = (new ReleaseReadinessService())->evaluate(['flutterwave']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'payouts.paystack_gateway')['status']);
    }

    public function test_automatic_payout_release_fails_closed_until_policy_is_implemented(): void
    {
        $this->configureProduction();
        config()->set('payouts.automatic_release_enabled', true);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'payouts.automatic_release')['status']);
    }

    public function test_automatic_payout_release_passes_with_approved_valid_policy(): void
    {
        $this->configureProduction();
        config()->set('payouts.paystack_transfers_enabled', true);
        config()->set('payouts.automatic_release_enabled', true);
        config()->set('payouts.eligibility_policy', [
            'version' => '2026-11-v1',
            'approved' => true,
            'approval_reference' => 'FIN-RISK-2026-11',
            'settlement_owner' => 'platform',
            'timezone' => 'Africa/Lagos',
            'release_hour' => 10,
            'business_days' => [1, 2, 3, 4, 5],
            'holiday_dates' => [],
            'minimum_organizer_age_days' => 14,
            'minimum_kyc_age_days' => 7,
            'minimum_account_age_days' => 3,
            'minimum_successful_payouts' => 1,
            'minimum_amount' => 1000,
            'maximum_amount' => 500000,
            'daily_organizer_limit' => 1000000,
            'batch_limit' => 100,
            'scan_limit' => 5000,
        ]);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'payouts.automatic_release')['status']);
    }

    public function test_paystack_payouts_require_a_finance_alert_recipient(): void
    {
        $this->configureProduction();
        config()->set('payouts.paystack_transfers_enabled', true);
        config()->set('payouts.finance_email', null);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'payouts.finance_email')['status']);
    }

    public function test_paystack_payouts_require_a_current_production_like_drill(): void
    {
        $this->configureProduction();
        config()->set('payouts.paystack_transfers_enabled', true);
        config()->set('payouts.production_drill_at', null);
        config()->set('payouts.production_drill_reference', null);

        $missing = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($missing)
            ->firstWhere('id', 'payouts.production_drill')['status']);

        config()->set('payouts.production_drill_at', now()->subDay()->toIso8601String());
        config()->set('payouts.production_drill_reference', 'PAYOUT-DRILL-2026-11');
        $current = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('pass', collect($current)
            ->firstWhere('id', 'payouts.production_drill')['status']);
    }

    public function test_production_requires_an_external_incident_destination(): void
    {
        $this->configureProduction();
        config()->set('operations.incident_email', null);
        config()->set('operations.incident_webhook_url', null);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'operations.incident_destination')['status']);
    }

    public function test_incident_webhook_must_be_https_and_signed(): void
    {
        $this->configureProduction();
        config()->set('operations.incident_email', null);
        config()->set('operations.incident_webhook_url', 'http://alerts.example.test/hook');
        config()->set('operations.incident_webhook_secret', 'short');

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'operations.incident_webhook')['status']);
    }

    public function test_missing_backup_contract_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('operations.backup.database_enabled', false);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'operations.backup_policy')['status']);
    }

    public function test_stale_or_future_restore_drill_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('operations.backup.restore_drill_at', now()->subDays(91)->toIso8601String());

        $stale = (new ReleaseReadinessService())->evaluate(['paystack']);
        $this->assertSame('critical', collect($stale)
            ->firstWhere('id', 'operations.restore_drill')['status']);

        config()->set('operations.backup.restore_drill_at', now()->addDay()->toIso8601String());
        $future = (new ReleaseReadinessService())->evaluate(['paystack']);
        $this->assertSame('critical', collect($future)
            ->firstWhere('id', 'operations.restore_drill')['status']);
    }

    public function test_current_restore_drill_and_complete_backup_contract_pass(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'operations.backup_policy')['status']);
        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'operations.restore_drill')['status']);
    }

    public function test_missing_privacy_governance_or_stale_review_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('privacy.owner_email', null);

        $missing = (new ReleaseReadinessService())->evaluate(['paystack']);
        $this->assertSame('critical', collect($missing)
            ->firstWhere('id', 'privacy.governance_policy')['status']);

        $this->configureProduction();
        config()->set('privacy.vendor_dpa_review_at', now()->subDays(366)->toIso8601String());
        $stale = (new ReleaseReadinessService())->evaluate(['paystack']);
        $this->assertSame('critical', collect($stale)
            ->firstWhere('id', 'privacy.launch_review')['status']);
    }

    public function test_complete_current_privacy_governance_passes(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'privacy.governance_policy')['status']);
        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'privacy.launch_review')['status']);
    }

    public function test_missing_or_unbounded_audit_retention_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('audit.retention_enabled', false);
        $this->assertSame('critical', collect((new ReleaseReadinessService())->evaluate())
            ->firstWhere('id', 'audit.retention_policy')['status']);

        config()->set('audit.retention_enabled', true);
        config()->set('audit.retention_days', 5000);
        $this->assertSame('critical', collect((new ReleaseReadinessService())->evaluate())
            ->firstWhere('id', 'audit.retention_policy')['status']);

        config()->set('audit.retention_days', 2555);
        $this->assertSame('pass', collect((new ReleaseReadinessService())->evaluate())
            ->firstWhere('id', 'audit.retention_policy')['status']);
    }

    public function test_missing_or_partial_accessibility_audit_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('accessibility.scanner_verified', false);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'accessibility.wcag_audit')['status']);
    }

    public function test_current_complete_accessibility_audit_passes(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'accessibility.wcag_audit')['status']);
    }

    public function test_missing_or_stale_security_assurance_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('security.penetration_test_at', now()->subDays(181)->toIso8601String());

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'security.penetration_test')['status']);
        config()->set('security.key_rotation_reference', null);
        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);
        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'security.key_rotation')['status']);
    }

    public function test_current_security_assurance_and_incident_drill_pass(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'security.penetration_test')['status']);
        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'security.key_rotation')['status']);
        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'security.incident_drill')['status']);
    }

    public function test_missing_or_wrong_host_external_monitoring_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('operations.monitoring.readiness_url', 'https://other.example/health/ready');

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'operations.external_monitoring')['status']);
    }

    public function test_stale_monitoring_exercise_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('operations.monitoring.exercise_at', now()->subDays(91)->toIso8601String());

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'operations.monitoring_exercise')['status']);
    }

    public function test_complete_external_monitoring_contract_passes(): void
    {
        $this->configureProduction();

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'operations.external_monitoring')['status']);
        $this->assertSame('pass', collect($checks)
            ->firstWhere('id', 'operations.monitoring_exercise')['status']);
    }

    public function test_web_installer_or_updater_enabled_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('installer.updaterEnabled', true);

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'security.web_installer')['status']);
    }

    public function test_public_product_document_storage_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('filesystems.product_order_documents_disk', 'public');

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'storage.product_order_documents')['status']);
    }

    public function test_public_event_booking_document_storage_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('filesystems.event_booking_documents_disk', 'public');

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'storage.event_booking_documents')['status']);
    }

    public function test_public_registration_import_storage_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('registration.import_disk', 'public');

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'storage.registration_imports')['status']);
    }

    public function test_public_registration_upload_storage_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('registration.upload_disk', 'public');

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'storage.registration_uploads')['status']);
    }

    public function test_nonexistent_document_root_below_public_directory_blocks_release(): void
    {
        $this->configureProduction();
        config()->set('filesystems.disks.unsafe_event_documents', [
            'driver' => 'local',
            'root' => public_path('not-created/event-documents'),
            'visibility' => 'private',
        ]);
        config()->set('filesystems.event_booking_documents_disk', 'unsafe_event_documents');

        $checks = (new ReleaseReadinessService())->evaluate(['paystack']);

        $this->assertSame('critical', collect($checks)
            ->firstWhere('id', 'storage.event_booking_documents')['status']);
    }

    private function configureProduction(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.url', 'https://tica.example');
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('tickets.signing_key', 'base64:' . base64_encode(str_repeat('b', 32)));
        config()->set('tickets.buyer_identity_hash_key', 'base64:' . base64_encode(str_repeat('d', 32)));
        config()->set('queue.default', 'redis');
        config()->set('session.driver', 'redis');
        config()->set('cache.default', 'redis');
        config()->set('mail.default', 'smtp');
        config()->set('mail.from.address', 'tickets@tica.example');
        config()->set('ticket_delivery.sms', [
            'enabled' => true,
            'driver' => 'termii',
            'base_url' => 'https://api.ng.termii.com',
            'api_key' => str_repeat('s', 32),
            'sender_id' => 'Tica',
            'channel' => 'dnd',
            'default_country_code' => '234',
            'connect_timeout_seconds' => 5,
            'timeout_seconds' => 10,
            'owner_email' => 'messaging@tica.example',
            'production_drill_at' => now()->subDay()->toIso8601String(),
            'production_drill_reference' => 'SMS-DRILL-2026-11',
            'production_drill_max_age_days' => 180,
            'drill_local_number_verified' => true,
            'drill_international_number_verified' => true,
            'drill_rejection_verified' => true,
            'drill_timeout_verified' => true,
            'drill_signed_link_verified' => true,
            'drill_worker_sla_verified' => true,
        ]);
        config()->set('webpush.vapid.subject', 'mailto:platform@tica.example');
        config()->set('webpush.vapid.public_key', str_repeat('u', 87));
        config()->set('webpush.vapid.private_key', str_repeat('v', 43));
        config()->set('security.enforce_content_security_policy', true);
        config()->set('security.owner_email', 'security@tica.example');
        config()->set('security.penetration_test_at', now()->subDays(30)->toIso8601String());
        config()->set('security.penetration_test_reference', 'SEC-PENTEST-2026-11');
        config()->set('security.penetration_test_max_age_days', 180);
        config()->set('security.key_rotation_at', now()->subDay()->toIso8601String());
        config()->set('security.key_rotation_reference', 'SEC-KEYS-2026-11');
        config()->set('security.key_rotation_max_age_days', 365);
        config()->set('security.incident_drill_at', now()->subDay()->toIso8601String());
        config()->set('security.incident_drill_reference', 'SEC-DRILL-2026-11');
        config()->set('security.incident_drill_max_age_days', 180);
        config()->set('logging.channels.single.level', 'info');
        config()->set('services.flutterwave.secret_hash', str_repeat('c', 32));
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);
        config()->set('payments.allow_legacy_non_production', false);
        config()->set('payments.operations_owner_email', 'payments@tica.example');
        config()->set('payments.production_drill_at', now()->subDay()->toIso8601String());
        config()->set('payments.production_drill_reference', 'PAYMENT-DRILL-2026-11');
        config()->set('payments.production_drill_max_age_days', 180);
        config()->set('payments.drill_paystack_verified', true);
        config()->set('payments.drill_flutterwave_verified', true);
        config()->set('payments.drill_shop_paystack_verified', true);
        config()->set('payments.drill_event_checkout_verified', true);
        config()->set('payments.drill_webhook_verified', true);
        config()->set('payments.drill_lost_callback_verified', true);
        config()->set('payments.drill_reservation_expiry_verified', true);
        config()->set('payments.drill_refund_verified', true);
        config()->set('payments.drill_reconciliation_verified', true);
        config()->set('payouts.paystack_transfers_enabled', false);
        config()->set('payouts.automatic_release_enabled', false);
        config()->set('payouts.finance_email', 'finance@tica.example');
        config()->set('payouts.production_drill_at', null);
        config()->set('payouts.production_drill_reference', null);
        config()->set('payouts.production_drill_max_age_days', 180);
        config()->set('operations.incident_email', 'oncall@tica.example');
        config()->set('operations.incident_webhook_url', null);
        config()->set('operations.incident_webhook_secret', null);
        config()->set('operations.backup', [
            'owner_email' => 'platform-owner@tica.example',
            'provider' => 'managed-encrypted-backup',
            'database_enabled' => true,
            'private_files_enabled' => true,
            'encryption_enabled' => true,
            'offsite_enabled' => true,
            'rpo_minutes' => 60,
            'rto_minutes' => 240,
            'restore_drill_at' => now()->subDay()->toIso8601String(),
            'restore_drill_reference' => 'OPS-RESTORE-2026-11',
            'restore_drill_max_age_days' => 90,
        ]);
        config()->set('operations.monitoring', [
            'owner_email' => 'observability@tica.example',
            'external_uptime_enabled' => true,
            'error_tracking_enabled' => true,
            'synthetic_checkout_enabled' => true,
            'liveness_url' => 'https://tica.example/health/live',
            'readiness_url' => 'https://tica.example/health/ready',
            'exercise_at' => now()->subDay()->toIso8601String(),
            'exercise_reference' => 'OBS-DRILL-2026-11',
            'exercise_max_age_days' => 90,
        ]);
        config()->set('installer.webInstallerEnabled', false);
        config()->set('installer.updaterEnabled', false);
        config()->set('filesystems.product_order_documents_disk', 'local');
        config()->set('filesystems.event_booking_documents_disk', 'local');
        config()->set('registration.import_disk', 'local');
        config()->set('registration.retention_enabled', true);
        config()->set('registration.retention_days', 730);
        config()->set('tickets.guest_retention_enabled', true);
        config()->set('tickets.guest_retention_days', 730);
        config()->set('exports.retention_days', 7);
        config()->set('audit.retention_enabled', true);
        config()->set('audit.retention_days', 2555);
        config()->set('privacy', [
            'owner_email' => 'privacy@tica.example',
            'policy_version' => 'tica-privacy-2026-11',
            'breach_plan_reference' => 'SEC-IR-PRIVACY-01',
            'vendor_dpa_review_at' => now()->subDays(30)->toIso8601String(),
            'launch_review_at' => now()->subDay()->toIso8601String(),
            'launch_review_reference' => 'LEGAL-PRIVACY-LAUNCH-01',
            'review_max_age_days' => 365,
        ]);
        config()->set('accessibility', [
            'owner_email' => 'accessibility@tica.example',
            'audit_standard' => 'WCAG 2.2 AA',
            'audit_at' => now()->subDay()->toIso8601String(),
            'audit_reference' => 'A11Y-LAUNCH-2026-11',
            'audit_max_age_days' => 180,
            'keyboard_verified' => true,
            'screen_reader_verified' => true,
            'checkout_verified' => true,
            'scanner_verified' => true,
        ]);
    }
}
