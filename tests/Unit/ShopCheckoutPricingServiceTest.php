<?php

namespace Tests\Unit;

use App\Services\ShopCheckoutPricingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopCheckoutPricingServiceTest extends TestCase
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

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->integer('stock')->default(0);
            $table->string('feature_image')->nullable();
            $table->decimal('current_price', 18, 2);
            $table->boolean('status')->default(true);
            $table->string('type')->default('physical');
            $table->timestamps();
        });
        Schema::create('shop_coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 18, 2);
            $table->string('start_date');
            $table->string('end_date');
            $table->decimal('minimum_spend', 18, 2)->default(0);
            $table->timestamps();
        });

        Carbon::setTestNow('2026-11-06 12:00:00');
        DB::table('products')->insert([
            'id' => 1,
            'stock' => 10,
            'feature_image' => 'catalogue.jpg',
            'current_price' => 100,
            'status' => 1,
            'type' => 'physical',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_quote_replaces_client_price_and_calculates_coupon_from_catalogue_subtotal(): void
    {
        $this->coupon('SAVE10', 'percentage', 10, 150);

        $quote = app(ShopCheckoutPricingService::class)->quote([
            1 => ['qty' => 2, 'price' => 1, 'photo' => 'forged.jpg'],
        ], 'SAVE10');

        $this->assertSame(200.0, $quote['subtotal']);
        $this->assertSame(20.0, $quote['discount']);
        $this->assertSame(100.0, $quote['cart'][1]['price']);
        $this->assertSame('catalogue.jpg', $quote['cart'][1]['photo']);
    }

    public function test_coupon_cannot_bypass_minimum_spend(): void
    {
        $this->coupon('SAVE10', 'percentage', 10, 150);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('minimum spend');

        app(ShopCheckoutPricingService::class)->quote([1 => ['qty' => 1]], 'SAVE10');
    }

    public function test_fixed_coupon_is_capped_at_the_authoritative_subtotal(): void
    {
        $this->coupon('BIGSAVE', 'fixed', 500, 0);

        $quote = app(ShopCheckoutPricingService::class)->quote([1 => ['qty' => 1]], 'BIGSAVE');

        $this->assertSame(100.0, $quote['discount']);
    }

    public function test_expired_coupon_fails_closed(): void
    {
        DB::table('shop_coupons')->insert([
            'code' => 'OLD', 'type' => 'fixed', 'value' => 10,
            'start_date' => '01/01/2025', 'end_date' => '01/02/2025',
            'minimum_spend' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(DomainException::class);

        app(ShopCheckoutPricingService::class)->quote([1 => ['qty' => 1]], 'OLD');
    }

    private function coupon(string $code, string $type, float $value, float $minimumSpend): void
    {
        DB::table('shop_coupons')->insert([
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'start_date' => '11/01/2026',
            'end_date' => '11/30/2026',
            'minimum_spend' => $minimumSpend,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
