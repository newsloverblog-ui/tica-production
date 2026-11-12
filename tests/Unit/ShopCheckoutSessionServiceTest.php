<?php

namespace Tests\Unit;

use App\Services\ShopCheckoutSessionService;
use App\Services\ProductOrderInventoryService;
use App\Http\Controllers\FrontEnd\Shop\OrderController;
use App\Models\ShopManagement\ProductOrder;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ShopCheckoutSessionServiceTest extends TestCase
{
    public function test_completed_checkout_cleanup_removes_only_shop_transaction_state(): void
    {
        session()->put([
            'cart' => [1 => ['qty' => 1]],
            'Shop_discount' => 10,
            'Shop_coupon_code' => 'SAVE10',
            'shipping_cost' => 5,
            'arrData' => ['payment' => 'pending'],
            'paymentId' => 'provider-payment-id',
            'lang' => 'en',
            'unrelated_preference' => 'keep-me',
        ]);

        app(ShopCheckoutSessionService::class)->clearCompletedCheckout();

        foreach ([
            'cart', 'Shop_discount', 'Shop_coupon_code', 'shipping_cost',
            'arrData', 'paymentId',
        ] as $key) {
            $this->assertFalse(session()->has($key), $key . ' was not cleared.');
        }
        $this->assertSame('en', session('lang'));
        $this->assertSame('keep-me', session('unrelated_preference'));
    }

    public function test_legacy_gateway_inventory_boundary_invokes_central_checkout_cleanup(): void
    {
        $source = (string) file_get_contents(
            app_path('Http/Controllers/FrontEnd/Shop/OrderController.php')
        );
        $reservation = strpos($source, '->reserveFromCart(');
        $cleanup = strpos($source, '->clearCompletedCheckout();');

        $this->assertNotFalse($reservation);
        $this->assertNotFalse($cleanup);
        $this->assertGreaterThan($reservation, $cleanup, 'Checkout state must clear only after inventory reservation.');
    }

    public function test_every_online_legacy_shop_gateway_uses_atomic_order_and_item_persistence(): void
    {
        $checked = 0;
        $atomicGateways = 0;

        foreach (glob(app_path('Http/Controllers/FrontEnd/Shop/PaymentGateway/*.php')) ?: [] as $path) {
            $source = (string) file_get_contents($path);
            if (str_contains($source, '->storeOrderWithItems(')) {
                $atomicGateways++;
                $this->assertStringNotContainsString(
                    '->generateInvoice(',
                    $source,
                    basename($path) . ' still generates a paid invoice synchronously.'
                );
                $this->assertStringNotContainsString(
                    '->sendMail(',
                    $source,
                    basename($path) . ' still dispatches paid fulfilment from the callback.'
                );
            }
            if (in_array(basename($path), ['OfflineController.php', 'PaystackController.php'], true)
                || !str_contains($source, "route('product_order.complete")) {
                continue;
            }

            $this->assertStringContainsString(
                '->storeOrderWithItems(',
                $source,
                basename($path) . ' does not persist order and inventory atomically.'
            );
            $this->assertStringNotContainsString('->storeData(', $source);
            $this->assertStringNotContainsString('->storeOders(', $source);
            $checked++;
        }

        $this->assertGreaterThanOrEqual(15, $checked, 'Too few online legacy completion paths were covered.');
        $this->assertGreaterThanOrEqual(16, $atomicGateways, 'Too few online legacy shop gateways use atomic persistence.');
    }

    public function test_atomic_legacy_persistence_rolls_back_order_and_preserves_session_on_inventory_failure(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('atomic_probe', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });

        $inventory = Mockery::mock(ProductOrderInventoryService::class);
        $inventory->shouldReceive('reserveFromCart')->once()
            ->andThrow(new DomainException('inventory rejected'));
        $this->app->instance(ProductOrderInventoryService::class, $inventory);

        session()->put([
            'cart' => [1 => ['qty' => 1]],
            'Shop_coupon_code' => 'KEEP-ON-FAILURE',
        ]);
        $orders = new class extends OrderController {
            public function getLanguage()
            {
                return (object) ['id' => 1];
            }

            public function storeData($info)
            {
                DB::table('atomic_probe')->insert(['value' => 'created']);
                $order = new ProductOrder();
                $order->id = 99;

                return $order;
            }
        };

        try {
            $orders->storeOrderWithItems([]);
            $this->fail('Inventory failure must roll back the order transaction.');
        } catch (DomainException $exception) {
            $this->assertSame('inventory rejected', $exception->getMessage());
        }

        $this->assertDatabaseCount('atomic_probe', 0);
        $this->assertSame(1, session('cart.1.qty'));
        $this->assertSame('KEEP-ON-FAILURE', session('Shop_coupon_code'));
    }
}
