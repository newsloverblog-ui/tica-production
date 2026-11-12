<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\HomeController;
use App\Http\Middleware\EnsurePaymentGatewayAllowed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PaymentGatewayCallbackBoundaryTest extends TestCase
{
    public function test_xendit_callback_never_echoes_an_unscoped_provider_payload(): void
    {
        Session::forget('xendit_payment_type');
        $request = Request::create('/xendit/callback', 'POST', [
            'status' => 'PAID',
            'secret' => 'provider-payload-must-not-be-reflected',
        ]);
        $request->setLaravelSession(app('session')->driver());

        $response = (new HomeController())->xendit_callback($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['status' => 'unknown checkout scope'], $response->getData(true));
        $this->assertStringNotContainsString('provider-payload', $response->getContent());
    }

    public function test_myfatoorah_callback_never_echoes_an_unscoped_provider_payload(): void
    {
        Session::forget('myfatoorah_payment_type');
        $request = Request::create('/myfatoorah/callback', 'GET', [
            'paymentId' => 'provider-payment-id-must-not-be-reflected',
            'secret' => 'provider-payload-must-not-be-reflected',
        ]);
        $request->setLaravelSession(app('session')->driver());

        $response = (new HomeController())->myfatoorah_callback($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['status' => 'unknown checkout scope'], $response->getData(true));
        $this->assertStringNotContainsString('provider-payment-id', $response->getContent());
        $this->assertStringNotContainsString('provider-payload', $response->getContent());
    }

    public function test_production_rejects_legacy_event_and_shop_callbacks(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);
        $middleware = app(EnsurePaymentGatewayAllowed::class);

        foreach ([['event', 'razorpay'], ['event', 'iyzico'], ['shop', 'flutterwave']] as [$scope, $gateway]) {
            try {
                $middleware->handle(Request::create('/callback'), fn () => response('unsafe'), $scope, $gateway);
                $this->fail("{$scope} {$gateway} callback unexpectedly passed production policy.");
            } catch (NotFoundHttpException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_production_keeps_hardened_callbacks_available(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);
        $middleware = app(EnsurePaymentGatewayAllowed::class);

        $eventResponse = $middleware->handle(
            Request::create('/callback'),
            fn () => response('event-ok'),
            'event',
            'paystack'
        );
        $shopResponse = $middleware->handle(
            Request::create('/callback'),
            fn () => response('shop-ok'),
            'shop',
            'paystack'
        );

        $this->assertSame('event-ok', $eventResponse->getContent());
        $this->assertSame('shop-ok', $shopResponse->getContent());
    }

    public function test_non_production_rejects_legacy_callbacks_without_explicit_opt_in(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config()->set('payments.allow_legacy_non_production', false);

        $this->expectException(NotFoundHttpException::class);
        app(EnsurePaymentGatewayAllowed::class)->handle(
            Request::create('/callback'),
            fn () => response('unsafe'),
            'event',
            'razorpay'
        );
    }

    public function test_explicit_local_opt_in_can_exercise_non_retired_shop_integrations(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config()->set('payments.allow_legacy_non_production', true);

        $response = app(EnsurePaymentGatewayAllowed::class)->handle(
            Request::create('/callback'),
            fn () => response('sandbox-ok'),
            'shop',
            'flutterwave'
        );

        $this->assertSame('sandbox-ok', $response->getContent());
    }

    public function test_every_legacy_gateway_route_has_policy_and_rate_boundaries(): void
    {
        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $policy = collect($middleware)->first(
                static fn ($entry): bool => is_string($entry) && str_starts_with($entry, 'payment.gateway:')
            );

            if ($policy === null) {
                continue;
            }

            $label = $route->getName() ?: $route->uri();
            $this->assertMatchesRegularExpression(
                '/^payment\.gateway:(event|shop),[a-z0-9_]+$/',
                $policy,
                "{$label} has a malformed gateway policy boundary"
            );
            $this->assertTrue(
                collect($middleware)->contains(
                    static fn ($entry): bool => is_string($entry) && str_starts_with($entry, 'throttle:')
                ),
                "{$label} is not rate bounded"
            );
            $checked++;
        }

        $this->assertGreaterThanOrEqual(30, $checked, 'The gateway boundary audit covered too few routes.');
    }

    public function test_hardened_browser_callbacks_and_recovery_routes_honor_gateway_policy(): void
    {
        $expected = [
            'event_booking.paystack.notify' => 'payment.gateway:event,paystack',
            'event_booking.paystack.recovery' => 'payment.gateway:event,paystack',
            'event_booking.paystack.recovery.verify' => 'payment.gateway:event,paystack',
            'event_booking.flutterwave.notify' => 'payment.gateway:event,flutterwave',
            'event_booking.flutterwave.recovery' => 'payment.gateway:event,flutterwave',
            'event_booking.flutterwave.recovery.verify' => 'payment.gateway:event,flutterwave',
            'product_order.paystack.notify' => 'payment.gateway:shop,paystack',
            'product_order.paystack.recovery' => 'payment.gateway:shop,paystack',
            'product_order.paystack.recovery.verify' => 'payment.gateway:shop,paystack',
        ];

        foreach ($expected as $name => $policy) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "{$name} route is missing");
            $this->assertContains($policy, $route->gatherMiddleware(), "{$name} bypasses gateway policy");
        }
    }
}
