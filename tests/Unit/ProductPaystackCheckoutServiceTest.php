<?php

namespace Tests\Unit;

use App\Services\PaystackGatewayClient;
use App\Services\ProductPaystackCheckoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductPaystackCheckoutServiceTest extends TestCase
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
        DB::table('basic_settings')->insert([
            'shop_tax' => 10, 'base_currency_text' => 'NGN',
            'base_currency_text_position' => 'left', 'base_currency_symbol' => '₦',
            'base_currency_symbol_position' => 'left',
        ]);
        DB::table('products')->insert([
            'id' => 1, 'stock' => '5', 'sku' => 'SKU-1', 'feature_image' => 'one.jpg',
            'current_price' => 100, 'status' => 1, 'type' => 'physical',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_contents')->insert([
            'product_id' => 1, 'language_id' => 1, 'title' => 'Real product',
            'summary' => 'Summary', 'description' => 'Description',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_checkout_prices_from_catalogue_reserves_stock_and_initializes_durable_intent(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => function ($request) {
                return Http::response([
                    'status' => true,
                    'data' => [
                        'reference' => $request['reference'],
                        'authorization_url' => 'https://checkout.paystack.com/secure-token',
                    ],
                ]);
            },
        ]);

        $intent = app(ProductPaystackCheckoutService::class)->start(
            $this->checkout(),
            [1 => ['qty' => 2, 'price' => 1]],
            1,
            7,
            new PaystackGatewayClient('test-secret')
        );

        $this->assertSame('initialized', $intent->status);
        $this->assertSame(220.0, (float) $intent->amount);
        $this->assertSame(3, (int) DB::table('products')->where('id', 1)->value('stock'));
        $this->assertDatabaseHas('product_orders', [
            'id' => $intent->product_order_id, 'cart_total' => 200,
            'tax' => 20, 'total' => 220, 'payment_status' => 'pending',
            'tnxid' => $intent->reference,
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_order_id' => $intent->product_order_id, 'product_id' => 1,
            'qty' => '2', 'price' => 100,
        ]);
        Http::assertSent(fn ($request) => $request['amount'] === 22000
            && $request['reference'] === $intent->reference
            && $request['metadata']['product_order_id'] === $intent->product_order_id);
    }

    public function test_authoritative_initialization_rejection_marks_failed_and_restores_stock(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => false, 'message' => 'Rejected',
            ], 400),
        ]);

        try {
            app(ProductPaystackCheckoutService::class)->start(
                $this->checkout(), [1 => ['qty' => 2]], 1, null,
                new PaystackGatewayClient('test-secret')
            );
            $this->fail('Expected Paystack rejection to be raised.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rejected', $exception->getMessage());
        }

        $this->assertSame(5, (int) DB::table('products')->where('id', 1)->value('stock'));
        $this->assertDatabaseHas('product_payment_intents', ['status' => 'failed']);
        $this->assertNotNull(DB::table('product_orders')->value('inventory_released_at'));
    }

    private function checkout(): array
    {
        return [
            'fname' => 'Ada', 'lname' => 'Okafor', 'email' => 'ada@example.com',
            'phone' => '08000000000', 'country' => 'Nigeria', 'state' => 'Lagos',
            'city' => 'Lagos', 'zip_code' => '100001', 'address' => '1 Tica Street',
            'sameas_shipping' => '1', 'discount' => 999999,
        ];
    }

    private function createSchema(): void
    {
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->decimal('shop_tax', 8, 2)->default(0);
            $table->string('base_currency_text'); $table->string('base_currency_text_position')->nullable();
            $table->string('base_currency_symbol')->nullable(); $table->string('base_currency_symbol_position')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id(); $table->string('stock')->nullable(); $table->string('sku')->nullable();
            $table->string('feature_image')->nullable(); $table->decimal('current_price', 18, 2);
            $table->decimal('previous_price', 18, 2)->nullable(); $table->integer('status');
            $table->string('type'); $table->timestamps();
        });
        Schema::create('product_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('language_id');
            $table->string('title'); $table->text('summary')->nullable(); $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('shipping_charges', function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->decimal('charge', 18, 2); $table->timestamps();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable();
            foreach (['billing_fname','billing_lname','billing_email','billing_phone','billing_country','billing_state','billing_city','billing_zip_code','billing_address','shipping_fname','shipping_lname','shipping_email','shipping_phone','shipping_country','shipping_state','shipping_city','shipping_zip_code','shipping_address'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('cart_total', 18, 2); $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0); $table->decimal('tax_percentage', 8, 2)->default(0);
            $table->decimal('total', 18, 2); $table->string('currency_text')->nullable();
            $table->string('currency_text_position')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->string('order_number')->unique();
            $table->string('shipping_method')->nullable(); $table->decimal('shipping_charge', 18, 2)->default(0);
            $table->string('method'); $table->string('gateway_type'); $table->string('payment_status');
            $table->string('order_status'); $table->string('tnxid')->nullable(); $table->unsignedBigInteger('charge_id')->nullable();
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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable(); $table->string('title'); $table->string('sku')->nullable();
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
