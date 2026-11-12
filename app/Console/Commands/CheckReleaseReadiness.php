<?php

namespace App\Console\Commands;

use App\Casts\PrefixedEncryptedString;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\BasicSettings\Basic;
use App\Models\Event\Booking;
use App\Models\ShopManagement\ProductOrder;
use App\Services\ReleaseReadinessService;
use App\Services\LegacyPublicCheckoutDocuments;
use App\Services\LegacyPublicTicketQrArtifacts;
use App\Services\QueueHealthService;
use App\Services\SharedStateHealthService;
use App\Services\TicketUnitIntegrityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CheckReleaseReadiness extends Command
{
    protected $signature = 'ops:release-readiness
        {--json : Emit machine-readable JSON}
        {--configuration-only : Skip live worker heartbeat checks}';

    protected $description = 'Fail closed when critical production launch prerequisites are missing';

    public function handle(
        ReleaseReadinessService $service,
        LegacyPublicCheckoutDocuments $legacyPublicDocuments,
        LegacyPublicTicketQrArtifacts $legacyQrArtifacts,
        TicketUnitIntegrityService $ticketUnitIntegrity,
    ): int
    {
        $enabledGateways = [];
        $legacyProductDocuments = null;
        $legacyEventDocuments = null;
        $legacyProductPublicFiles = null;
        $legacyEventPublicFiles = null;
        $transactionalSchemaCheck = null;
        $transactionalColumnCheck = null;
        $transactionalIndexCheck = null;
        $platformEarningCheck = null;
        $guestBuyerIdentityCheck = null;
        $ticketUnitIntegrityCheck = null;
        $unencryptedGatewayCredentials = null;
        $unencryptedBasicSecrets = null;
        $paymentIntentSensitiveFieldsCheck = null;
        $financeDiagnosticsEncryptedCheck = null;
        $operationalDiagnosticsEncryptedCheck = null;
        $databaseAvailable = false;
        $databaseCheck = ['id' => 'database.connection', 'status' => 'pass', 'message' => 'Ready.'];

        try {
            $legacyProductPublicFiles = count($legacyPublicDocuments->productFiles());
            $legacyEventPublicFiles = count($legacyPublicDocuments->eventFiles());
        } catch (\Throwable $exception) {
            Log::error('Legacy public checkout-document inspection failed.', [
                'exception_type' => $exception::class,
                'exception_code' => (string) $exception->getCode(),
            ]);
        }

        try {
            DB::select('SELECT 1');
            $databaseAvailable = true;
            $enabledGateways = OnlineGateway::where('status', 1)
                ->pluck('keyword')
                ->map(fn ($keyword) => strtolower((string) $keyword))
                ->all();
            $transactionalSchemaCheck = $service->transactionalSchemaCheck(array_values(array_filter(
                $service->requiredTransactionalTables(),
                static fn (string $table): bool => Schema::hasTable($table)
            )));
            $transactionalColumnCheck = $service->transactionalColumnCheck(collect(
                $service->requiredTransactionalColumns()
            )->mapWithKeys(static function (array $columns, string $table): array {
                return [$table => Schema::hasTable($table) ? Schema::getColumnListing($table) : []];
            })->all());
            $transactionalIndexCheck = $service->transactionalIndexCheck(collect(
                $service->requiredTransactionalIndexes()
            )->mapWithKeys(static function (array $indexes, string $table): array {
                if (!Schema::hasTable($table)) {
                    return [$table => []];
                }

                return [$table => collect(Schema::getIndexes($table))
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all()];
            })->all());
            if (Schema::hasTable('earnings') && Schema::hasColumn('earnings', 'singleton_key')) {
                $platformEarningCheck = $service->platformEarningSingletonCheck(
                    DB::table('earnings')->orderBy('id')->pluck('singleton_key')->all()
                );
            }
            $buyerIdentitySchemaReady = Schema::hasTable('bookings')
                && Schema::hasColumn('bookings', 'buyer_email_hash')
                && Schema::hasColumn('bookings', 'buyer_email_hash_version')
                && Schema::hasTable('ticket_reservations')
                && Schema::hasColumn('ticket_reservations', 'buyer_email_hash')
                && Schema::hasColumn('ticket_reservations', 'buyer_email_hash_version');
            if ($buyerIdentitySchemaReady) {
                $buyerIdentityVersion = app(\App\Services\EventTicketPurchaseLimitService::class)
                    ->buyerIdentityHashVersion();
                $guestBuyer = static fn ($query) => $query
                    ->whereNull('customer_id')
                    ->orWhere('customer_id', '');
                $guestBuyerIdentityCheck = $service->guestBuyerIdentityIntegrityCheck(
                    DB::table('bookings')
                        ->where($guestBuyer)
                        ->where(static fn ($query) => $query
                            ->whereNull('buyer_email_hash')
                            ->orWhereNull('buyer_email_hash_version')
                            ->orWhere('buyer_email_hash_version', '<>', $buyerIdentityVersion))
                        ->count(),
                    DB::table('ticket_reservations')
                        ->where($guestBuyer)
                        ->where('status', 'active')
                        ->where(static fn ($query) => $query
                            ->whereNull('buyer_email_hash')
                            ->orWhereNull('buyer_email_hash_version')
                            ->orWhere('buyer_email_hash_version', '<>', $buyerIdentityVersion))
                        ->count(),
                );
            }
            $ticketUnitColumns = [
                'bookings' => ['id', 'booking_id', 'event_id', 'organizer_id', 'ticket_id', 'variation', 'quantity', 'scan_status'],
                'booking_tickets' => ['id', 'booking_id', 'event_id', 'organizer_id', 'ticket_id', 'sequence', 'variation_name'],
            ];
            $ticketUnitSchemaReady = collect($ticketUnitColumns)->every(
                static fn (array $columns, string $table): bool => Schema::hasTable($table)
                    && collect($columns)->every(static fn (string $column): bool => Schema::hasColumn($table, $column))
            );
            if ($ticketUnitSchemaReady) {
                $ticketUnitIntegrityCheck = $service->ticketUnitIntegrityCheck($ticketUnitIntegrity->inspect());
            }
            $unencryptedGatewayCredentials = DB::table('online_gateways')
                ->where('information', 'not like', OnlineGateway::ENCRYPTED_INFORMATION_PREFIX.'%')
                ->count();
            $unencryptedBasicSecrets = 0;
            foreach (Basic::SECRET_ATTRIBUTES as $attribute) {
                $unencryptedBasicSecrets += DB::table('basic_settings')
                    ->whereNotNull($attribute)
                    ->where($attribute, '<>', '')
                    ->where($attribute, 'not like', Basic::ENCRYPTED_SECRET_PREFIX.'%')
                    ->count();
            }
            $paymentIntentTables = ['payment_intents', 'product_payment_intents'];
            $paymentIntentSchemaReady = collect($paymentIntentTables)->every(
                static fn (string $table): bool => Schema::hasTable($table)
                    && Schema::hasColumn($table, 'email')
                    && Schema::hasColumn($table, 'last_error')
            );
            if ($paymentIntentSchemaReady) {
                $plaintextSensitiveFields = collect($paymentIntentTables)->sum(
                    static fn (string $table): int => DB::table($table)
                        ->where(static fn ($query) => $query
                            ->whereNotNull('email')
                            ->where('email', '<>', '')
                            ->where('email', 'not like', PrefixedEncryptedString::PREFIX.'%'))
                        ->orWhere(static fn ($query) => $query
                            ->whereNotNull('last_error')
                            ->where('last_error', '<>', '')
                            ->where('last_error', 'not like', PrefixedEncryptedString::PREFIX.'%'))
                        ->count()
                );
                $paymentIntentSensitiveFieldsCheck = [
                    'id' => 'security.payment_intent_sensitive_fields_encrypted',
                    'status' => $plaintextSensitiveFields === 0 ? 'pass' : 'critical',
                    'message' => $plaintextSensitiveFields === 0
                        ? 'Ready.'
                        : "{$plaintextSensitiveFields} payment intent row(s) retain plaintext buyer email or provider diagnostics. Apply the payment-intent sensitive-field migration before traffic.",
                ];
            } else {
                $paymentIntentSensitiveFieldsCheck = [
                    'id' => 'security.payment_intent_sensitive_fields_encrypted',
                    'status' => 'critical',
                    'message' => 'Payment-intent sensitive-field encryption cannot be verified until both intent schemas are current.',
                ];
            }
            $financeDiagnosticTables = [
                'payment_refunds',
                'product_gateway_refunds',
                'payout_transfers',
                'gateway_settlements',
                'payment_reconciliation_runs',
            ];
            $financeDiagnosticSchemaReady = collect($financeDiagnosticTables)->every(
                static fn (string $table): bool => Schema::hasTable($table)
                    && Schema::hasColumn($table, 'last_error')
            );
            if ($financeDiagnosticSchemaReady) {
                $plaintextFinanceDiagnostics = collect($financeDiagnosticTables)->sum(
                    static fn (string $table): int => DB::table($table)
                        ->whereNotNull('last_error')
                        ->where('last_error', '<>', '')
                        ->where('last_error', 'not like', PrefixedEncryptedString::PREFIX.'%')
                        ->count()
                );
                $financeDiagnosticsEncryptedCheck = [
                    'id' => 'security.finance_diagnostics_encrypted',
                    'status' => $plaintextFinanceDiagnostics === 0 ? 'pass' : 'critical',
                    'message' => $plaintextFinanceDiagnostics === 0
                        ? 'Ready.'
                        : "{$plaintextFinanceDiagnostics} finance row(s) retain plaintext provider diagnostics. Apply the finance diagnostic-field migration before traffic.",
                ];
            } else {
                $financeDiagnosticsEncryptedCheck = [
                    'id' => 'security.finance_diagnostics_encrypted',
                    'status' => 'critical',
                    'message' => 'Finance diagnostic encryption cannot be verified until all refund, payout and reconciliation schemas are current.',
                ];
            }
            $operationalDiagnosticTables = [
                'booking_deliveries',
                'booking_ticket_delivery_requests',
                'customer_email_verification_deliveries',
                'data_exports',
                'data_export_schedules',
                'booking_ticket_transfers',
                'event_cancellation_notifications',
                'event_registration_imports',
                'event_registration_notifications',
                'event_waitlist_entries',
                'organizer_email_verification_deliveries',
                'product_order_deliveries',
            ];
            $operationalDiagnosticSchemaReady = collect($operationalDiagnosticTables)->every(
                static fn (string $table): bool => Schema::hasTable($table)
                    && Schema::hasColumn($table, 'last_error')
            );
            if ($operationalDiagnosticSchemaReady) {
                $plaintextOperationalDiagnostics = collect($operationalDiagnosticTables)->sum(
                    static fn (string $table): int => DB::table($table)
                        ->whereNotNull('last_error')
                        ->where('last_error', '<>', '')
                        ->where('last_error', 'not like', PrefixedEncryptedString::PREFIX.'%')
                        ->count()
                );
                $operationalDiagnosticsEncryptedCheck = [
                    'id' => 'security.operational_diagnostics_encrypted',
                    'status' => $plaintextOperationalDiagnostics === 0 ? 'pass' : 'critical',
                    'message' => $plaintextOperationalDiagnostics === 0
                        ? 'Ready.'
                        : "{$plaintextOperationalDiagnostics} operational row(s) retain plaintext delivery or processing diagnostics. Apply the operational diagnostic-field migration before traffic.",
                ];
            } else {
                $operationalDiagnosticsEncryptedCheck = [
                    'id' => 'security.operational_diagnostics_encrypted',
                    'status' => 'critical',
                    'message' => 'Operational diagnostic encryption cannot be verified until all delivery, export and registration schemas are current.',
                ];
            }
            $legacyProductDocuments = ProductOrder::query()
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->whereNotNull('receipt')->where('receipt', 'not like', 'private/product-orders/%');
                    })->orWhere(function ($query): void {
                        $query->whereNotNull('invoice_number')->where('invoice_number', 'not like', 'private/product-orders/%');
                    });
                })
                ->count();
            $legacyEventDocuments = Booking::query()
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->whereNotNull('invoice')->where('invoice', 'not like', 'private/event-bookings/%');
                    })->orWhere(function ($query): void {
                        $query->whereNotNull('attachmentFile')->where('attachmentFile', 'not like', 'private/event-bookings/%');
                    });
                })
                ->count();
        } catch (\Throwable $exception) {
            Log::error('Release readiness database check failed.', [
                'exception_type' => $exception::class,
                'exception_code' => (string) $exception->getCode(),
            ]);
            $databaseCheck = [
                'id' => 'database.connection',
                'status' => 'critical',
                'message' => 'Database connection failed. Review protected application logs and verify the deployment-managed database configuration.',
            ];
        }

        $configurationChecks = $service->evaluate($enabledGateways);
        if (!$databaseAvailable) {
            $configurationChecks = array_map(static function (array $check): array {
                if ($check['id'] !== 'payments.production_contract') {
                    return $check;
                }

                return [
                    'id' => 'payments.production_contract',
                    'status' => 'critical',
                    'message' => 'The enabled gateway inventory cannot be verified while the database is unavailable.',
                ];
            }, $configurationChecks);
        }

        $checks = array_merge([$databaseCheck], $configurationChecks);
        $checks[] = $service->frontendAssetCheck(public_path('build/manifest.json'));
        if (!$databaseAvailable) {
            $checks = array_merge($checks, $this->databaseUnavailableChecks());
        }
        if ($transactionalSchemaCheck !== null) {
            $checks[] = $transactionalSchemaCheck;
        }
        if ($transactionalColumnCheck !== null) {
            $checks[] = $transactionalColumnCheck;
        }
        if ($transactionalIndexCheck !== null) {
            $checks[] = $transactionalIndexCheck;
        }
        if ($platformEarningCheck !== null) {
            $checks[] = $platformEarningCheck;
        }
        if ($guestBuyerIdentityCheck !== null) {
            $checks[] = $guestBuyerIdentityCheck;
        }
        if ($ticketUnitIntegrityCheck !== null) {
            $checks[] = $ticketUnitIntegrityCheck;
        }
        if ($unencryptedGatewayCredentials !== null) {
            $checks[] = [
                'id' => 'security.gateway_credentials_encrypted',
                'status' => $unencryptedGatewayCredentials === 0 ? 'pass' : 'critical',
                'message' => $unencryptedGatewayCredentials === 0
                    ? 'Ready.'
                    : "{$unencryptedGatewayCredentials} online gateway configuration row(s) remain plaintext. Back up the database, run gateways:encrypt-credentials --apply, and re-run readiness.",
            ];
        }
        if ($unencryptedBasicSecrets !== null) {
            $checks[] = [
                'id' => 'security.basic_setting_secrets_encrypted',
                'status' => $unencryptedBasicSecrets === 0 ? 'pass' : 'critical',
                'message' => $unencryptedBasicSecrets === 0
                    ? 'Ready.'
                    : "{$unencryptedBasicSecrets} SMTP, CAPTCHA, or social-login secret value(s) remain plaintext. Back up the database, run settings:encrypt-secrets --apply, and re-run readiness.",
            ];
        }
        if ($paymentIntentSensitiveFieldsCheck !== null) {
            $checks[] = $paymentIntentSensitiveFieldsCheck;
        }
        if ($financeDiagnosticsEncryptedCheck !== null) {
            $checks[] = $financeDiagnosticsEncryptedCheck;
        }
        if ($operationalDiagnosticsEncryptedCheck !== null) {
            $checks[] = $operationalDiagnosticsEncryptedCheck;
        }
        $legacyQrCount = $legacyQrArtifacts->count();
        $checks[] = [
            'id' => 'storage.legacy_public_ticket_qr_artifacts',
            'status' => $legacyQrCount === 0 ? 'pass' : 'critical',
            'message' => $legacyQrCount === 0
                ? 'Ready.'
                : "{$legacyQrCount} reusable ticket QR SVG artifact(s) remain in the public web root. Run tickets:purge-public-qr-artifacts --apply after validating private invoices.",
        ];
        if ($legacyProductDocuments !== null) {
            $checks[] = $service->legacyPublicDocumentsCheck(
                'storage.product_order_legacy_documents',
                'product order',
                $legacyProductDocuments,
                $legacyProductPublicFiles,
                'product-orders:migrate-private-documents --apply',
            );
        }
        if ($legacyEventDocuments !== null) {
            $checks[] = $service->legacyPublicDocumentsCheck(
                'storage.event_booking_legacy_documents',
                'event booking',
                $legacyEventDocuments,
                $legacyEventPublicFiles,
                'event-bookings:migrate-private-documents --apply',
            );
        }
        if (!$this->option('configuration-only')) {
            $sharedState = app(SharedStateHealthService::class)->evaluate();
            $checks[] = [
                'id' => 'cache.shared_store_reachable',
                'status' => $sharedState['cache']['healthy'] ? 'pass' : 'critical',
                'message' => $sharedState['cache']['message'],
            ];
            $checks[] = [
                'id' => 'session.shared_store_reachable',
                'status' => $sharedState['session']['healthy'] ? 'pass' : 'critical',
                'message' => $sharedState['session']['message'],
            ];

            $queueDriver = strtolower(trim((string) config('queue.default', '')));
            if (in_array($queueDriver, ['', 'sync', 'null'], true)) {
                $checks[] = [
                    'id' => 'queue.workers',
                    'status' => 'critical',
                    'message' => 'Worker health cannot be verified until QUEUE_CONNECTION uses a durable asynchronous backend.',
                ];
            } else {
                $queue = app(QueueHealthService::class)->evaluate();
                $checks[] = [
                    'id' => 'queue.workers',
                    'status' => $queue['healthy'] ? 'pass' : 'critical',
                    'message' => $queue['healthy'] ? 'Ready.' : $queue['message'],
                ];
            }
        }
        $summary = $service->summary($checks);

        if ($this->option('json')) {
            $this->line(json_encode(['summary' => $summary, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Check', 'Status', 'Details'], array_map(fn ($check) => [
                $check['id'], strtoupper($check['status']), $check['message'],
            ], $checks));
            $this->line(sprintf(
                'Passed: %d | Warnings: %d | Critical: %d',
                $summary['passed'],
                $summary['warnings'],
                $summary['critical']
            ));
        }

        return $summary['critical'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Keep the readiness result shape stable and fail closed when live
     * transactional evidence cannot be inspected. A single connection error
     * must not make schema, encryption, finance, admission, or legacy-storage
     * controls disappear from deployment automation.
     *
     * @return array<int, array{id: string, status: string, message: string}>
     */
    private function databaseUnavailableChecks(): array
    {
        $message = 'Cannot verify this live transactional control while the database is unavailable.';

        return array_map(
            static fn (string $id): array => [
                'id' => $id,
                'status' => 'critical',
                'message' => $message,
            ],
            [
                'database.transactional_schema',
                'database.transactional_columns',
                'database.transactional_indexes',
                'database.platform_earning_singleton',
                'tickets.guest_buyer_identity_integrity',
                'tickets.booking_unit_integrity',
                'security.gateway_credentials_encrypted',
                'security.basic_setting_secrets_encrypted',
                'security.payment_intent_sensitive_fields_encrypted',
                'security.finance_diagnostics_encrypted',
                'security.operational_diagnostics_encrypted',
                'storage.product_order_legacy_documents',
                'storage.event_booking_legacy_documents',
            ]
        );
    }
}
