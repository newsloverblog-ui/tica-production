<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaystackController;
use App\Services\ProductPaystackCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentExceptionDisclosureTest extends TestCase
{
    public function test_product_paystack_initialization_does_not_disclose_provider_exception_text(): void
    {
        $secret = 'sk_live_should_never_reach_the_customer_or_logs';
        $service = Mockery::mock(ProductPaystackCheckoutService::class);
        $service->shouldReceive('start')->once()->andThrow(new RuntimeException($secret, 503));
        $this->app->instance(ProductPaystackCheckoutService::class, $service);
        Log::spy();

        Session::put('cart', [['product_id' => 1, 'quantity' => 1]]);
        $request = Request::create('/shop/paystack', 'POST');
        $request->setLaravelSession(app('session')->driver());

        $controller = new class extends PaystackController
        {
            public function getLanguage()
            {
                return (object) ['id' => 1];
            }
        };

        $response = $controller->enrolmentProcess($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringNotContainsString($secret, (string) Session::get('error'));
        $this->assertSame(
            'Paystack could not start the payment. Your cart is unchanged; please try again.',
            Session::get('error')
        );
        Log::shouldHaveReceived('warning')->once()->withArgs(
            function (string $message, array $context) use ($secret): bool {
                return $message === 'Product Paystack checkout initialization failed.'
                    && ($context['exception_type'] ?? null) === RuntimeException::class
                    && ($context['exception_code'] ?? null) === 503
                    && !str_contains(json_encode($context), $secret);
            }
        );
    }

    public function test_payment_controllers_do_not_expose_or_log_raw_exception_messages(): void
    {
        $paths = [
            'Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php',
            'Http/Controllers/FrontEnd/PaymentGateway/FlutterwaveController.php',
            'Http/Controllers/FrontEnd/PaymentGateway/StripeController.php',
            'Http/Controllers/FrontEnd/PaymentGateway/PaytmController.php',
            'Http/Controllers/FrontEnd/Shop/PaymentGateway/StripeController.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(app_path($path));

            $this->assertIsString($source);
            $this->assertStringNotContainsString('->getMessage()', $source, $path);
        }
    }

    public function test_public_checkout_boundaries_only_show_expected_selection_errors(): void
    {
        $booking = file_get_contents(app_path(
            'Http/Controllers/FrontEnd/Event/BookingController.php'
        ));
        $checkout = file_get_contents(app_path(
            'Http/Controllers/FrontEnd/CheckOutController.php'
        ));
        $orders = file_get_contents(app_path(
            'Http/Controllers/FrontEnd/Shop/OrderController.php'
        ));

        $this->assertIsString($booking);
        $this->assertIsString($checkout);
        $this->assertIsString($orders);

        $this->assertStringContainsString(
            'We could not refresh your ticket selection. Please try again.',
            $booking
        );
        $this->assertStringContainsString(
            'We could not refresh your ticket selection. Please try again.',
            $checkout
        );
        $this->assertStringNotContainsString(
            "catch (\\Throwable \$exception) {\n      return redirect()->back()->with('error', \$exception->getMessage())",
            $booking
        );
        $this->assertStringNotContainsString(
            "catch (\\Throwable \$exception) {\n      report(\$exception);\n\n      return redirect()->route('event.details', [\$event->slug, \$event->id])\n        ->with('error', \$exception->getMessage())",
            $checkout
        );
        $this->assertStringNotContainsString('Mailer Error:', $booking);
        $this->assertStringNotContainsString('Mailer Error:', $orders);
    }
}
