<?php

namespace Tests\Unit;

use App\Models\Earning;
use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use App\Services\ProductOrderAccountingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductOrderAccountingServiceTest extends TestCase
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

        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('total', 18, 2);
            $table->string('method')->nullable();
            $table->string('gateway_type')->nullable();
            $table->string('payment_status');
            $table->string('order_number')->unique();
            $table->string('tnxid')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable();
            $table->timestamps();
        });
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transcation_id')->nullable();
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('booking_id')->nullable();
            $table->integer('transcation_type')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('grand_total', 18, 2)->nullable();
            $table->decimal('commission', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0);
            $table->decimal('after_balance', 18, 2)->default(0);
            $table->string('gateway_type')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->longText('before')->nullable();
            $table->longText('after')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('request_id')->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });

        Earning::create(['total_revenue' => 100, 'total_earning' => 100]);
    }

    public function test_pending_manual_order_does_not_change_revenue_or_paid_ledger(): void
    {
        $order = $this->order('pending', 'manual-1');

        app(ProductOrderAccountingService::class)->recordCompleted($order);

        $this->assertSame(100.0, (float) Earning::first()->total_revenue);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_completed_order_is_accounted_exactly_once_with_stable_reference(): void
    {
        $order = $this->order('completed', 'paid-1');
        $service = app(ProductOrderAccountingService::class);

        $service->recordCompleted($order);
        $service->recordCompleted($order->fresh());

        $this->assertSame(150.0, (float) Earning::first()->total_revenue);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'transcation_id' => 'gateway-ref-1',
            'booking_id' => (string) $order->id,
            'transcation_type' => 2,
            'payment_status' => '1',
            'grand_total' => 50,
        ]);
    }

    public function test_exact_legacy_offline_ledger_identity_is_repaired_without_reposting_revenue(): void
    {
        $order = $this->order('completed', 'manual-paid-1', [
            'gateway_type' => 'offline',
            'method' => 'Bank transfer',
            'tnxid' => null,
        ]);
        $this->paidLedger($order, ['gateway' => null, 'gateway_reference' => null]);

        app(ProductOrderAccountingService::class)->recordCompleted($order);

        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'booking_id' => (string) $order->id,
            'gateway' => 'offline',
            'gateway_reference' => 'order:'.$order->id,
        ]);
        $this->assertSame(1, DB::table('audit_logs')
            ->where('action', 'product_payment.ledger_identity_repaired')->count());
    }

    public function test_duplicate_paid_ledger_rows_fail_closed_without_mutating_revenue(): void
    {
        $order = $this->order('completed', 'duplicate-paid-1', ['gateway_type' => 'offline']);
        $this->paidLedger($order);
        $this->paidLedger($order, ['transcation_id' => 'legacy-duplicate']);

        try {
            app(ProductOrderAccountingService::class)->recordCompleted($order);
            $this->fail('Duplicate paid product ledgers must fail closed.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('multiple paid ledger rows', $exception->getMessage());
        }

        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_partial_or_conflicting_offline_identity_fails_closed(): void
    {
        foreach ([
            ['gateway' => 'offline', 'gateway_reference' => null, 'message' => 'partial gateway identity'],
            ['gateway' => 'paystack', 'gateway_reference' => 'other-reference', 'message' => 'conflicting gateway identity'],
        ] as $index => $case) {
            $order = $this->order('completed', 'bad-identity-'.$index, [
                'gateway_type' => 'offline',
                'method' => 'Bank transfer',
            ]);
            $this->paidLedger($order, $case);

            try {
                app(ProductOrderAccountingService::class)->recordCompleted($order);
                $this->fail('Unsafe offline ledger identity must fail closed.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString($case['message'], $exception->getMessage());
            }
        }

        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
    }

    public function test_explicit_online_identity_is_accounted_once_and_cannot_cross_orders(): void
    {
        $service = app(ProductOrderAccountingService::class);
        $first = $this->order('completed', 'iyzico-1', [
            'method' => 'Iyzico',
            'tnxid' => null,
        ]);

        $service->recordCompleted($first, 'IYZICO', 'conversation-1');
        $service->recordCompleted($first->fresh(), 'iyzico', 'conversation-1');

        $this->assertSame(150.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'gateway' => 'iyzico',
            'gateway_reference' => 'conversation-1',
            'booking_id' => (string) $first->id,
        ]);

        $second = $this->order('completed', 'iyzico-2', [
            'method' => 'Iyzico',
            'tnxid' => null,
        ]);

        try {
            $service->recordCompleted($second, 'iyzico', 'conversation-1');
            $this->fail('A provider reference must not be reusable across product orders.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('different order', $exception->getMessage());
        }

        $this->assertSame(150.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_explicit_online_replay_with_changed_reference_fails_closed(): void
    {
        $service = app(ProductOrderAccountingService::class);
        $order = $this->order('completed', 'iyzico-reference-drift', [
            'method' => 'Iyzico',
            'tnxid' => null,
        ]);
        $service->recordCompleted($order, 'iyzico', 'conversation-original');

        try {
            $service->recordCompleted($order->fresh(), 'iyzico', 'conversation-changed');
            $this->fail('A completed order must retain its original gateway reference.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('conflicting gateway identity', $exception->getMessage());
        }

        $this->assertSame(150.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 1);
    }

    private function order(string $status, string $number, array $overrides = []): ProductOrder
    {
        return ProductOrder::create(array_merge([
            'user_id' => 41,
            'total' => 50,
            'method' => 'Test gateway',
            'gateway_type' => $status === 'completed' ? 'online' : 'offline',
            'payment_status' => $status,
            'order_number' => $number,
            'tnxid' => $status === 'completed' ? 'gateway-ref-1' : '',
            'currency_symbol' => '₦',
            'currency_symbol_position' => 'left',
        ], $overrides));
    }

    private function paidLedger(ProductOrder $order, array $overrides = []): Transaction
    {
        unset($overrides['message']);

        return Transaction::create(array_merge([
            'transcation_id' => 'legacy-'.$order->id,
            'booking_id' => (string) $order->id,
            'transcation_type' => 2,
            'customer_id' => $order->user_id,
            'payment_status' => 1,
            'payment_method' => $order->method,
            'grand_total' => $order->total,
            'commission' => $order->total,
            'gateway_type' => $order->gateway_type,
            'currency_symbol' => $order->currency_symbol,
            'currency_symbol_position' => $order->currency_symbol_position,
        ], $overrides));
    }
}
