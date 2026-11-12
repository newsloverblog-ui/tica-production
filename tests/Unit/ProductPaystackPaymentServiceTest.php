<?php

namespace Tests\Unit;

use App\Jobs\FinalizePaidProductOrder;
use App\Models\ProductPaymentIntent;
use App\Models\ShopManagement\ProductOrder;
use App\Services\ProductPaystackPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductPaystackPaymentServiceTest extends TestCase
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
        DB::table('earnings')->insert([
            'total_revenue' => 0,
            'total_earning' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Queue::fake();
    }

    public function test_successful_webhook_completes_accounts_and_fulfils_order_exactly_once(): void
    {
        [$order, $intent] = $this->pendingOrderAndIntent();
        $payment = $this->successPayload($intent);
        $service = app(ProductPaystackPaymentService::class);

        $first = $service->acceptWebhook($payment);
        $second = $service->acceptWebhook($payment);

        $this->assertSame('completed', $first['status']);
        $this->assertSame('completed', $second['status']);
        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertSame('completed', $intent->fresh()->status);
        $this->assertSame('98765', $intent->fresh()->gateway_transaction_id);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'booking_id' => $order->id, 'transcation_type' => 2,
            'payment_status' => 1, 'grand_total' => 100,
        ]);
        $this->assertSame(100.0, (float) DB::table('earnings')->value('total_revenue'));
        $this->assertDatabaseCount('product_order_deliveries', 1);
        Queue::assertPushed(FinalizePaidProductOrder::class);
    }

    public function test_terminal_failure_releases_reserved_physical_inventory_once(): void
    {
        [$order, $intent] = $this->pendingOrderAndIntent();
        DB::table('products')->insert([
            'id' => 10, 'stock' => '3', 'current_price' => 50, 'status' => 1,
            'type' => 'physical', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'product_order_id' => $order->id, 'product_id' => 10, 'qty' => '2',
            'price' => 50, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(ProductPaystackPaymentService::class);

        $service->acceptWebhook(['status' => 'abandoned', 'reference' => $intent->reference]);
        $service->acceptWebhook(['status' => 'abandoned', 'reference' => $intent->reference]);

        $this->assertSame('failed', $intent->fresh()->status);
        $this->assertNotNull($order->fresh()->inventory_released_at);
        $this->assertSame(5, (int) DB::table('products')->where('id', 10)->value('stock'));
        $this->assertDatabaseCount('transactions', 0);
    }

    private function pendingOrderAndIntent(): array
    {
        $order = ProductOrder::create([
            'user_id' => 1, 'cart_total' => 100, 'total' => 100,
            'method' => 'Paystack', 'gateway_type' => 'online',
            'payment_status' => 'pending', 'order_status' => 'pending',
            'order_number' => 'ORDER-1', 'tnxid' => 'tica-shop-1',
            'currency_symbol' => '₦', 'currency_symbol_position' => 'left',
            'inventory_reserved_at' => now(),
        ]);
        $intent = ProductPaymentIntent::create([
            'gateway' => 'paystack', 'reference' => 'tica-shop-1',
            'product_order_id' => $order->id, 'customer_id' => 1,
            'status' => 'initialized', 'amount' => 100, 'currency' => 'NGN',
            'email' => 'buyer@example.com', 'expires_at' => now()->addDay(),
        ]);
        return [$order, $intent];
    }

    private function successPayload(ProductPaymentIntent $intent): array
    {
        return [
            'id' => 98765, 'status' => 'success', 'reference' => $intent->reference,
            'amount' => 10000, 'currency' => 'NGN',
            'customer' => ['email' => 'buyer@example.com'],
        ];
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
            $table->timestamps();
        });
        Schema::create('product_payment_intents', function (Blueprint $table): void {
            $table->id(); $table->string('gateway'); $table->string('reference');
            $table->unsignedBigInteger('product_order_id'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status'); $table->decimal('amount', 18, 2); $table->string('currency');
            $table->string('email'); $table->text('authorization_url')->nullable();
            $table->string('gateway_transaction_id')->nullable(); $table->unsignedInteger('verification_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable(); $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id')->nullable();
            $table->string('gateway')->nullable(); $table->string('gateway_reference')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->integer('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->string('gateway_type')->nullable();
            $table->string('currency_symbol')->nullable(); $table->string('currency_symbol_position')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('product_order_deliveries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->string('kind');
            $table->string('status'); $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable(); $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('alerted_at')->nullable(); $table->timestamps();
            $table->unique(['product_order_id', 'kind']);
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id(); $table->string('stock')->nullable(); $table->string('sku')->nullable();
            $table->string('feature_image')->nullable(); $table->decimal('current_price', 18, 2);
            $table->decimal('previous_price', 18, 2)->nullable(); $table->integer('status');
            $table->string('type'); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable(); $table->string('title')->nullable(); $table->string('sku')->nullable();
            $table->string('qty'); $table->string('category')->nullable(); $table->string('image')->nullable();
            $table->text('summery')->nullable(); $table->text('description')->nullable();
            $table->decimal('price', 18, 2); $table->decimal('previous_price', 18, 2)->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type')->nullable(); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->longText('before')->nullable(); $table->longText('after')->nullable(); $table->longText('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->string('user_agent', 1000)->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
