<?php

namespace Tests\Unit;

use App\Models\ShopManagement\Product;
use App\Models\ShopManagement\ProductOrder;
use App\Services\ProductOrderInventoryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductOrderInventoryServiceTest extends TestCase
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

    public function test_reservation_uses_server_prices_and_decrements_physical_stock_once(): void
    {
        $physical = $this->product('physical', 5, 25, 'Physical item');
        $digital = $this->product('digital', null, 10, 'Digital item');
        $order = $this->order(60);
        $cart = [
            $physical->id => ['qty' => 2, 'price' => 1, 'name' => 'tampered'],
            $digital->id => ['qty' => 1, 'price' => 1, 'name' => 'tampered'],
        ];
        $service = app(ProductOrderInventoryService::class);

        $service->reserveFromCart($order, $cart, 1);
        $service->reserveFromCart($order->fresh(), $cart, 1);

        $this->assertSame(3, (int) $physical->fresh()->stock);
        $this->assertNotNull($order->fresh()->inventory_reserved_at);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'product_order_id' => $order->id,
            'product_id' => $physical->id,
            'title' => 'Physical item',
            'qty' => '2',
            'price' => 25,
        ]);
    }

    public function test_stale_cart_total_fails_without_changing_inventory(): void
    {
        $product = $this->product('physical', 5, 25, 'Physical item');
        $order = $this->order(1);

        try {
            app(ProductOrderInventoryService::class)->reserveFromCart(
                $order,
                [$product->id => ['qty' => 1, 'price' => 1]],
                1
            );
            $this->fail('Expected stale cart validation to fail.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('prices changed', $exception->getMessage());
        }

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertNull($order->fresh()->inventory_reserved_at);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_pending_rejected_order_releases_inventory_exactly_once(): void
    {
        $product = $this->product('physical', 5, 25, 'Physical item');
        $order = $this->order(50);
        $service = app(ProductOrderInventoryService::class);
        $service->reserveFromCart($order, [$product->id => ['qty' => 2]], 1);

        $service->release($order->fresh(), 'payment_rejected');
        $service->release($order->fresh(), 'payment_rejected');

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->inventory_released_at);
    }

    public function test_release_fails_closed_when_reserved_product_is_missing(): void
    {
        $product = $this->product('physical', 5, 25, 'Physical item');
        $order = $this->order(50);
        $service = app(ProductOrderInventoryService::class);
        $service->reserveFromCart($order, [$product->id => ['qty' => 2]], 1);
        $product->delete();

        try {
            $service->release($order->fresh(), 'payment_rejected');
            $this->fail('Missing reserved product evidence must stop release.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('no longer exist', $exception->getMessage());
        }

        $this->assertNull($order->fresh()->inventory_released_at);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'product_inventory.released')->count());
    }

    public function test_release_fails_closed_for_invalid_or_duplicate_item_evidence(): void
    {
        $product = $this->product('physical', 5, 25, 'Physical item');
        $order = $this->order(50);
        $service = app(ProductOrderInventoryService::class);
        $service->reserveFromCart($order, [$product->id => ['qty' => 2]], 1);
        DB::table('order_items')->where('product_order_id', $order->id)->update(['qty' => '-2']);

        try {
            $service->release($order->fresh(), 'payment_rejected');
            $this->fail('Invalid item evidence must stop release.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('invalid inventory evidence', $exception->getMessage());
        }
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertNull($order->fresh()->inventory_released_at);

        DB::table('order_items')->where('product_order_id', $order->id)->update(['qty' => '2']);
        DB::table('order_items')->insert([
            'product_order_id' => $order->id,
            'product_id' => $product->id,
            'user_id' => 1,
            'title' => 'Duplicate',
            'qty' => '1',
            'price' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            $service->release($order->fresh(), 'payment_rejected');
            $this->fail('Duplicate item evidence must stop release.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('duplicate product', $exception->getMessage());
        }

        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertNull($order->fresh()->inventory_released_at);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'product_inventory.released')->count());
    }

    private function product(string $type, ?int $stock, float $price, string $title): Product
    {
        $product = Product::create([
            'stock' => $stock,
            'sku' => random_int(1000, 9999),
            'feature_image' => 'product.jpg',
            'current_price' => $price,
            'previous_price' => null,
            'status' => 1,
            'type' => $type,
        ]);
        DB::table('product_contents')->insert([
            'product_id' => $product->id,
            'language_id' => 1,
            'title' => $title,
            'summary' => 'Summary',
            'description' => 'Description',
        ]);

        return $product;
    }

    private function order(float $subtotal): ProductOrder
    {
        return ProductOrder::create([
            'user_id' => 1,
            'cart_total' => $subtotal,
            'total' => $subtotal,
            'method' => 'Bank transfer',
            'gateway_type' => 'offline',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'order_number' => uniqid('order-', true),
            'currency_symbol' => '₦',
            'currency_symbol_position' => 'left',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id(); $table->string('stock')->nullable(); $table->string('sku')->nullable();
            $table->string('feature_image')->nullable(); $table->decimal('current_price', 18, 2);
            $table->decimal('previous_price', 18, 2)->nullable(); $table->string('is_feature')->nullable();
            $table->integer('status'); $table->string('type'); $table->string('file_type')->nullable();
            $table->string('download_file')->nullable(); $table->text('download_link')->nullable(); $table->timestamps();
        });
        Schema::create('product_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('language_id');
            $table->string('title'); $table->text('summary')->nullable(); $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->decimal('cart_total', 18, 2);
            $table->decimal('total', 18, 2); $table->string('method')->nullable(); $table->string('gateway_type')->nullable();
            $table->string('payment_status'); $table->string('order_status'); $table->string('order_number')->unique();
            $table->string('tnxid')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamp('inventory_reserved_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable(); $table->timestamps();
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
