<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentIntent;
use App\Services\IncidentAlertService;
use App\Services\StripeEventCheckoutService;
use App\Services\StripeGatewayClient;
use App\Services\StripePaymentRecoveryService;
use App\Services\TicketReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

/**
 * Event ticket payments through Stripe Checkout (Stripe's hosted page).
 * Tickets are only issued after Stripe's API confirms the session is paid:
 * the success redirect, the signed webhook and the recovery page all ask
 * Stripe first.
 */
class StripeController extends Controller
{
    use Concerns\FulfilsVerifiedPayment;

    // =========================================================================
    // STEP 1 — Create a Checkout session and send the buyer to Stripe
    // =========================================================================
    public function bookingProcess(Request $request, $eventId)
    {
        $reservationToken = null;
        $request->merge(['gateway' => strtolower(trim((string) $request->input('gateway')))]);
        $request->validate([
            'fname'   => 'required',
            'lname'   => 'required',
            'email'   => 'required|email:rfc|max:255',
            'phone'   => 'required|string|max:40',
            'gateway' => 'required|in:stripe',
        ]);

        $total      = (float) Session::get('grand_total');
        $tax_amount = (float) Session::get('tax');

        $chargeCurrency = \App\Support\TicaCurrency::normalize(\App\Models\Event::query()->whereKey((int) $eventId)->value('currency'));
        if (!in_array($chargeCurrency, StripeGatewayClient::CURRENCIES, true)) {
            return redirect()->back()->with('currency_error', 'Stripe cannot take payments in ' . $chargeCurrency . '. Choose another payment method.')->withInput();
        }
        if ($total <= 0 || $tax_amount < 0 || $tax_amount > $total) {
            return redirect()->back()->with('error', 'The payment total is invalid.')->withInput();
        }

        try {
            $reservationToken = (new TicketReservationService())->replace(
                (int) $eventId,
                Session::get('selTickets') ?: [],
                Session::get('ticket_reservation_token'),
                $request->session()->getId(),
                auth('customer')->id(),
                app(\App\Services\EventTicketPurchaseLimitService::class)->buyerIdentityHash($request->email),
            );
            Session::put('ticket_reservation_token', $reservationToken);

            $currencyInfo = Basic::query()->select('base_currency_text_position')->first();
            $arrData = [
                'event_id'                 => $eventId,
                'price'                    => $total - $tax_amount,
                'tax'                      => $tax_amount,
                'commission'               => (float) Session::get('commission', 0),
                'quantity'                 => Session::get('quantity'),
                'discount'                 => Session::get('discount'),
                'total_early_bird_dicount' => Session::get('total_early_bird_dicount'),
                'currencyText'             => $chargeCurrency,
                'currencyTextPosition'     => $currencyInfo->base_currency_text_position ?? 'right',
                'currencySymbol'           => \App\Support\TicaCurrency::symbol($chargeCurrency),
                'currencySymbolPosition'   => 'left',
                'fname'                    => $request->fname,
                'lname'                    => $request->lname,
                'email'                    => $request->email,
                'phone'                    => $request->phone,
                'country'                  => $request->country  ?? null,
                'state'                    => $request->state    ?? null,
                'city'                     => $request->city     ?? null,
                'zip_code'                 => $request->zip_code ?? null,
                'address'                  => $request->address  ?? null,
                'paymentMethod'            => 'Stripe',
                'gatewayType'              => 'online',
                'paymentStatus'            => 'completed',
                'event_date'               => Session::get('event_date'),
                'selected_tickets'         => Session::get('selTickets'),
                'reservation_token'        => $reservationToken,
                'customer_id'              => auth('customer')->id(),
                'attendees'                => Session::get('tica_attendees'),
                'coupon_id'                => Session::get('event_coupon_id'),
                'tracking_link_id'         => Session::get('tica_tracking_link_id'),
            ];

            $event = \App\Models\Event::query()->find((int) $eventId);
            $intent = app(StripeEventCheckoutService::class)->initialize([
                'event_id' => (int) $eventId,
                'customer_id' => auth('customer')->id(),
                'amount' => $total,
                'currency' => $chargeCurrency,
                'email' => $request->email,
                'description' => 'TICA tickets',
                'cancel_url' => $event ? url('/checkout/' . \Illuminate\Support\Str::slug((string) \App\Models\Event\EventContent::query()->where('event_id', $event->id)->value('title')) . '/' . $event->id) : url('/'),
                'payload' => $arrData,
            ]);

            if ($intent->status === 'verification_required') {
                return redirect($this->recoveryUrl($intent))->with(
                    'warning',
                    'Stripe did not return a final answer. No second charge was created; check this payment before trying again.'
                );
            }

            $checkoutUrl = $this->safeCheckoutUrl($intent);
            if (!$checkoutUrl) {
                throw new \RuntimeException('Stripe returned an unsafe checkout URL.');
            }

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout initialization failed.', [
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            if ($reservationToken) {
                (new TicketReservationService())->release($reservationToken, 'payment_init_failed');
                Session::forget('ticket_reservation_token');
            }
            return redirect()->back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    // =========================================================================
    // STEP 2 — Webhook. Every Stripe webhook is signed (Stripe-Signature);
    // the event only prompts a server-side lookup of the session.
    // =========================================================================
    public function webhook(Request $request)
    {
        try {
            $client = new StripeGatewayClient();
        } catch (\Throwable $exception) {
            app(IncidentAlertService::class)->alert(
                'stripe-webhook-credentials',
                'Stripe webhook gateway credentials are unavailable.',
                ['request_id' => $request->attributes->get('request_id')]
            );
            return response()->json(['status' => 'gateway unavailable'], 503);
        }

        $body = $request->getContent();
        $valid = $client->webhookSignatureIsValid($body, (string) $request->header('Stripe-Signature', ''));
        $request->attributes->set('webhook_signature_valid', $valid);
        if (!$valid) {
            Log::error('Stripe webhook: invalid or missing signature');
            return response()->json(['status' => 'invalid signature'], 400);
        }

        $event = json_decode($body, true);
        $type = is_array($event) ? (string) ($event['type'] ?? '') : '';
        if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'], true)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $reference = (string) ($event['data']['object']['client_reference_id'] ?? '');
        $intent = $reference !== '' ? PaymentIntent::where('gateway', 'stripe')->where('reference', $reference)->first() : null;
        if (!$intent || !$intent->payload || !$intent->event_id) {
            Log::warning('Stripe webhook: reference has no local payment intent.', ['reference_hash' => hash('sha256', $reference)]);
            return response()->json(['status' => 'unknown reference - ignored'], 200);
        }

        try {
            $outcome = app(StripePaymentRecoveryService::class)->attempt(
                $intent,
                fn () => $this->processVerifiedPayment('stripe', $intent->reference, $intent->payload),
                $client
            );
            return response()->json(['status' => $outcome['status'] ?? 'accepted'], 200);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed.', [
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    // =========================================================================
    // STEP 3 — The buyer returns from Stripe Checkout
    // =========================================================================
    public function notify(Request $request)
    {
        $sessionId = (string) $request->query('session_id', '');
        $intent = str_starts_with($sessionId, 'cs_')
            ? PaymentIntent::where('gateway', 'stripe')->where('gateway_transaction_id', $sessionId)->first()
            : null;
        if (!$intent) {
            return redirect(eventBookingCancelUrl(0));
        }
        if ($intent->status === 'completed' && $intent->booking_id) {
            return $this->completedRedirect($request, $intent);
        }

        try {
            $outcome = app(StripePaymentRecoveryService::class)->attempt(
                $intent,
                fn () => $this->processVerifiedPayment('stripe', $intent->reference, $intent->payload)
            );
        } catch (\Throwable $exception) {
            Log::error('Stripe success redirect verification unavailable.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))->with(
                'warning',
                'We could not reach Stripe just now. Your payment is not lost and no second charge was created.'
            );
        }

        return $this->recoveryOutcomeRedirect($request, $intent->fresh(), $outcome);
    }

    public function recovery(Request $request, string $reference)
    {
        $intent = PaymentIntent::query()->where('gateway', 'stripe')->where('reference', $reference)->firstOrFail();
        if ($intent->status === 'completed' && $intent->booking_id) {
            return $this->completedRedirect($request, $intent);
        }

        return view('frontend.payment.recovery', [
            'intent' => $intent,
            'providerName' => 'Stripe',
            'authorizationUrl' => $this->safeCheckoutUrl($intent),
            'verifyUrl' => URL::temporarySignedRoute('event_booking.stripe.recovery.verify', now()->addHours(24), ['reference' => $intent->reference]),
        ]);
    }

    public function retryRecovery(Request $request, string $reference, StripePaymentRecoveryService $service)
    {
        $intent = PaymentIntent::query()->where('gateway', 'stripe')->where('reference', $reference)->firstOrFail();

        try {
            $outcome = $service->attempt($intent, fn () => $this->processVerifiedPayment('stripe', $intent->reference, $intent->payload));
        } catch (\Throwable $exception) {
            Log::error('Stripe recovery verification unavailable.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))
                ->with('warning', 'We could not reach Stripe just now. No new charge was created; please try checking again.');
        }

        return $this->recoveryOutcomeRedirect($request, $intent->fresh(), $outcome);
    }

    private function recoveryOutcomeRedirect(Request $request, PaymentIntent $intent, array $outcome)
    {
        if (($outcome['status'] ?? null) === 'completed' && !empty($outcome['booking_id'])) {
            $intent->forceFill(['booking_id' => $outcome['booking_id']])->save();
            return $this->completedRedirect($request, $intent);
        }

        $level = ($outcome['status'] ?? null) === 'failed' ? 'error' : 'warning';
        return redirect($this->recoveryUrl($intent))->with($level, $outcome['message'] ?? 'Your payment is still being confirmed.');
    }

    private function recoveryUrl(PaymentIntent $intent): string
    {
        return URL::temporarySignedRoute('event_booking.stripe.recovery', now()->addHours(24), ['reference' => $intent->reference]);
    }

    private function safeCheckoutUrl(PaymentIntent $intent): ?string
    {
        $url = trim((string) $intent->authorization_url);

        return $url !== '' && StripeGatewayClient::isCheckoutUrl($url) ? $url : null;
    }
}
