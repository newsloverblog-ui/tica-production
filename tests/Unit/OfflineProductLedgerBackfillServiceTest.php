<?php

namespace Tests\Unit;

use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use App\Services\OfflineProductLedgerBackfillService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfflineProductLedgerBackfillServiceTest extends TestCase
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
        $order = $this->order('completed');
        $transaction = $this->transaction($order);

        $result = app(OfflineProductLedgerBackfillService::class)->run();

        $this->assertTrue($result['healthy']);
        $this->assertSame(1, $result['would_update']);
        $this->assertNull($transaction->fresh()->gateway);
        $this->assertNull($transaction->fresh()->gateway_reference);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_apply_is_audited_and_idempotent(): void
    {
        $order = $this->order('completed');
        $transaction = $this->transaction($order);
        $service = app(OfflineProductLedgerBackfillService::class);

        $first = $service->run(true);
        $second = $service->run(true);

        $this->assertSame(1, $first['updated']);
        $this->assertSame(1, $second['already_linked']);
        $this->assertSame('offline', $transaction->fresh()->gateway);
        $this->assertSame('order:'.$order->id, $transaction->fresh()->gateway_reference);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaction.offline_product_identity_backfilled',
            'subject_id' => $transaction->id,
        ]);
    }

    public function test_missing_duplicate_mismatched_and_partial_ledgers_are_never_guessed(): void
    {
        $missing = $this->order('completed');
        $duplicate = $this->order('completed');
        $this->transaction($duplicate);
        $this->transaction($duplicate, ['transcation_id' => 'duplicate']);
        $mismatch = $this->order('completed');
        $bad = $this->transaction($mismatch, ['grand_total' => 999]);
        $partial = $this->order('completed');
        $partialRow = $this->transaction($partial, ['gateway' => 'offline']);

        $result = app(OfflineProductLedgerBackfillService::class)->run(true);

        $this->assertFalse($result['healthy']);
        $this->assertSame(1, $result['missing']);
        $this->assertSame(3, $result['conflicts']);
        $this->assertSame(
            ['multiple_sale_ledger_rows', 'partial_or_conflicting_identity', 'sale_ledger_row_not_found', 'sale_values_do_not_reconcile'],
            collect($result['issues'])->pluck('reason')->sort()->values()->all()
        );
        $this->assertNull($bad->fresh()->gateway);
        $this->assertNull($partialRow->fresh()->gateway_reference);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNotNull($missing->id);
    }

    public function test_online_pending_and_rejected_orders_are_out_of_scope(): void
    {
        $pending = $this->order('pending');
        $rejected = $this->order('rejected');
        $online = $this->order('completed', ['gateway_type' => 'online']);
        $this->transaction($pending);
        $this->transaction($rejected);
        $this->transaction($online);

        $result = app(OfflineProductLedgerBackfillService::class)->run(true);

        $this->assertTrue($result['healthy']);
        $this->assertSame(0, $result['scanned']);
        $this->assertDatabaseMissing('transactions', ['gateway_reference' => 'order:'.$online->id]);
    }

    public function test_command_is_dry_run_by_default_and_fails_for_unresolved_sales(): void
    {
        $order = $this->order('completed');
        $transaction = $this->transaction($order, ['customer_id' => 999]);

        $exit = Artisan::call('ledger:backfill-offline-product-identities', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('"mode": "dry-run"', $output);
        $this->assertStringContainsString('sale_values_do_not_reconcile', $output);
        $this->assertNull($transaction->fresh()->gateway);
    }

    private function order(string $status, array $overrides = []): ProductOrder
    {
        return ProductOrder::create(array_merge([
            'user_id' => 41,
            'total' => 500,
            'method' => 'Bank transfer',
            'gateway_type' => 'offline',
            'payment_status' => $status,
            'order_number' => uniqid('ORDER-', true),
            'currency_symbol' => '₦',
            'currency_symbol_position' => 'left',
        ], $overrides));
    }

    private function transaction(ProductOrder $order, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'transcation_id' => uniqid('legacy-', true),
            'booking_id' => $order->id,
            'transcation_type' => 2,
            'customer_id' => $order->user_id,
            'organizer_id' => null,
            'payment_status' => 1,
            'payment_method' => $order->method,
            'grand_total' => $order->total,
            'tax' => 0,
            'commission' => null,
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('total', 18, 2); $table->string('method')->nullable();
            $table->string('gateway_type')->nullable(); $table->string('payment_status');
            $table->string('order_number')->unique(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->nullable(); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->nullable(); $table->decimal('after_balance', 18, 2)->nullable();
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
