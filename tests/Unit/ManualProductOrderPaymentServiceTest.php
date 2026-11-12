<?php

namespace Tests\Unit;

use App\Models\Earning;
use App\Models\ShopManagement\OrderItem;
use App\Models\ShopManagement\Product;
use App\Models\ShopManagement\ProductOrder;
use App\Services\ManualProductOrderPaymentService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManualProductOrderPaymentServiceTest extends TestCase
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
        Earning::create(['total_revenue' => 100, 'total_earning' => 100]);
    }

    public function test_offline_approval_accounts_and_dispatch_state_exactly_once(): void
    {
        $order = $this->order(['inventory_reserved_at' => now()]);
        $service = app(ManualProductOrderPaymentService::class);

        $first = $service->transition($order->id, 'completed');
        $second = $service->transition($order->id, 'completed');

        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertSame(600.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertSame(600.0, (float) Earning::firstOrFail()->total_earning);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'booking_id' => (string) $order->id,
            'transcation_type' => 2,
            'payment_status' => '1',
            'gateway' => 'offline',
            'gateway_reference' => 'order:'.$order->id,
            'grand_total' => 500,
        ]);
        $this->assertDatabaseHas('product_order_deliveries', [
            'product_order_id' => $order->id,
            'kind' => 'product_order_confirmation',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('product_order_deliveries', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'product_payment.status_changed')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'product_payment.accounted')->count());
    }

    public function test_approval_rolls_back_accounting_when_fulfillment_record_cannot_commit(): void
    {
        $order = $this->order(['inventory_reserved_at' => now()]);
        Schema::drop('product_order_deliveries');

        try {
            app(ManualProductOrderPaymentService::class)->transition($order->id, 'completed');
            $this->fail('Approval must fail when its recoverable fulfillment record cannot commit.');
        } catch (\Illuminate\Database\QueryException) {
            // The missing delivery table simulates a failed fulfillment-ledger write.
        }

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_earning);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejection_restores_reserved_inventory_exactly_once(): void
    {
        $product = Product::create([
            'stock' => 3, 'sku' => 'PHYSICAL-1', 'current_price' => 250,
            'status' => 1, 'type' => 'physical',
        ]);
        $order = $this->order(['inventory_reserved_at' => now()]);
        OrderItem::create([
            'product_order_id' => $order->id, 'product_id' => $product->id,
            'title' => 'Physical', 'qty' => 2, 'price' => 250,
        ]);
        $service = app(ManualProductOrderPaymentService::class);

        $first = $service->transition($order->id, 'rejected');
        $second = $service->transition($order->id, 'rejected');

        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->inventory_released_at);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('product_order_deliveries', [
            'product_order_id' => $order->id,
            'kind' => 'product_order_rejection',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('product_order_deliveries', 1);
    }

    public function test_rejection_rolls_back_inventory_when_notification_record_cannot_commit(): void
    {
        $product = Product::create([
            'stock' => 3, 'sku' => 'PHYSICAL-ROLLBACK', 'current_price' => 250,
            'status' => 1, 'type' => 'physical',
        ]);
        $order = $this->order(['inventory_reserved_at' => now()]);
        OrderItem::create([
            'product_order_id' => $order->id, 'product_id' => $product->id,
            'title' => 'Physical', 'qty' => 2, 'price' => 250,
        ]);
        Schema::drop('product_order_deliveries');

        try {
            app(ManualProductOrderPaymentService::class)->transition($order->id, 'rejected');
            $this->fail('Rejection must fail when its durable notification record cannot commit.');
        } catch (\Illuminate\Database\QueryException) {
            // The missing table simulates a failed decision-notification ledger write.
        }

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->inventory_released_at);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_online_order_cannot_enter_manual_payment_review(): void
    {
        $order = $this->order(['gateway_type' => 'online', 'inventory_reserved_at' => now()]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only offline product orders');
        app(ManualProductOrderPaymentService::class)->transition($order->id, 'completed');
    }

    public function test_order_without_inventory_reservation_cannot_be_approved(): void
    {
        $order = $this->order();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no verified inventory reservation');
        app(ManualProductOrderPaymentService::class)->transition($order->id, 'completed');
    }

    public function test_terminal_order_cannot_be_reversed(): void
    {
        $order = $this->order([
            'payment_status' => 'completed',
            'inventory_reserved_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only pending offline product orders');
        app(ManualProductOrderPaymentService::class)->transition($order->id, 'rejected');
    }

    public function test_existing_financial_evidence_blocks_rejection_without_releasing_stock(): void
    {
        $product = Product::create([
            'stock' => 3, 'sku' => 'PHYSICAL-2', 'current_price' => 250,
            'status' => 1, 'type' => 'physical',
        ]);
        $order = $this->order(['inventory_reserved_at' => now()]);
        OrderItem::create([
            'product_order_id' => $order->id, 'product_id' => $product->id,
            'title' => 'Physical', 'qty' => 2, 'price' => 250,
        ]);
        DB::table('transactions')->insert([
            'transcation_id' => 'legacy-paid-order',
            'gateway' => 'offline',
            'gateway_reference' => 'legacy-paid-order',
            'booking_id' => (string) $order->id,
            'transcation_type' => 2,
            'customer_id' => $order->user_id,
            'payment_status' => '1',
            'payment_method' => 'Bank transfer',
            'grand_total' => 500,
            'commission' => 500,
            'tax' => 0,
            'pre_balance' => 0,
            'gateway_type' => 'offline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(ManualProductOrderPaymentService::class)->transition($order->id, 'rejected');
            $this->fail('Existing financial evidence must stop manual rejection.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('requires finance review', $exception->getMessage());
        }

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->inventory_released_at);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame('1', (string) DB::table('transactions')->value('payment_status'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_repeated_completed_review_never_synthesizes_missing_financial_history(): void
    {
        $order = $this->order([
            'payment_status' => 'completed',
            'inventory_reserved_at' => now(),
        ]);

        try {
            app(ManualProductOrderPaymentService::class)->transition($order->id, 'completed');
            $this->fail('Missing paid ledger evidence must not be synthesized during terminal review.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('no single paid ledger', $exception->getMessage());
        }

        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_repeated_rejected_review_does_not_bypass_financial_evidence_guard(): void
    {
        $product = Product::create([
            'stock' => 3, 'sku' => 'PHYSICAL-3', 'current_price' => 250,
            'status' => 1, 'type' => 'physical',
        ]);
        $order = $this->order([
            'payment_status' => 'rejected',
            'inventory_reserved_at' => now(),
        ]);
        OrderItem::create([
            'product_order_id' => $order->id, 'product_id' => $product->id,
            'title' => 'Physical', 'qty' => 2, 'price' => 250,
        ]);
        DB::table('transactions')->insert([
            'transcation_id' => 'late-ledger',
            'gateway' => 'offline',
            'gateway_reference' => 'late-ledger',
            'booking_id' => (string) $order->id,
            'transcation_type' => 2,
            'customer_id' => $order->user_id,
            'payment_status' => '1',
            'payment_method' => 'Bank transfer',
            'grand_total' => 500,
            'commission' => 500,
            'tax' => 0,
            'pre_balance' => 0,
            'gateway_type' => 'offline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(ManualProductOrderPaymentService::class)->transition($order->id, 'rejected');
            $this->fail('Terminal rejection must retain the financial evidence guard.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('requires finance review', $exception->getMessage());
        }

        $this->assertNull($order->fresh()->inventory_released_at);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('transactions', 1);
    }

    private function order(array $overrides = []): ProductOrder
    {
        return ProductOrder::create(array_merge([
            'user_id' => 9,
            'cart_total' => 500,
            'total' => 500,
            'method' => 'Bank transfer',
            'gateway_type' => 'offline',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'order_number' => uniqid('ORDER-', true),
            'currency_symbol' => '₦',
            'currency_symbol_position' => 'left',
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('cart_total', 18, 2); $table->decimal('total', 18, 2);
            $table->string('method')->nullable(); $table->string('gateway_type')->nullable();
            $table->string('payment_status'); $table->string('order_status');
            $table->string('order_number')->unique(); $table->string('tnxid')->nullable();
            $table->string('currency_symbol')->nullable(); $table->string('currency_symbol_position')->nullable();
            $table->timestamp('inventory_reserved_at')->nullable(); $table->timestamp('inventory_released_at')->nullable();
            $table->timestamp('archived_at')->nullable(); $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id(); $table->string('stock')->nullable(); $table->string('sku')->nullable();
            $table->string('feature_image')->nullable(); $table->decimal('current_price', 18, 2);
            $table->decimal('previous_price', 18, 2)->nullable(); $table->integer('status');
            $table->string('type'); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id');
            $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title'); $table->string('sku')->nullable(); $table->string('qty');
            $table->string('category')->nullable(); $table->string('image')->nullable();
            $table->text('summery')->nullable(); $table->text('description')->nullable();
            $table->decimal('price', 18, 2); $table->decimal('previous_price', 18, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
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
        Schema::create('product_order_deliveries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->string('kind', 64);
            $table->string('status', 20)->default('pending'); $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable(); $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('alerted_at')->nullable();
            $table->timestamps(); $table->unique(['product_order_id', 'kind']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type')->nullable(); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable(); $table->longText('before')->nullable();
            $table->longText('after')->nullable(); $table->longText('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->string('user_agent', 1000)->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
