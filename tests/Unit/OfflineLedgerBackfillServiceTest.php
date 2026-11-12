<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use App\Models\Transaction;
use App\Services\OfflineLedgerBackfillService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfflineLedgerBackfillServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_dry_run_reports_exact_mapping_without_writing(): void
    {
        $booking = $this->booking('completed');
        $transaction = $this->transaction($booking);

        $result = app(OfflineLedgerBackfillService::class)->run();

        $this->assertTrue($result['healthy']);
        $this->assertSame(1, $result['would_update']);
        $this->assertNull($transaction->fresh()->gateway);
        $this->assertNull($transaction->fresh()->gateway_reference);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_apply_is_audited_and_idempotent(): void
    {
        $booking = $this->booking('completed');
        $transaction = $this->transaction($booking);
        $service = app(OfflineLedgerBackfillService::class);

        $first = $service->run(true);
        $second = $service->run(true);

        $this->assertSame(1, $first['updated']);
        $this->assertSame(1, $second['already_linked']);
        $this->assertSame('offline', $transaction->fresh()->gateway);
        $this->assertSame('booking:'.$booking->id, $transaction->fresh()->gateway_reference);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaction.offline_identity_backfilled',
            'subject_id' => $transaction->id,
        ]);
    }

    public function test_missing_duplicate_and_mismatched_ledgers_are_never_guessed(): void
    {
        $missing = $this->booking('completed');
        $duplicate = $this->booking('completed');
        $this->transaction($duplicate);
        $this->transaction($duplicate, ['transcation_id' => 'duplicate']);
        $mismatch = $this->booking('completed');
        $bad = $this->transaction($mismatch, ['grand_total' => 999]);

        $result = app(OfflineLedgerBackfillService::class)->run(true);

        $this->assertFalse($result['healthy']);
        $this->assertSame(1, $result['missing']);
        $this->assertSame(2, $result['conflicts']);
        $this->assertSame(
            ['multiple_sale_ledger_rows', 'sale_ledger_row_not_found', 'sale_values_do_not_reconcile'],
            collect($result['issues'])->pluck('reason')->sort()->values()->all()
        );
        $this->assertNull($bad->fresh()->gateway);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNotNull($missing->id);
    }

    public function test_non_offline_and_non_completed_bookings_are_out_of_scope(): void
    {
        $pending = $this->booking('pending');
        $online = $this->booking('completed', ['gatewayType' => 'online']);
        $this->transaction($pending);
        $this->transaction($online);

        $result = app(OfflineLedgerBackfillService::class)->run(true);

        $this->assertTrue($result['healthy']);
        $this->assertSame(0, $result['scanned']);
        $this->assertDatabaseMissing('transactions', ['gateway' => 'offline']);
    }

    public function test_command_is_dry_run_by_default_and_returns_failure_for_unresolved_sales(): void
    {
        $booking = $this->booking('completed');
        $transaction = $this->transaction($booking, ['grand_total' => 999]);

        $exit = Artisan::call('ledger:backfill-offline-identities', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('"mode": "dry-run"', $output);
        $this->assertStringContainsString('sale_values_do_not_reconcile', $output);

        $this->assertNull($transaction->fresh()->gateway);
    }

    private function booking(string $status, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_id' => uniqid('OFFLINE-', true),
            'event_id' => 7,
            'customer_id' => 15,
            'organizer_id' => 21,
            'price' => 1000,
            'tax' => 75,
            'commission' => 100,
            'paymentMethod' => 'Bank transfer',
            'gatewayType' => 'offline',
            'paymentStatus' => $status,
        ], $overrides));
    }

    private function transaction(Booking $booking, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'transcation_id' => uniqid('legacy-', true),
            'booking_id' => $booking->id,
            'transcation_type' => 1,
            'customer_id' => $booking->customer_id,
            'organizer_id' => $booking->organizer_id,
            'payment_status' => 1,
            'payment_method' => $booking->paymentMethod,
            'grand_total' => $booking->price,
            'tax' => $booking->tax,
            'commission' => $booking->commission,
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->string('booking_id')->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->decimal('price', 18, 2); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->string('paymentMethod')->nullable();
            $table->string('gatewayType')->nullable(); $table->string('paymentStatus'); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->nullable();
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type', 32)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action', 120);
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable();
            $table->json('metadata')->nullable(); $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
