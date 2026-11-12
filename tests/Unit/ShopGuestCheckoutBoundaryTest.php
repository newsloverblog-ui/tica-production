<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Shop\OrderController;
use App\Http\Controllers\FrontEnd\Shop\ShopController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopGuestCheckoutBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('shop_status')->default(true);
            $table->boolean('shop_guest_checkout')->default(false);
            $table->decimal('shop_tax', 8, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('online_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword')->unique();
            $table->boolean('status')->default(false);
        });
        Schema::create('offline_gateways', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status')->default(false);
            $table->unsignedInteger('serial_number')->default(0);
            $table->timestamps();
        });
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->boolean('is_default')->default(false);
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->integer('stock')->nullable();
            $table->string('feature_image')->nullable();
            $table->decimal('current_price', 18, 2);
            $table->boolean('status')->default(true);
            $table->string('type')->default('physical');
            $table->timestamps();
        });
        Schema::create('product_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('language_id');
            $table->string('title');
        });
        DB::table('languages')->insert(['id' => 1, 'code' => 'en', 'is_default' => 1]);

        session()->flush();
    }

    public function test_guest_query_parameter_cannot_open_checkout_when_guest_checkout_is_disabled(): void
    {
        DB::table('basic_settings')->insert([
            'shop_status' => 1,
            'shop_guest_checkout' => 0,
        ]);
        session()->put('cart', [1 => ['qty' => 1]]);
        $request = $this->request('/shop/checkout', 'GET', ['type' => 'guest']);
        app()->instance('request', $request);

        $response = app(ShopController::class)->checkout();

        $this->assertSame(route('customer.login', ['redirectPath' => 'checkout']), $response->getTargetUrl());
    }

    public function test_arbitrary_type_cannot_submit_an_order_when_guest_checkout_is_disabled(): void
    {
        DB::table('basic_settings')->insert(['shop_guest_checkout' => 0]);
        $request = $this->request('/shop/buy', 'POST', ['type' => 'anything']);

        $response = app(OrderController::class)->enrol($request);

        $this->assertSame(route('customer.login', ['redirectPath' => 'checkout']), $response->getTargetUrl());
    }

    public function test_enabled_guest_checkout_enforces_bounded_customer_identity(): void
    {
        DB::table('basic_settings')->insert(['shop_guest_checkout' => 1]);
        $request = $this->request('/shop/buy', 'POST', [
            'fname' => 'Ada',
            'lname' => 'Okafor',
            'email' => 'not-an-email',
            'phone' => '08000000000',
            'country' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'zip_code' => '100001',
            'address' => '1 Marina Road',
            'gateway' => 'paystack',
            'sameas_shipping' => 'on',
        ]);

        try {
            app(OrderController::class)->enrol($request);
            $this->fail('Expected invalid customer identity to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
    }

    public function test_shop_gateway_shipping_postcodes_are_never_sourced_from_city_fields(): void
    {
        $checked = 0;

        foreach (glob(app_path('Http/Controllers/FrontEnd/Shop/PaymentGateway/*.php')) ?: [] as $path) {
            $source = (string) file_get_contents($path);
            if (!str_contains($source, "'s_zip_code'")) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                "/'s_zip_code'\\s*=>[^\\n]*->s_city/",
                $source,
                basename($path) . ' corrupts the shipping postcode with a city value.'
            );
            $this->assertStringContainsString('$request->s_zip_code', $source);
            $this->assertStringContainsString('$request->zip_code', $source);
            $checked++;
        }

        $this->assertGreaterThanOrEqual(17, $checked, 'Too few shop checkout adapters were covered.');
    }

    public function test_opening_cart_invalidates_coupon_and_shipping_values_from_an_older_quote(): void
    {
        DB::table('basic_settings')->insert([
            'shop_status' => 1,
            'shop_guest_checkout' => 1,
        ]);
        session()->put([
            'Shop_discount' => 25,
            'Shop_coupon_code' => 'OLD-CART-COUPON',
            'shipping_cost' => 15,
        ]);

        app(ShopController::class)->cart();

        $this->assertFalse(session()->has('Shop_discount'));
        $this->assertFalse(session()->has('Shop_coupon_code'));
        $this->assertFalse(session()->has('shipping_cost'));
    }

    public function test_every_cart_mutation_invalidates_the_previous_checkout_quote(): void
    {
        $source = (string) file_get_contents(
            app_path('Http/Controllers/FrontEnd/Shop/ShopController.php')
        );

        $this->assertSame(1, substr_count($source, "Session::put('cart', \$cart);"));
        $this->assertStringContainsString(
            "Session::forget(['Shop_discount', 'Shop_coupon_code', 'shipping_cost']);",
            $source
        );
        $this->assertStringNotContainsString("\$quantity + \$cart[\$id]['qty']++;", $source);
    }

    public function test_add_to_cart_uses_catalogue_values_and_enforces_cumulative_quantity_limit(): void
    {
        DB::table('products')->insert([
            'id' => 1,
            'stock' => 200,
            'feature_image' => 'catalogue.jpg',
            'current_price' => 250,
            'status' => 1,
            'type' => 'physical',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_contents')->insert([
            'product_id' => 1,
            'language_id' => 1,
            'title' => 'Authoritative product',
        ]);
        session()->put([
            'cart' => [1 => ['qty' => 90, 'price' => 1, 'name' => 'forged']],
            'Shop_coupon_code' => 'SAVE10',
        ]);

        $rejected = app(ShopController::class)->addToCart2(
            $this->request('/shop/add-to-cart-2/1', 'POST', ['qty' => 11]),
            1
        );
        $this->assertSame(422, $rejected->getStatusCode());
        $this->assertSame(90, session('cart.1.qty'));
        $this->assertSame('SAVE10', session('Shop_coupon_code'));

        $accepted = app(ShopController::class)->addToCart2(
            $this->request('/shop/add-to-cart-2/1', 'POST', ['qty' => 10]),
            1
        );
        $this->assertSame(200, $accepted->getStatusCode());
        $this->assertSame(100, session('cart.1.qty'));
        $this->assertSame(250.0, (float) session('cart.1.price'));
        $this->assertSame('catalogue.jpg', session('cart.1.photo'));
        $this->assertFalse(session()->has('Shop_coupon_code'));
    }

    private function request(string $uri, string $method, array $input): Request
    {
        $request = Request::create($uri, $method, $input);
        $request->setLaravelSession(app('session')->driver());
        $request->headers->set('referer', '/shop/checkout');

        return $request;
    }
}
