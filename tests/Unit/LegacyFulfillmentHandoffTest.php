<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Http\Controllers\FrontEnd\Shop\OrderController;
use App\Models\BookingDelivery;
use App\Models\ProductOrderDelivery;
use App\Services\PaidBookingFulfillmentService;
use App\Services\PaidProductOrderFulfillmentService;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class LegacyFulfillmentHandoffTest extends TestCase
{
    public function test_legacy_event_callback_uses_durable_fulfillment_instead_of_synchronous_mail(): void
    {
        $delivery = new BookingDelivery(['booking_id' => 41, 'status' => 'pending']);
        $service = Mockery::mock(PaidBookingFulfillmentService::class);
        $service->shouldReceive('dispatch')->once()->with(41)->andReturn($delivery);
        $this->app->instance(PaidBookingFulfillmentService::class, $service);

        $result = (new BookingController())->sendMail((object) ['id' => 41]);

        $this->assertTrue($result);
    }

    public function test_legacy_product_callback_uses_durable_fulfillment_instead_of_synchronous_mail(): void
    {
        $delivery = new ProductOrderDelivery(['product_order_id' => 52, 'status' => 'pending']);
        $service = Mockery::mock(PaidProductOrderFulfillmentService::class);
        $service->shouldReceive('dispatch')->once()->with(52)->andReturn($delivery);
        $this->app->instance(PaidProductOrderFulfillmentService::class, $service);

        $result = (new OrderController())->sendMail((object) ['id' => 52]);

        $this->assertTrue($result);
    }

    public function test_event_gateway_callbacks_never_generate_invoices_or_public_qr_artifacts_synchronously(): void
    {
        $gatewayFiles = File::glob(app_path('Http/Controllers/FrontEnd/PaymentGateway/*Controller.php'));

        $this->assertNotEmpty($gatewayFiles);

        foreach ($gatewayFiles as $file) {
            $source = File::get($file);

            $this->assertStringNotContainsString(
                '->generateInvoice(',
                $source,
                basename($file).' must hand invoice generation to the durable fulfilment worker.'
            );
            $this->assertStringNotContainsString(
                'assets/admin/qrcodes',
                $source,
                basename($file).' must not create or clean up reusable admission artifacts in a payment callback.'
            );
        }
    }
}
