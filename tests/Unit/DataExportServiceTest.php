<?php

namespace Tests\Unit;

use App\Jobs\GenerateBookingExport;
use App\Models\DataExport;
use App\Services\BookingExportQueryService;
use App\Services\DataExportService;
use App\Services\DataExportScheduleService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataExportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('e', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $this->seedReferenceData();
    }

    public function test_organizer_export_is_private_scoped_encrypted_and_queued(): void
    {
        Queue::fake();
        $this->insertBooking(1, 10, 'ORG-ONE', 'First attendee');
        $this->insertBooking(2, 20, 'ORG-TWO', 'Other organizer attendee');

        $export = app(DataExportService::class)->queueBookings(
            'organizer', 10, 10, 1, ['status' => 'completed'], 'csv'
        );

        $this->assertSame(1, $export->row_count);
        $this->assertSame(10, $export->organizer_id);
        $this->assertSame(1, $export->filters['max_booking_id']);
        $rawFilters = (string) DB::table('data_exports')->where('id', $export->id)->value('filters');
        $this->assertStringNotContainsString('completed', $rawFilters);
        Queue::assertPushed(GenerateBookingExport::class, fn ($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export.queued', 'subject_id' => $export->id]);
    }

    public function test_organizer_cannot_queue_another_organizers_export(): void
    {
        $this->expectException(DomainException::class);
        app(DataExportService::class)->queueBookings(
            'organizer', 10, 20, 1, [], 'csv'
        );
    }

    public function test_snapshot_excludes_bookings_created_after_request_and_job_is_idempotent(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->insertBooking(1, 10, 'SNAPSHOT-ONE', 'Included attendee');
        $export = app(DataExportService::class)->queueBookings(
            'organizer', 10, 10, 1, [], 'csv'
        );
        $this->insertBooking(2, 10, 'SNAPSHOT-TWO', 'Late attendee');

        $job = new GenerateBookingExport($export->id);
        $job->handle(app(BookingExportQueryService::class));
        $ready = $export->fresh();

        $this->assertSame('ready', $ready->status);
        $this->assertSame(1, $ready->attempts);
        Storage::disk('local')->assertExists($ready->path);
        $csv = Storage::disk('local')->get($ready->path);
        $this->assertStringContainsString('SNAPSHOT-ONE', $csv);
        $this->assertStringNotContainsString('SNAPSHOT-TWO', $csv);

        $job->handle(app(BookingExportQueryService::class));
        $this->assertSame(1, $ready->fresh()->attempts);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'data_export.ready')->count());
    }

    public function test_booking_export_uses_point_in_time_refund_and_settlement_evidence_and_neutralizes_formulas(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->insertBooking(1, 10, 'FINANCE-ONE', '=Unsafe attendee');
        $this->insertFinancialEvidence(1);

        $export = app(DataExportService::class)->queueBookings(
            'organizer', 10, 10, 1, [], 'csv'
        );

        DB::table('payment_refunds')->insert([
            'public_id' => '00000000-0000-4000-8000-000000000099', 'payment_intent_id' => 1,
            'booking_id' => 1, 'requested_by_admin_id' => 1, 'gateway' => 'paystack',
            'gateway_reference' => 'PAY-REF-1', 'amount' => 500, 'currency' => 'NGN',
            'reason' => 'After snapshot', 'status' => 'processed', 'requested_at' => now(),
            'financial_applied_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new GenerateBookingExport($export->id))->handle(app(BookingExportQueryService::class));
        $csv = Storage::disk('local')->get($export->fresh()->path);

        $this->assertStringContainsString('"Ledger Gateway","Gateway Reference","Charged Amount","Refunded Amount","Refund Status"', $csv);
        $this->assertStringContainsString('PAY-REF-1', $csv);
        $this->assertStringContainsString('SETTLEMENT-1', $csv);
        $this->assertStringContainsString('Partially refunded', $csv);
        $this->assertStringContainsString('₦1000.00', $csv);
        $this->assertStringNotContainsString('₦1500.00', $csv);
        $this->assertStringContainsString("'=Unsafe attendee", $csv);
    }

    public function test_empty_result_is_rejected_without_a_job_or_history_record(): void
    {
        Queue::fake();
        try {
            app(DataExportService::class)->queueBookings(
                'admin', 1, null, 1, ['event_title' => 'No such event'], 'xlsx'
            );
            $this->fail('An empty export should have been rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('no bookings', strtolower($exception->getMessage()));
        }

        $this->assertDatabaseCount('data_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_queue_handoff_failure_is_durable_and_recovery_reuses_the_existing_export(): void
    {
        $export = DataExport::create([
            'requested_by_type' => 'admin',
            'requested_by_id' => 1,
            'export_type' => 'bookings',
            'format' => 'csv',
            'status' => 'queued',
            'filters' => [],
            'disk' => 'local',
            'path' => 'exports/bookings/recoverable.csv',
            'filename' => 'recoverable.csv',
            'row_count' => 1,
            'expires_at' => now()->addDay(),
        ]);

        $failing = new class extends DataExportService {
            protected function pushGenerationJob(string $job, int $exportId): void
            {
                throw new \RuntimeException('queue unavailable for recovery test');
            }
        };

        $this->assertFalse($failing->dispatchQueued($export->id));
        $failed = $export->fresh();
        $this->assertSame('queued', $failed->status);
        $this->assertSame(1, $failed->dispatch_attempts);
        $this->assertStringContainsString('queue unavailable', $failed->dispatch_last_error);
        $rawError = (string) DB::table('data_exports')->where('id', $export->id)->value('dispatch_last_error');
        $this->assertStringNotContainsString('queue unavailable', $rawError);

        Queue::fake();
        $this->artisan('exports:dispatch-pending')->assertSuccessful();

        Queue::assertPushed(GenerateBookingExport::class,
            fn (GenerateBookingExport $job): bool => $job->exportId === $export->id);
        $recovered = $export->fresh();
        $this->assertSame(2, $recovered->dispatch_attempts);
        $this->assertNull($recovered->dispatch_last_error);
        $this->assertDatabaseCount('data_exports', 1);
    }

    public function test_report_date_gateway_and_status_filters_are_preserved(): void
    {
        Queue::fake();
        $this->insertBooking(1, 10, 'FILTER-MATCH', 'Matching attendee');
        DB::table('bookings')->where('id', 1)->update(['created_at' => '2026-11-01 10:00:00']);
        $this->insertBooking(2, 10, 'FILTER-OLD', 'Old attendee');
        DB::table('bookings')->where('id', 2)->update(['created_at' => '2026-10-01 10:00:00']);

        $export = app(DataExportService::class)->queueBookings('organizer', 10, 10, 1, [
            'from_date' => '2026-11-01', 'to_date' => '2026-11-30',
            'payment_method' => 'paystack', 'payment_status' => 'completed',
        ], 'csv');

        $this->assertSame(1, $export->row_count);
        $this->assertSame('2026-11-01', $export->filters['from_date']);
        $this->assertSame('paystack', $export->filters['payment_method']);
        $this->assertSame('completed', $export->filters['payment_status']);
    }

    public function test_recurring_schedule_is_actor_scoped_encrypted_and_runs_once_when_due(): void
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC')); // 09:00 in Lagos, the schedule's start
        $this->insertBooking(1, 10, 'SCHEDULED-ONE', 'Scheduled attendee');

        $service = app(DataExportScheduleService::class);
        $schedule = $service->createBookingSchedule(
            'organizer', 10, 10, 1, ['payment_status' => 'completed'], 'csv',
            'daily', 'Africa/Lagos', '2026-11-02 09:00:00', 7
        );
        $rawFilters = (string) DB::table('data_export_schedules')->where('id', $schedule->id)->value('filters');
        $this->assertStringNotContainsString('completed', $rawFilters);

        $result = $service->runDue();
        $this->assertSame(['queued' => 1, 'failed' => 0], $result);
        $export = DataExport::query()->where('schedule_id', $schedule->id)->sole();
        $this->assertSame(10, $export->organizer_id);
        $this->assertSame('2026-10-27', $export->filters['from_date']);
        $this->assertSame('2026-11-02', $export->filters['to_date']);
        $this->assertSame('2026-11-03 09:00:00', $schedule->fresh()->next_run_at->copy()->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s'));
        Queue::assertPushed(GenerateBookingExport::class, 1);

        $this->assertSame(['queued' => 0, 'failed' => 0], $service->runDue());
        Queue::assertPushed(GenerateBookingExport::class, 1);
        $this->travelBack();
    }

    public function test_recurring_schedule_rejects_cross_organizer_ownership(): void
    {
        $this->expectException(DomainException::class);
        app(DataExportScheduleService::class)->createBookingSchedule(
            'organizer', 10, 20, 1, [], 'csv', 'daily', 'Africa/Lagos',
            now()->addHour()->toDateTimeString(), 7
        );
    }

    public function test_admin_role_field_policy_omits_unapproved_contact_and_finance_columns(): void
    {
        Storage::fake('local');
        Queue::fake();
        DB::table('role_permissions')->insert([
            'id' => 2, 'name' => 'Operations', 'permissions' => json_encode(['Event Bookings']),
            'export_fields' => json_encode(['booking_id', 'event', 'payment_status']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('admins')->insert([
            'id' => 2, 'role_id' => 2, 'username' => 'operations-admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->insertBooking(1, 10, 'POLICY-ONE', 'Private attendee');

        $export = app(DataExportService::class)->queueBookings('admin', 2, null, 1, [], 'csv');
        (new GenerateBookingExport($export->id))->handle(app(BookingExportQueryService::class));
        $csv = Storage::disk('local')->get($export->fresh()->path);

        $this->assertSame(['booking_id', 'event', 'payment_status'], $export->filters['field_keys']);
        $this->assertStringContainsString('"Booking Id","Event","Payment Status"', $csv);
        $this->assertStringNotContainsString('Email', $csv);
        $this->assertStringNotContainsString('Gateway Reference', $csv);
        $this->assertStringNotContainsString('Private attendee', $csv);
    }

    public function test_prune_removes_private_file_but_retains_expired_history(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/bookings/expired.csv', 'private data');
        $export = DataExport::create([
            'requested_by_type' => 'admin', 'requested_by_id' => 1,
            'export_type' => 'bookings', 'format' => 'csv', 'status' => 'ready',
            'filters' => [], 'disk' => 'local', 'path' => 'exports/bookings/expired.csv',
            'filename' => 'expired.csv', 'row_count' => 1, 'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('exports:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing('exports/bookings/expired.csv');
        $this->assertSame('expired', $export->fresh()->status);
        $this->assertDatabaseHas('data_exports', ['id' => $export->id, 'status' => 'expired']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export.expired', 'subject_id' => $export->id]);
    }

    private function insertBooking(int $id, int $organizerId, string $bookingId, string $name): void
    {
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');
        DB::table('bookings')->insert([
            'id' => $id, 'booking_id' => $bookingId, 'event_id' => 100,
            'organizer_id' => $organizerId, 'customer_id' => null,
            'fname' => $first, 'lname' => $last, 'email' => strtolower($bookingId) . '@example.test',
            'phone' => '08000000000', 'country' => 'Nigeria', 'state' => 'Lagos',
            'city' => 'Lagos', 'zip_code' => '100001', 'discount' => 0,
            'early_bird_discount' => 0, 'quantity' => 1, 'price' => 5000,
            'tax' => 0, 'refunded_amount' => 0, 'refund_status' => null,
            'paymentMethod' => 'paystack', 'paymentStatus' => 'completed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertFinancialEvidence(int $bookingId): void
    {
        DB::table('transactions')->insert([
            'transcation_id' => 'PAY-REF-1', 'gateway' => 'paystack',
            'gateway_reference' => 'PAY-REF-1', 'booking_id' => $bookingId,
            'transcation_type' => 1, 'payment_status' => 'completed',
            'grand_total' => 5000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payment_refunds')->insert([
            'public_id' => '00000000-0000-4000-8000-000000000001', 'payment_intent_id' => 1,
            'booking_id' => $bookingId, 'requested_by_admin_id' => 1, 'gateway' => 'paystack',
            'gateway_reference' => 'PAY-REF-1', 'amount' => 1000, 'currency' => 'NGN',
            'reason' => 'Customer adjustment', 'status' => 'processed', 'requested_at' => now()->subMinutes(2),
            'financial_applied_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('gateway_settlements')->insert([
            'id' => 1, 'gateway' => 'paystack', 'gateway_settlement_id' => 'SETTLEMENT-1',
            'status' => 'success', 'currency' => 'NGN', 'total_processed' => 5000,
            'total_fees' => 100, 'total_amount' => 5000, 'effective_amount' => 4900,
            'transaction_count' => 1, 'matched_count' => 1, 'mismatch_count' => 0,
            'reconciliation_status' => 'matched', 'settlement_date' => now()->subDay(),
            'fetched_at' => now()->subMinute(), 'reconciled_at' => now()->subMinute(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('gateway_settlement_entries')->insert([
            'gateway_settlement_id' => 1, 'booking_id' => $bookingId, 'reference' => 'PAY-REF-1',
            'result' => 'matched', 'gateway_amount' => 5000, 'gateway_fee' => 100,
            'gateway_currency' => 'NGN', 'checked_at' => now()->subMinute(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedReferenceData(): void
    {
        DB::table('admins')->insert([
            'id' => 1, 'role_id' => null, 'username' => 'primary-admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_contents')->insert([
            'event_id' => 100, 'language_id' => 1, 'title' => 'Verified export event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('basic_settings')->insert([
            'base_currency_symbol' => '₦', 'base_currency_symbol_position' => 'left',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->longText('permissions')->nullable();
            $table->longText('export_fields')->nullable(); $table->timestamps();
        });
        Schema::create('admins', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('role_id')->nullable();
            $table->string('username'); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->string('booking_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('email')->nullable();
            $table->string('phone')->nullable(); $table->string('country')->nullable(); $table->string('state')->nullable();
            $table->string('city')->nullable(); $table->string('zip_code')->nullable();
            $table->decimal('discount', 12, 2)->default(0); $table->decimal('early_bird_discount', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1); $table->decimal('price', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0); $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('refund_status')->nullable();
            $table->string('paymentMethod')->nullable(); $table->string('paymentStatus')->nullable(); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->string('transcation_id'); $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedInteger('transcation_type'); $table->string('payment_status')->nullable();
            $table->decimal('grand_total', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('payment_intent_id');
            $table->unsignedBigInteger('booking_id')->nullable(); $table->unsignedBigInteger('requested_by_admin_id');
            $table->string('gateway'); $table->string('gateway_reference'); $table->decimal('amount', 18, 2);
            $table->string('currency'); $table->text('reason'); $table->string('status');
            $table->timestamp('requested_at'); $table->timestamp('financial_applied_at')->nullable(); $table->timestamps();
        });
        Schema::create('gateway_settlements', function (Blueprint $table) {
            $table->id(); $table->string('gateway'); $table->string('gateway_settlement_id');
            $table->string('status'); $table->string('currency')->nullable();
            $table->decimal('total_processed', 18, 2); $table->decimal('total_fees', 18, 2);
            $table->decimal('total_amount', 18, 2); $table->decimal('effective_amount', 18, 2);
            $table->unsignedInteger('transaction_count'); $table->unsignedInteger('matched_count');
            $table->unsignedInteger('mismatch_count'); $table->string('reconciliation_status');
            $table->timestamp('settlement_date')->nullable(); $table->timestamp('fetched_at');
            $table->timestamp('reconciled_at')->nullable(); $table->timestamps();
        });
        Schema::create('gateway_settlement_entries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('gateway_settlement_id');
            $table->unsignedBigInteger('booking_id')->nullable(); $table->string('reference');
            $table->string('result'); $table->decimal('gateway_amount', 18, 2);
            $table->decimal('gateway_fee', 18, 2); $table->string('gateway_currency')->nullable();
            $table->timestamp('checked_at'); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('language_id');
            $table->string('title'); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); $table->string('fname')->nullable(); $table->string('lname')->nullable();
        });
        Schema::create('basic_settings', function (Blueprint $table) {
            $table->id(); $table->string('base_currency_symbol')->nullable();
            $table->string('base_currency_symbol_position')->nullable(); $table->timestamps();
        });
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type', 20);
            $table->unsignedBigInteger('requested_by_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('export_type', 40); $table->string('format', 8); $table->string('status', 20);
            $table->longText('filters')->nullable(); $table->string('disk', 40); $table->string('path', 500)->nullable();
            $table->string('filename', 191); $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable();
            $table->timestamp('dispatch_alerted_at')->nullable();
            $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('downloaded_at')->nullable(); $table->timestamps();
        });
        Schema::create('data_export_schedules', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type', 20);
            $table->unsignedBigInteger('requested_by_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('export_type', 40)->default('bookings'); $table->string('format', 8);
            $table->longText('filters')->nullable(); $table->unsignedBigInteger('language_id');
            $table->string('frequency', 16); $table->string('timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('date_window_days')->default(7); $table->boolean('active')->default(true);
            $table->timestamp('next_run_at'); $table->timestamp('last_run_at')->nullable();
            $table->unsignedBigInteger('last_export_id')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0); $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
