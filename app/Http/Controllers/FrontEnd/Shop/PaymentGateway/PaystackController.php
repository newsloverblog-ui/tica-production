<?php

namespace App\Http\Controllers\FrontEnd\Shop\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\ProductPaymentIntent;
use App\Services\ProductPaystackCheckoutService;
use App\Services\ProductPaystackPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Throwable;

class PaystackController extends Controller
{
    public function enrolmentProcess(Request $request)
    {
        try {
            $intent = app(ProductPaystackCheckoutService::class)->start(
                array_merge($request->all(), ['coupon_code' => Session::get('Shop_coupon_code')]),
                (array) Session::get('cart', []),
                (int) $this->getLanguage()->id,
                Auth::guard('customer')->id()
            );
        } catch (Throwable $exception) {
            Log::warning('Product Paystack checkout initialization failed.', [
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect()->back()->withInput()->with(
                'error',
                'Paystack could not start the payment. Your cart is unchanged; please try again.'
            );
        }

        if ($intent->status === 'initialized' && ($url = $this->safeAuthorizationUrl($intent))) {
            return redirect()->away($url);
        }

        return redirect($this->recoveryUrl($intent))->with(
            'warning',
            'Paystack did not return a final checkout response. Your stock is reserved and no second charge has been created; check this attempt here.'
        );
    }

    public function notify(Request $request, ProductPaystackPaymentService $service)
    {
        $reference = trim((string) ($request->input('reference') ?: $request->input('trxref')));
        $intent = ProductPaymentIntent::query()
            ->where('gateway', 'paystack')
            ->where('reference', $reference)
            ->first();
        if (!$intent) {
            return redirect()->route('shop.checkout')->with('error', 'We could not identify that payment attempt.');
        }

        try {
            $outcome = $service->verify($intent);
        } catch (Throwable $exception) {
            Log::error('Product Paystack callback verification unavailable.', [
                'reference' => $reference,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))->with(
                'warning',
                'Your payment is not lost. We could not confirm it with Paystack just now; check the same transaction again.'
            );
        }

        return $this->outcomeRedirect($intent->fresh(), $outcome);
    }

    public function recovery(ProductPaymentIntent $intent)
    {
        abort_unless($intent->gateway === 'paystack', 404);

        return view('frontend.payment.recovery', [
            'intent' => $intent,
            'providerName' => 'Paystack',
            'authorizationUrl' => $this->safeAuthorizationUrl($intent),
            'verifyUrl' => URL::temporarySignedRoute(
                'product_order.paystack.recovery.verify',
                now()->addHours(24),
                ['intent' => $intent->id]
            ),
            'newCheckoutUrl' => route('shop.checkout'),
            'recoverySubject' => __('order'),
        ]);
    }

    public function retryRecovery(ProductPaymentIntent $intent, ProductPaystackPaymentService $service)
    {
        abort_unless($intent->gateway === 'paystack', 404);
        try {
            $outcome = $service->verify($intent);
        } catch (Throwable $exception) {
            Log::error('Product Paystack recovery verification unavailable.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
            return redirect($this->recoveryUrl($intent))->with(
                'warning',
                'We could not reach Paystack just now. No new charge was created; please check again.'
            );
        }

        return $this->outcomeRedirect($intent->fresh(), $outcome);
    }

    private function outcomeRedirect(ProductPaymentIntent $intent, array $outcome)
    {
        if (($outcome['status'] ?? null) === 'completed') {
            Session::forget(['cart', 'Shop_discount', 'Shop_coupon_code']);
        }
        return match ($outcome['status'] ?? 'pending') {
            'completed' => redirect()->route('product_order.complete', ['via' => 'paystack']),
            'failed' => redirect()->route('shop.checkout')->with('error', 'Paystack did not complete this transaction. You can safely try again.'),
            'verification_required' => redirect($this->recoveryUrl($intent))->with(
                'error',
                'We could not safely match the payment to this order. Support has been alerted; do not pay again.'
            ),
            default => redirect($this->recoveryUrl($intent))->with(
                'warning',
                'Paystack has not confirmed this payment yet. No second charge has been created.'
            ),
        };
    }

    private function recoveryUrl(ProductPaymentIntent $intent): string
    {
        return URL::temporarySignedRoute(
            'product_order.paystack.recovery',
            now()->addHours(24),
            ['intent' => $intent->id]
        );
    }

    private function safeAuthorizationUrl(ProductPaymentIntent $intent): ?string
    {
        $url = trim((string) $intent->authorization_url);
        if ($url === '' || $intent->expires_at?->isPast()) {
            return null;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https'
            || ($host !== 'checkout.paystack.com' && !str_ends_with($host, '.paystack.com'))) {
            return null;
        }
        return $url;
    }
}
