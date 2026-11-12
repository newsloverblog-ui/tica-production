<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\PaymentIntent;
use App\Models\ProductPaymentIntent;
use App\Models\Transaction;
use App\Services\AuditLogger;
use App\Services\EventSaleAccountingService;
use App\Services\IncidentAlertService;
use App\Services\PaystackPaymentVerifier;
use App\Services\PaystackPaymentRecoveryService;
use App\Services\PaystackEventCheckoutService;
use App\Services\TicketReservationService;
use App\Services\PaidBookingFulfillmentService;
use App\Services\PaymentRefundService;
use App\Services\PayoutTransferService;
use App\Services\ProductPaystackPaymentService;
use App\Services\ProductGatewayRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class PaystackController extends Controller
{
    private $api_key;
    private $secret_key;

    /**
     * Load gateway credentials only while handling a payment request.
     *
     * Controllers are instantiated by route discovery and route caching. A database
     * query in the constructor made deployment tooling fail whenever the database was
     * unavailable, even though no payment was being processed.
     */
    private function loadGatewayCredentials(): void
    {
        if ($this->api_key && $this->secret_key) {
            return;
        }

        $gateway = OnlineGateway::whereKeyword('paystack')->first();
        $paystackData = $gateway ? json_decode($gateway->information, true) : null;

        if (!is_array($paystackData) || empty($paystackData['key'])) {
            throw new \RuntimeException('Paystack gateway credentials are not configured.');
        }

        $this->api_key = $paystackData['key'];
        $this->secret_key = $paystackData['secret'] ?? $paystackData['key'];
    }

    // =========================================================================
    // STEP 1 — Initialize payment and redirect to Paystack
    // =========================================================================
    public function bookingProcess(Request $request, $eventId)
    {
        $reservationToken = null;
        $request->merge(['gateway' => strtolower(trim((string) $request->input('gateway')))]);
        $rules = [
            'fname'   => 'required',
            'lname'   => 'required',
            'email'   => 'required|email:rfc|max:255',
            'phone'   => 'required|string|max:40',
            'gateway' => 'required|in:paystack',
        ];

        $message = [
            'fname.required'   => 'The first name field is required',
            'lname.required'   => 'The last name field is required',
            'gateway.required' => 'The payment gateway field is required',
        ];

        $request->validate($rules, $message);

        $currencyInfo = $this->getCurrencyInfo();

        $total        = (float) Session::get('grand_total');
        $quantity     = Session::get('quantity');
        $discount     = Session::get('discount');
        $basicSetting = Basic::select('commission')->first();

        $tax_amount               = (float) Session::get('tax');
        $commission_amount        = (float) Session::get('commission', 0);
        $total_early_bird_dicount = Session::get('total_early_bird_dicount');

        // Each event charges in its own currency (the base currency by default).
        $chargeCurrency = \App\Support\TicaCurrency::normalize(\App\Models\Event::query()->whereKey((int) $eventId)->value('currency'));
        if (!in_array($chargeCurrency, \App\Support\TicaCurrency::PAYSTACK, true)) {
            return redirect()->back()
                ->with('currency_error', 'Invalid currency for Paystack payment.')
                ->withInput();
        }
        $chargeSymbol = \App\Support\TicaCurrency::symbol($chargeCurrency);

        if ($total <= 0 || $tax_amount < 0 || $tax_amount > $total) {
            return redirect()->back()->with('error', 'The payment total is invalid.')->withInput();
        }

        try {
            $selectedTickets = Session::get('selTickets') ?: [];
            $reservationToken = (new TicketReservationService())->replace(
                (int) $eventId,
                $selectedTickets,
                Session::get('ticket_reservation_token'),
                $request->session()->getId(),
                auth('customer')->id(),
                app(\App\Services\EventTicketPurchaseLimitService::class)
                    ->buyerIdentityHash($request->email),
            );
            Session::put('ticket_reservation_token', $reservationToken);

            $sub_total_price = $total - $tax_amount;

            $arrData = [
                'event_id'                 => $eventId,
                'price'                    => $sub_total_price,
                'tax'                      => $tax_amount,
                'commission'               => $commission_amount,
                'quantity'                 => $quantity,
                'discount'                 => $discount,
                'total_early_bird_dicount' => $total_early_bird_dicount,
                'currencyText'             => $chargeCurrency,
                'currencyTextPosition'     => $currencyInfo->base_currency_text_position,
                'currencySymbol'           => $chargeSymbol,
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
                'paymentMethod'            => 'Paystack',
                'gatewayType'              => 'online',
                'paymentStatus'            => 'completed',
                'event_date'               => Session::get('event_date'),
                'selected_tickets'          => Session::get('selTickets'),
                'reservation_token'         => $reservationToken,
                'customer_id'               => auth('customer')->id(),
                'attendees'                 => Session::get('tica_attendees'),
                'coupon_id'                 => Session::get('event_coupon_id'),
                'tracking_link_id'          => Session::get('tica_tracking_link_id'),
            ];

            $intent = app(PaystackEventCheckoutService::class)->initialize([
                'event_id' => (int) $eventId,
                'customer_id' => auth('customer')->id(),
                'amount' => $total,
                'currency' => $chargeCurrency,
                'email' => $request->email,
                'payload' => $arrData,
            ]);

            if ($intent->status === 'verification_required') {
                return redirect($this->recoveryUrl($intent))->with(
                    'warning',
                    'Paystack did not return a final initialization response. No second charge was created; check this payment status before trying again.'
                );
            }

            $authorizationUrl = $this->safeAuthorizationUrl($intent);
            if (!$authorizationUrl) {
                throw new \RuntimeException('Paystack returned an unsafe checkout URL.');
            }

            return redirect()->away($authorizationUrl);

        } catch (\Throwable $e) {
            Log::error('Paystack checkout initialization failed.', [
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
    // STEP 2 — Webhook (called directly by Paystack servers after payment)
    // =========================================================================
    public function webhook(Request $request)
    {
        try {
            $this->loadGatewayCredentials();
        } catch (\RuntimeException $exception) {
            app(IncidentAlertService::class)->alert(
                'paystack-webhook-credentials',
                'Paystack webhook gateway credentials are unavailable.', [
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return response()->json(['status' => 'gateway unavailable'], 503);
        }

        $input     = $request->getContent();
        $signature = $request->header('x-paystack-signature');
        $expectedSignature = hash_hmac('sha512', $input, $this->secret_key);

        if (!$signature || !hash_equals($expectedSignature, (string) $signature)) {
            $request->attributes->set('webhook_signature_valid', false);
            Log::error('Webhook: Invalid signature');
            return response()->json(['status' => 'invalid signature'], 400);
        }
        $request->attributes->set('webhook_signature_valid', true);

        $event = json_decode($input);

        if (!$event) {
            Log::error('Webhook: Invalid JSON payload');
            return response()->json(['status' => 'invalid payload'], 400);
        }

        $eventName = is_string($event->event ?? null) ? $event->event : '';
        if ($eventName === '') {
            Log::error('Webhook: Event name is missing');
            return response()->json(['status' => 'invalid payload'], 400);
        }

        Log::info('Webhook received: ' . $eventName);

        if (str_starts_with($eventName, 'transfer.')) {
            $transfer = (new PayoutTransferService())->handleWebhook(
                $eventName,
                json_decode(json_encode($event->data ?? []), true)
            );
            if (!$transfer) {
                Log::warning('Webhook: Transfer event did not match a controlled payout.', [
                    'event' => $eventName,
                ]);
            }
            return response()->json(['status' => 'accepted'], 200);
        }

        if (str_starts_with($eventName, 'refund.')) {
            $refund = (new PaymentRefundService())->handleWebhook(
                $eventName,
                json_decode(json_encode($event->data ?? []), true)
            );
            if (!$refund) {
                $refund = app(ProductGatewayRefundService::class)->handleWebhook(
                    $eventName,
                    json_decode(json_encode($event->data ?? []), true)
                );
                if (!$refund) {
                    Log::warning('Webhook: Refund event did not match a local controlled refund', [
                        'event' => $eventName,
                    ]);
                }
            }
            return response()->json(['status' => 'accepted'], 200);
        }

        if ($eventName !== 'charge.success') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $reference = $event->data->reference ?? null;
        if (!$reference) {
            Log::error('Webhook: No reference in payload');
            return response()->json(['status' => 'no reference'], 400);
        }

        Log::info('Webhook: Handling reference: ' . $reference);

        $productIntent = ProductPaymentIntent::query()
            ->where('gateway', 'paystack')
            ->where('reference', $reference)
            ->first();
        if ($productIntent) {
            try {
                $result = app(ProductPaystackPaymentService::class)->acceptWebhook(
                    json_decode(json_encode($event->data), true)
                );
                return response()->json(['status' => $result['status'] ?? 'accepted'], 200);
            } catch (\Throwable $exception) {
                Log::error('Product Paystack webhook processing failed.', [
                    'reference' => $reference,
                    'exception_type' => $exception::class,
                    'exception_code' => $exception->getCode(),
                    'request_id' => $request->attributes->get('request_id'),
                ]);
                return response()->json(['status' => 'error'], 500);
            }
        }

        $intent = PaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->first();
        if (!$intent || !$intent->payload || !$intent->event_id) {
            Log::warning('Webhook: Reference has no durable local payment intent.', ['reference' => $reference]);
            return response()->json(['status' => 'unknown reference - ignored'], 200);
        }
        $arrData = $intent->payload;
        $eventId = $intent->event_id;

        $verificationErrors = PaystackPaymentVerifier::errors(
            json_decode(json_encode($event->data), true),
            $arrData
        );
        if ($verificationErrors) {
            app(IncidentAlertService::class)->alert(
                'paystack-webhook-verification:' . optional($intent)->id,
                'Paystack webhook payment verification mismatch.', [
                'reference' => $reference,
                'errors' => $verificationErrors,
                'request_id' => $request->attributes->get('request_id'),
            ]);
            if ($intent) {
                $intent->update(['status' => 'failed', 'last_error' => implode(',', $verificationErrors)]);
            }
            return response()->json(['status' => 'payment verification mismatch'], 422);
        }

        try {
            $result = $this->processPayment($reference, $arrData);

            if ($result['status'] === 'duplicate') {
                return response()->json(['status' => 'being processed'], 200);
            }
            if ($result['status'] === 'already_processed') {
                return response()->json(['status' => 'already processed'], 200);
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Paystack webhook processing failed.', [
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    // =========================================================================
    // STEP 3 — notify() — Browser redirect back after Paystack payment page
    // =========================================================================
    public function notify(Request $request)
    {
        $reference = $request->input('reference') ?? $request->input('trxref') ?? null;
        if (!$reference) {
            return redirect(eventBookingCancelUrl(0));
        }

        $intent = PaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->first();
        if (!$intent) {
            Log::warning('Paystack browser callback used an unknown reference.', [
                'reference_hash' => hash('sha256', (string) $reference),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect(eventBookingCancelUrl(0));
        }
        if ($intent->status === 'completed' && $intent->booking_id) {
            return $this->completedRedirect($request, $intent);
        }

        try {
            $outcome = app(PaystackPaymentRecoveryService::class)->attempt(
                $intent,
                fn () => $this->processPayment($intent->reference, $intent->payload)
            );
        } catch (\Throwable $exception) {
            Log::error('Paystack browser callback verification unavailable.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))->with(
                'warning',
                'We could not reach Paystack just now. Your payment is not lost and no second charge was created.'
            );
        }

        return $this->recoveryOutcomeRedirect($request, $intent->fresh(), $outcome);
    }

    public function recovery(Request $request, string $reference)
    {
        $intent = PaymentIntent::query()
            ->where('gateway', 'paystack')
            ->where('reference', $reference)
            ->firstOrFail();

        if ($intent->status === 'completed' && $intent->booking_id) {
            return $this->completedRedirect($request, $intent);
        }

        return view('frontend.payment.recovery', [
            'intent' => $intent,
            'providerName' => 'Paystack',
            'authorizationUrl' => $this->safeAuthorizationUrl($intent),
            'verifyUrl' => URL::temporarySignedRoute(
                'event_booking.paystack.recovery.verify',
                now()->addHours(24),
                ['reference' => $intent->reference]
            ),
        ]);
    }

    public function retryRecovery(Request $request, string $reference, PaystackPaymentRecoveryService $service)
    {
        $intent = PaymentIntent::query()
            ->where('gateway', 'paystack')
            ->where('reference', $reference)
            ->firstOrFail();

        try {
            $outcome = $service->attempt(
                $intent,
                fn () => $this->processPayment($intent->reference, $intent->payload)
            );
        } catch (\Throwable $exception) {
            Log::error('Paystack recovery verification unavailable.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))
                ->with('warning', 'We could not reach Paystack just now. No new charge was created; please try checking again.');
        }

        return $this->recoveryOutcomeRedirect($request, $intent->fresh(), $outcome);
    }


    // =========================================================================
    // CORE PAYMENT PROCESSOR — shared by webhook and verified recovery
    // =========================================================================
    private function processPayment($reference, $arrData)
    {
        $lockName = 'paystack_ref_' . hash('sha256', (string) $reference);

        $lockResult = DB::select("SELECT GET_LOCK(?, 0) AS acquired", [$lockName]);
        $acquired   = $lockResult[0]->acquired ?? 0;

        if (!$acquired) {
            Log::warning('processPayment: MySQL lock already held — duplicate blocked for: ' . $reference);
            return ['status' => 'duplicate', 'bookingInfo' => null];
        }

        Log::info('processPayment: MySQL lock acquired for: ' . $reference);

        try {
            $intent = PaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->first();
            $existingRow = Transaction::query()
                ->where('gateway', 'paystack')
                ->where('gateway_reference', $reference)
                ->first();
            if ($existingRow) {
                Log::info('processPayment: Already in DB (raw check) — skipping: ' . $reference);
                if ($intent && $intent->status !== 'completed') {
                    $intent->update([
                        'status' => 'completed',
                        'booking_id' => $existingRow->booking_id,
                        'processed_at' => now(),
                        'last_error' => null,
                    ]);
                }
                return ['status' => 'already_processed', 'bookingInfo' => null, 'existingTxn' => $existingRow];
            }

            if (!$intent) {
                throw new \LogicException('A durable Paystack payment intent is required before fulfillment.');
            }

            if (!empty($arrData['event_date'])) {
                Session::put('event_date', $arrData['event_date']);
                Log::info('processPayment: Restored event_date: ' . $arrData['event_date']);
            }

            $bookingInfo = null;

            DB::transaction(function () use ($reference, $arrData, &$bookingInfo) {

                $intent = PaymentIntent::where('gateway', 'paystack')
                    ->where('reference', $reference)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($intent->status === 'completed') {
                    return;
                }

                $intent->update(['status' => 'processing', 'last_error' => null]);

                $alreadyStored = Transaction::where('gateway', 'paystack')
                    ->where('gateway_reference', $reference)
                    ->lockForUpdate()
                    ->first();

                if ($alreadyStored) {
                    Log::warning('processPayment DB Guard: Duplicate detected inside lock for: ' . $reference);
                    return;
                }

                $enrol       = new BookingController();
                $bookingInfo = $enrol->storeData($arrData);

                if (!$bookingInfo) {
                    throw new \Exception('storeData() returned null for reference: ' . $reference);
                }

                Log::info('processPayment: Booking created: ' . $bookingInfo->booking_id);

                $bookingInfo->paymentStatus    = 1;
                $bookingInfo->transcation_type = 1;
                $bookingInfo->transcation_id   = $reference;
                app(EventSaleAccountingService::class)->apply($bookingInfo, 'paystack', $reference);
                Log::info('processPayment: Ledger and balances applied: ' . $reference);

                $intent->update([
                    'status' => 'completed',
                    'booking_id' => $bookingInfo->id,
                    'processed_at' => now(),
                    'last_error' => null,
                ]);
                AuditLogger::record('payment.completed', $intent, ['status' => 'processing'], ['status' => 'completed'], [
                    'gateway' => 'paystack',
                    'reference' => $reference,
                    'booking_id' => $bookingInfo->id,
                    'amount' => (float) $intent->amount,
                    'currency' => $intent->currency,
                ]);
            });

            if (!$bookingInfo) {
                $existingTxn = Transaction::where('gateway', 'paystack')
                    ->where('gateway_reference', $reference)
                    ->first();
                return ['status' => 'already_processed', 'bookingInfo' => null, 'existingTxn' => $existingTxn];
            }

            // Release the request-scoped event date before asynchronous fulfilment.
            Session::forget('event_date');

            DB::select("SELECT RELEASE_LOCK(?)", [$lockName]);
            Log::info('processPayment: MySQL lock released for: ' . $reference);

            Log::info('processPayment: Completed successfully for: ' . $reference);

            $bookingInfo = $bookingInfo->fresh();
            app(PaidBookingFulfillmentService::class)->dispatch($bookingInfo->id);

            return ['status' => 'success', 'bookingInfo' => $bookingInfo];

        } catch (\Exception $e) {
            Log::error('Paystack payment processing failed.', [
                'reference' => $reference,
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
            ]);
            $failedIntent = PaymentIntent::where('gateway', 'paystack')
                ->where('reference', $reference)
                ->where('status', '!=', 'completed')
                ->first();
            if ($failedIntent) {
                $failedIntent->update([
                    'status' => 'failed',
                    'last_error' => 'Payment processing failed; inspect the correlated exception report.',
                ]);
            }
            throw $e;

        } finally {
            // Safety net — no-op if lock already released above
            DB::select("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    private function recoveryOutcomeRedirect(Request $request, PaymentIntent $intent, array $outcome)
    {
        if (($outcome['status'] ?? null) === 'completed' && !empty($outcome['booking_id'])) {
            $intent->forceFill(['booking_id' => $outcome['booking_id']])->save();
            return $this->completedRedirect($request, $intent);
        }

        $level = ($outcome['status'] ?? null) === 'failed' ? 'error' : 'warning';
        return redirect($this->recoveryUrl($intent))
            ->with($level, $outcome['message'] ?? 'Your payment is still being confirmed.');
    }

    private function completedRedirect(Request $request, PaymentIntent $intent)
    {
        $this->clearSession($request);
        return redirect(app(\App\Services\BookingCompletionUrlService::class)->make(
            (int) $intent->event_id,
            (int) $intent->booking_id
        ));
    }

    private function recoveryUrl(PaymentIntent $intent): string
    {
        return URL::temporarySignedRoute(
            'event_booking.paystack.recovery',
            now()->addHours(24),
            ['reference' => $intent->reference]
        );
    }

    private function safeAuthorizationUrl(PaymentIntent $intent): ?string
    {
        $url = trim((string) $intent->authorization_url);
        if ($url === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $scheme === 'https' && $host === 'checkout.paystack.com' ? $url : null;
    }

    // =========================================================================
    // Clear session after payment completes
    // =========================================================================
    private function clearSession($request)
    {
        $request->session()->forget([
            'eventId',
            'arrData',
            'discount',
            'selTickets',
            'paymentId',
            'event_date',
        ]);
    }
}
