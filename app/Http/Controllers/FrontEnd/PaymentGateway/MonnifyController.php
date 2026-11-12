<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentIntent;
use App\Services\IncidentAlertService;
use App\Services\MonnifyEventCheckoutService;
use App\Services\MonnifyGatewayClient;
use App\Services\MonnifyPaymentRecoveryService;
use App\Services\TicketReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

/**
 * Event ticket payments through Monnify's hosted checkout. Tickets are only
 * issued after Monnify's own API confirms a full naira payment: the browser
 * return, the webhook and the recovery page all ask Monnify first.
 */
class MonnifyController extends Controller
{
    use Concerns\FulfilsVerifiedPayment;

    // =========================================================================
    // STEP 1 — Create the checkout and send the buyer to Monnify
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
            'gateway' => 'required|in:monnify',
        ], [
            'fname.required'   => 'The first name field is required',
            'lname.required'   => 'The last name field is required',
            'gateway.required' => 'The payment gateway field is required',
        ]);

        $total      = (float) Session::get('grand_total');
        $tax_amount = (float) Session::get('tax');

        // Monnify charges in naira only.
        $chargeCurrency = \App\Support\TicaCurrency::normalize(\App\Models\Event::query()->whereKey((int) $eventId)->value('currency'));
        if ($chargeCurrency !== 'NGN') {
            return redirect()->back()
                ->with('currency_error', 'Monnify only takes payments in naira. Choose Paystack for this event.')
                ->withInput();
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
                'paymentMethod'            => 'Monnify',
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

            $intent = app(MonnifyEventCheckoutService::class)->initialize([
                'event_id' => (int) $eventId,
                'customer_id' => auth('customer')->id(),
                'amount' => $total,
                'currency' => $chargeCurrency,
                'email' => $request->email,
                'description' => 'TICA tickets',
                'payload' => $arrData,
            ]);

            if ($intent->status === 'verification_required') {
                return redirect($this->recoveryUrl($intent))->with(
                    'warning',
                    'Monnify did not return a final answer. No second charge was created; check this payment before trying again.'
                );
            }

            $checkoutUrl = $this->safeCheckoutUrl($intent);
            if (!$checkoutUrl) {
                throw new \RuntimeException('Monnify returned an unsafe checkout URL.');
            }

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Monnify checkout initialization failed.', [
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
    // STEP 2 — Webhook. Monnify signs production notifications with
    // monnify-signature (HMAC-SHA512 of the raw body with the secret key).
    // Either way the notification only prompts a server-side lookup.
    // =========================================================================
    public function webhook(Request $request)
    {
        try {
            $client = new MonnifyGatewayClient();
        } catch (\Throwable $exception) {
            app(IncidentAlertService::class)->alert(
                'monnify-webhook-credentials',
                'Monnify webhook gateway credentials are unavailable.',
                ['request_id' => $request->attributes->get('request_id')]
            );
            return response()->json(['status' => 'gateway unavailable'], 503);
        }

        $body = $request->getContent();
        $signature = (string) $request->header('monnify-signature', '');
        if ($signature !== '') {
            $valid = hash_equals(hash_hmac('sha512', $body, $client->secretKey()), $signature);
            $request->attributes->set('webhook_signature_valid', $valid);
            if (!$valid) {
                Log::error('Monnify webhook: invalid signature');
                return response()->json(['status' => 'invalid signature'], 400);
            }
        } elseif (!$client->isSandbox()) {
            // Live notifications always carry a signature.
            $request->attributes->set('webhook_signature_valid', false);
            return response()->json(['status' => 'missing signature'], 400);
        }

        $event = json_decode($body, true);
        if (!is_array($event) || !is_string($event['eventType'] ?? null)) {
            return response()->json(['status' => 'invalid payload'], 400);
        }
        if ($event['eventType'] !== 'SUCCESSFUL_TRANSACTION') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $reference = (string) ($event['eventData']['paymentReference'] ?? '');
        $intent = $reference !== '' ? PaymentIntent::where('gateway', 'monnify')->where('reference', $reference)->first() : null;
        if (!$intent || !$intent->payload || !$intent->event_id) {
            Log::warning('Monnify webhook: reference has no local payment intent.', ['reference_hash' => hash('sha256', $reference)]);
            return response()->json(['status' => 'unknown reference - ignored'], 200);
        }

        try {
            $outcome = app(MonnifyPaymentRecoveryService::class)->attempt(
                $intent,
                fn () => $this->processVerifiedPayment('monnify', $intent->reference, $intent->payload),
                $client
            );
            return response()->json(['status' => $outcome['status'] ?? 'accepted'], 200);
        } catch (\Throwable $e) {
            Log::error('Monnify webhook processing failed.', [
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    // =========================================================================
    // STEP 3 — The buyer returns from Monnify's checkout
    // =========================================================================
    public function notify(Request $request)
    {
        $reference = (string) $request->query('paymentReference', '');
        $intent = $reference !== '' ? PaymentIntent::where('gateway', 'monnify')->where('reference', $reference)->first() : null;
        if (!$intent) {
            return redirect(eventBookingCancelUrl(0));
        }
        if ($intent->status === 'completed' && $intent->booking_id) {
            return $this->completedRedirect($request, $intent);
        }

        try {
            $outcome = app(MonnifyPaymentRecoveryService::class)->attempt(
                $intent,
                fn () => $this->processVerifiedPayment('monnify', $intent->reference, $intent->payload)
            );
        } catch (\Throwable $exception) {
            Log::error('Monnify browser callback verification unavailable.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))->with(
                'warning',
                'We could not reach Monnify just now. Your payment is not lost and no second charge was created.'
            );
        }

        return $this->recoveryOutcomeRedirect($request, $intent->fresh(), $outcome);
    }

    public function recovery(Request $request, string $reference)
    {
        $intent = PaymentIntent::query()->where('gateway', 'monnify')->where('reference', $reference)->firstOrFail();
        if ($intent->status === 'completed' && $intent->booking_id) {
            return $this->completedRedirect($request, $intent);
        }

        return view('frontend.payment.recovery', [
            'intent' => $intent,
            'providerName' => 'Monnify',
            'authorizationUrl' => $this->safeCheckoutUrl($intent),
            'verifyUrl' => URL::temporarySignedRoute('event_booking.monnify.recovery.verify', now()->addHours(24), ['reference' => $intent->reference]),
        ]);
    }

    public function retryRecovery(Request $request, string $reference, MonnifyPaymentRecoveryService $service)
    {
        $intent = PaymentIntent::query()->where('gateway', 'monnify')->where('reference', $reference)->firstOrFail();

        try {
            $outcome = $service->attempt($intent, fn () => $this->processVerifiedPayment('monnify', $intent->reference, $intent->payload));
        } catch (\Throwable $exception) {
            Log::error('Monnify recovery verification unavailable.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))
                ->with('warning', 'We could not reach Monnify just now. No new charge was created; please try checking again.');
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
        return URL::temporarySignedRoute('event_booking.monnify.recovery', now()->addHours(24), ['reference' => $intent->reference]);
    }

    private function safeCheckoutUrl(PaymentIntent $intent): ?string
    {
        $url = trim((string) $intent->authorization_url);

        return $url !== '' && MonnifyGatewayClient::isCheckoutUrl($url) ? $url : null;
    }
}
