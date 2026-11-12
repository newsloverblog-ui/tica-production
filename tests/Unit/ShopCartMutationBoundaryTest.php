<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Shop\ShopController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCartMutationBoundaryTest extends TestCase
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

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('current_price', 12, 2)->default(0);
            $table->string('feature_image')->nullable();
            $table->string('type')->default('physical');
            $table->boolean('status')->default(true);
        });
        Schema::create('product_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('language_id');
            $table->string('title');
        });
    }

    public function test_remove_from_an_empty_cart_is_safe_and_idempotent(): void
    {
        $response = $this->controller()->cartitemremove(99);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $response->getData(true)['count']);
        $this->assertSame(0, $response->getData(true)['total']);
    }

    public function test_cart_update_is_bounded_and_uses_only_existing_cart_items(): void
    {
        $this->seedProduct(1, stock: 5, price: 10.00);
        Session::put('cart', [
            1 => ['name' => 'Test product', 'qty' => 1, 'price' => 10.00, 'photo' => null, 'type' => 'physical'],
        ]);

        $response = $this->controller()->updatecart($this->request([
            'product_id' => [1],
            'qty' => [3],
        ]));

        $this->assertSame(3, $response->getData(true)['count']);
        $this->assertSame(30.0, (float) $response->getData(true)['total']);
        $this->assertSame(3, Session::get('cart.1.qty'));

        foreach ([
            ['product_id' => [2], 'qty' => [1]],
            ['product_id' => [1], 'qty' => [101]],
            ['product_id' => [1, 2], 'qty' => [1]],
        ] as $payload) {
            try {
                $this->controller()->updatecart($this->request($payload));
                $this->fail('Malformed cart update unexpectedly passed validation.');
            } catch (ValidationException) {
                $this->assertSame(3, Session::get('cart.1.qty'));
            }
        }
    }

    public function test_order_now_enforces_stock_and_accumulates_a_valid_quantity(): void
    {
        $this->seedProduct(3, stock: 5, price: 25.00);

        $this->controller()->orderNow($this->request(['product_id' => 3, 'quantity' => 2]));
        $this->controller()->orderNow($this->request(['product_id' => 3, 'quantity' => 2]));

        $this->assertSame(4, Session::get('cart.3.qty'));
        $this->assertSame(25.0, (float) Session::get('cart.3.price'));

        $response = $this->controller()->orderNow($this->request(['product_id' => 3, 'quantity' => 2]));

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Out of Stock!', session('message'));
        $this->assertSame(4, Session::get('cart.3.qty'));
    }

    private function controller(): ShopController
    {
        return new class extends ShopController {
            public function getLanguage()
            {
                return (object) ['id' => 1];
            }
        };
    }

    private function request(array $payload): Request
    {
        $request = Request::create('/shop/cart', 'POST', $payload, [], [], [
            'HTTP_REFERER' => 'https://tica.test/shop/cart',
        ]);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);

        return $request;
    }

    private function seedProduct(int $id, int $stock, float $price): void
    {
        DB::table('products')->insert([
            'id' => $id,
            'stock' => $stock,
            'current_price' => $price,
            'feature_image' => null,
            'type' => 'physical',
            'status' => 1,
        ]);
        DB::table('product_contents')->insert([
            'product_id' => $id,
            'language_id' => 1,
            'title' => 'Test product',
        ]);
    }
}
