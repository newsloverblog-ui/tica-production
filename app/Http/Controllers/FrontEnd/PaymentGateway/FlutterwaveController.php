<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Services\AuditLogger;
use App\Services\EventSaleAccountingService;
use App\Services\FlutterwaveGatewayClient;
use App\Services\FlutterwavePaymentVerifier;
use App\Services\PaymentRefundService;
use App\Services\IncidentAlertService;
use App\Services\TicketReservationService;
use App\Services\PaidBookingFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class FlutterwaveController extends Controller
{
    private ?string $secretKey = null;
    private ?string $secretHash = null;

    private function loadGatewayCredentials(): void
    {
        if ($this->secretKey !== null) {
            return;
        }

        $gateway = OnlineGateway::whereKeyword('flutterwave')->first();
        $information = $gateway ? (json_decode($gateway->information, true) ?: []) : [];
        $this->secretKey = (string) ($information['secret_key'] ?? '');
        $this->secretHash = (string) ($information['secret_hash'] ?? config('services.flutterwave.secret_hash', ''));

        if ($this->secretKey === '') {
            throw new RuntimeException('Flutterwave gateway credentials are not configured.');
        }
    }

    public function bookingProcess(Request $request, $eventId)
    {
        $reservationToken = null;
        $intent = null;
        $request->merge(['gateway' => strtolower(trim((string) $request->input('gateway')))]);
        $request->validate([
            'fname' => 'required|string|max:100',
            'lname' => 'required|string|max:100',
            'email' => 'required|email|max:191',
            'phone' => 'required|string|max:40',
            'gateway' => 'required|in:flutterwave',
        ]);

        $currencyInfo = $this->getCurrencyInfo();
        $allowedCurrencies = [
            'BIF', 'CAD', 'CDF', 'CVE', 'EUR', 'GBP', 'GHS', 'GMD', 'GNF', 'KES',
            'LRD', 'MWK', 'MZN', 'NGN', 'RWF', 'SLL', 'STD', 'TZS', 'UGX', 'USD',
            'XAF', 'XOF', 'ZMK', 'ZMW', 'ZWD',
        ];
        $currency = strtoupper((string) $currencyInfo->base_currency_text);
        if (!in_array($currency, $allowedCurrencies, true)) {
            return redirect()->back()->with('currency_error', 'Invalid currency for Flutterwave payment.')->withInput();
        }

        try {
            $this->loadGatewayCredentials();
            $total = (float) Session::get('grand_total');
            $tax = (float) Session::get('tax');
            if ($total <= 0 || $tax < 0 || $tax > $total) {
                throw new RuntimeException('The payment total is invalid.');
            }

            $basic = Basic::select('commission')->firstOrFail();
            $reservationToken = (new TicketReservationService())->replace(
                (int) $eventId,
                Session::get('selTickets') ?: [],
                Session::get('ticket_reservation_token'),
                $request->session()->getId(),
                auth('customer')->id(),
                app(\App\Services\EventTicketPurchaseLimitService::class)
                    ->buyerIdentityHash($request->email),
            );
            Session::put('ticket_reservation_token', $reservationToken);

            $reference = 'tica-flw-' . Str::uuid();
            $arrData = [
                'event_id' => (int) $eventId,
                'price' => $total - $tax,
                'tax' => $tax,
                'commission' => (float) Session::get('commission', 0),
                'quantity' => Session::get('quantity'),
                'discount' => Session::get('discount'),
                'total_early_bird_dicount' => Session::get('total_early_bird_dicount'),
                'currencyText' => $currency,
                'currencyTextPosition' => $currencyInfo->base_currency_text_position,
                'currencySymbol' => $currencyInfo->base_currency_symbol,
                'currencySymbolPosition' => $currencyInfo->base_currency_symbol_position,
                'fname' => $request->fname,
                'lname' => $request->lname,
                'email' => $request->email,
                'phone' => $request->phone,
                'country' => $request->country,
                'state' => $request->state,
                'city' => $request->city,
                'zip_code' => $request->zip_code,
                'address' => $request->address,
                'paymentMethod' => 'Flutterwave',
                'gatewayType' => 'online',
                'paymentStatus' => 'completed',
                'event_date' => Session::get('event_date'),
                'selected_tickets' => Session::get('selTickets'),
                'reservation_token' => $reservationToken,
                'customer_id' => auth('customer')->id(),
            ];

            $intent = PaymentIntent::create([
                'gateway' => 'flutterwave',
                'reference' => $reference,
                'event_id' => $eventId,
                'customer_id' => auth('customer')->id(),
                'status' => 'initializing',
                'amount' => $total,
                'currency' => $currency,
                'email' => $request->email,
                'payload' => $arrData,
                'expires_at' => now()->addDay(),
            ]);
            AuditLogger::record('payment.created', $intent, [], [
                'status' => 'initializing',
                'amount' => $total,
                'currency' => $currency,
            ], [
                'gateway' => 'flutterwave',
                'reference' => $reference,
                'event_id' => (int) $eventId,
            ]);

            $response = (new FlutterwaveGatewayClient($this->secretKey))->initialize([
                'tx_ref' => $reference,
                'amount' => number_format($total, 2, '.', ''),
                'currency' => $currency,
                'redirect_url' => route('event_booking.flutterwave.notify'),
                'customer' => [
                    'email' => $request->email,
                    'name' => trim($request->fname . ' ' . $request->lname),
                    'phonenumber' => $request->phone,
                ],
                'customizations' => [
                    'title' => 'Tica event tickets',
                    'description' => 'Secure event ticket checkout',
                ],
            ]);

            $authorizationUrl = (string) $response['data']['link'];
            $intent->update([
                'status' => 'initialized',
                'authorization_url' => $authorizationUrl,
                'last_error' => null,
            ]);

            return redirect()->away($authorizationUrl);
        } catch (GatewayOutcomeUnknownException $exception) {
            Log::warning('Flutterwave initialization outcome is unknown.', [
                'event_id' => $eventId,
                'payment_intent_id' => $intent?->id,
                'request_id' => $request->attributes->get('request_id'),
            ]);
            if ($intent) {
                $intent->update([
                    'status' => 'verification_required',
                    'last_error' => 'Initialization outcome unknown; provider verification is required.',
                ]);
                app(IncidentAlertService::class)->alert(
                    'flutterwave-event-init-unknown:' . $intent->id,
                    'Event Flutterwave initialization has an unknown outcome.',
                    ['reference' => $intent->reference, 'event_id' => (int) $eventId]
                );
                return redirect($this->recoveryUrl($intent))->with(
                    'warning',
                    'Flutterwave did not return a final initialization response. No second charge was created; check this payment status before trying again.'
                );
            }
            return redirect()->back()->with('error', 'Flutterwave checkout could not be started.')->withInput();
        } catch (\Throwable $exception) {
            Log::error('Flutterwave initialization failed.', [
                'event_id' => $eventId,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            if ($intent) {
                $intent->update([
                    'status' => 'failed',
                    'last_error' => 'Initialization failed before a checkout redirect was issued.',
                ]);
                AuditLogger::record('payment.initialization_failed', $intent, [], ['status' => 'failed'], [
                    'gateway' => 'flutterwave',
                    'reference' => $intent->reference,
                    'event_id' => (int) $eventId,
                ]);
            }
            if ($reservationToken) {
                (new TicketReservationService())->release($reservationToken, 'payment_init_failed');
                Session::forget('ticket_reservation_token');
            }
            return redirect()->back()->with('error', 'Flutterwave checkout could not be started. No charge was created.')->withInput();
        }
    }

    public function notify(Request $request)
    {
        $reference = (string) $request->query('tx_ref', '');
        $intent = PaymentIntent::where('gateway', 'flutterwave')->where('reference', $reference)->first();
        if (!$intent) {
            return redirect()->route('check-out')->with('error', 'We could not identify that Flutterwave payment attempt.');
        }

        $transactionId = (int) $request->query('transaction_id', 0);
        try {
            $bookingId = $this->verifyAndFulfill($intent, $transactionId);
            $this->clearSession($request);
            return redirect(app(\App\Services\BookingCompletionUrlService::class)->make(
                (int) $intent->event_id,
                (int) $bookingId
            ));
        } catch (\Throwable $exception) {
            Log::error('Flutterwave callback verification failed.', [
                'reference' => $reference,
                'transaction_id' => $transactionId,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            return redirect($this->recoveryUrl($intent))
                ->with('warning', 'Your payment has not been confirmed yet. Do not pay again; check the same reference here.');
        }
    }

    public function recovery(Request $request, string $reference)
    {
        $intent = PaymentIntent::where('gateway', 'flutterwave')
            ->where('reference', $reference)
            ->firstOrFail();

        if ($intent->status === 'completed' && $intent->booking_id) {
            $this->clearSession($request);
            return redirect(app(\App\Services\BookingCompletionUrlService::class)->make(
                (int) $intent->event_id,
                (int) $intent->booking_id
            ));
        }

        return view('frontend.payment.recovery', [
            'intent' => $intent,
            'providerName' => 'Flutterwave',
            'authorizationUrl' => $this->safeAuthorizationUrl($intent),
            'verifyUrl' => URL::temporarySignedRoute(
                'event_booking.flutterwave.recovery.verify',
                now()->addHours(24),
                ['reference' => $intent->reference]
            ),
        ]);
    }

    public function retryRecovery(Request $request, string $reference)
    {
        $intent = PaymentIntent::where('gateway', 'flutterwave')
            ->where('reference', $reference)
            ->firstOrFail();

        try {
            $bookingId = $this->verifyAndFulfill($intent, 0);
            $this->clearSession($request);
            return redirect(app(\App\Services\BookingCompletionUrlService::class)->make(
                (int) $intent->event_id,
                (int) $bookingId
            ));
        } catch (\Throwable $exception) {
            Log::error('Flutterwave recovery verification failed.', [
                'reference' => $intent->reference,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            $intent->refresh();
            $message = $intent->status === 'failed'
                ? 'Flutterwave did not complete this transaction. You can safely start a new checkout.'
                : 'Flutterwave has not confirmed this payment yet. No second charge was created.';
            return redirect($this->recoveryUrl($intent))->with(
                $intent->status === 'failed' ? 'error' : 'warning',
                $message
            );
        }
    }

    public function webhook(Request $request)
    {
        try {
            $this->loadGatewayCredentials();
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'gateway unavailable'], 503);
        }

        $signature = (string) $request->header('verif-hash', '');
        if ($this->secretHash === '' || $signature === '' || !hash_equals($this->secretHash, $signature)) {
            $request->attributes->set('webhook_signature_valid', false);
            Log::warning('Flutterwave webhook rejected: invalid signature.');
            return response()->json(['status' => 'invalid signature'], 401);
        }
        $request->attributes->set('webhook_signature_valid', true);

        $eventName = strtolower((string) $request->input('event', ''));
        if (str_contains($eventName, 'refund')) {
            $refund = app(PaymentRefundService::class)->handleFlutterwaveWebhook((array) $request->input('data', []));
            return response()->json(['status' => $refund ? 'accepted' : 'ignored']);
        }

        if ($eventName !== 'charge.completed') {
            return response()->json(['status' => 'ignored']);
        }

        $reference = (string) $request->input('data.tx_ref', '');
        $transactionId = (int) $request->input('data.id', 0);
        $intent = PaymentIntent::where('gateway', 'flutterwave')->where('reference', $reference)->first();
        if (!$intent) {
            Log::warning('Flutterwave webhook has no local intent.', ['reference' => $reference]);
            return response()->json(['status' => 'ignored']);
        }

        try {
            $this->verifyAndFulfill($intent, $transactionId);
            return response()->json(['status' => 'accepted']);
        } catch (\Throwable $exception) {
            Log::error('Flutterwave webhook processing failed.', [
                'reference' => $reference,
                'transaction_id' => $transactionId,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
            return response()->json(['status' => 'retry'], 500);
        }
    }

    private function verifyAndFulfill(PaymentIntent $intent, int $transactionId): int
    {
        $intent->refresh();
        if ($intent->status === 'completed' && $intent->booking_id) {
            return (int) $intent->booking_id;
        }

        $this->loadGatewayCredentials();
        $intent->increment('verification_attempts');
        $client = new FlutterwaveGatewayClient($this->secretKey);
        $response = $transactionId > 0
            ? $client->verifyTransaction($transactionId)
            : $client->verifyByReference($intent->reference);
        $payment = $response['data'];
        $intent->forceFill([
            'gateway_transaction_id' => isset($payment['id']) ? (string) $payment['id'] : $intent->gateway_transaction_id,
            'last_verified_at' => now(),
        ])->save();

        $gatewayStatus = strtolower(trim((string) ($payment['status'] ?? 'unknown')));
        if ($gatewayStatus !== 'successful') {
            if (in_array($gatewayStatus, ['failed', 'cancelled', 'reversed'], true)) {
                $this->failIntent($intent, 'Flutterwave reported the transaction as ' . $gatewayStatus . '.');
                throw new RuntimeException('Flutterwave did not complete this transaction.');
            }

            $intent->update([
                'status' => 'verification_required',
                'last_error' => 'Flutterwave transaction remains ' . $gatewayStatus . '.',
            ]);
            throw new RuntimeException('Flutterwave has not reported a terminal transaction status.');
        }

        $errors = FlutterwavePaymentVerifier::errors($payment, $intent);
        if ($errors) {
            $message = 'Flutterwave verification mismatch: ' . implode(', ', $errors);
            $intent->update(['status' => 'failed', 'last_error' => $message]);
            AuditLogger::record('payment.verification_mismatch', $intent, [], ['status' => 'failed'], [
                'gateway' => 'flutterwave',
                'reference' => $intent->reference,
                'errors' => $errors,
            ]);
            app(IncidentAlertService::class)->alert(
                'flutterwave-verification-mismatch:' . $intent->id,
                'Flutterwave payment verification mismatch.', [
                'reference' => $intent->reference,
                'errors' => $errors,
            ]);
            throw new RuntimeException('Flutterwave payment verification did not match the checkout.');
        }

        return $this->processPayment($intent);
    }

    private function processPayment(PaymentIntent $intent): int
    {
        $lockName = 'flutterwave_ref_' . hash('sha256', $intent->reference);
        $lock = DB::select('SELECT GET_LOCK(?, 5) AS acquired', [$lockName]);
        if (!(int) ($lock[0]->acquired ?? 0)) {
            throw new RuntimeException('The Flutterwave payment is already being processed.');
        }

        try {
            $existing = Transaction::where('gateway', 'flutterwave')
                ->where('gateway_reference', $intent->reference)
                ->first();
            if ($existing) {
                $intent->update([
                    'status' => 'completed',
                    'booking_id' => $existing->booking_id,
                    'processed_at' => $intent->processed_at ?: now(),
                    'last_error' => null,
                ]);
                return (int) $existing->booking_id;
            }

            $booking = null;
            DB::transaction(function () use ($intent, &$booking) {
                $lockedIntent = PaymentIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
                if ($lockedIntent->status === 'completed' && $lockedIntent->booking_id) {
                    return;
                }

                $existing = Transaction::where('gateway', 'flutterwave')
                    ->where('gateway_reference', $lockedIntent->reference)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    $lockedIntent->update([
                        'status' => 'completed',
                        'booking_id' => $existing->booking_id,
                        'processed_at' => now(),
                        'last_error' => null,
                    ]);
                    return;
                }

                $lockedIntent->update(['status' => 'processing', 'last_error' => null]);
                $arrData = $lockedIntent->payload;
                $bookingController = new BookingController();
                $booking = $bookingController->storeData($arrData);
                $booking->paymentStatus = 1;
                $booking->transcation_type = 1;
                $booking->transcation_id = $lockedIntent->reference;
                app(EventSaleAccountingService::class)->apply(
                    $booking,
                    'flutterwave',
                    $lockedIntent->reference
                );

                $lockedIntent->update([
                    'status' => 'completed',
                    'booking_id' => $booking->id,
                    'processed_at' => now(),
                    'last_error' => null,
                ]);
                AuditLogger::record('payment.completed', $lockedIntent, ['status' => 'processing'], ['status' => 'completed'], [
                    'gateway' => 'flutterwave',
                    'reference' => $lockedIntent->reference,
                    'booking_id' => $booking->id,
                    'amount' => (float) $lockedIntent->amount,
                    'currency' => $lockedIntent->currency,
                ]);
            }, 3);

            $intent->refresh();
            if (!$intent->booking_id) {
                throw new RuntimeException('The verified Flutterwave payment could not be linked to a booking.');
            }

            app(PaidBookingFulfillmentService::class)->dispatch((int) $intent->booking_id);

            return (int) $intent->booking_id;
        } catch (\Throwable $exception) {
            $failedIntent = PaymentIntent::whereKey($intent->id)->where('status', '!=', 'completed')->first();
            if ($failedIntent) {
                $failedIntent->update([
                    'status' => 'failed',
                    'last_error' => 'Payment processing failed; inspect the correlated exception report.',
                ]);
            }
            throw $exception;
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    private function failIntent(PaymentIntent $intent, string $reason): void
    {
        if ($intent->status === 'completed') {
            return;
        }
        if ($intent->status === 'failed' && $intent->last_error === $reason) {
            return;
        }
        $intent->update(['status' => 'failed', 'last_error' => $reason]);
        $token = (string) (($intent->payload ?: [])['reservation_token'] ?? '');
        if ($token !== '') {
            (new TicketReservationService())->release($token, 'payment_failed');
        }
        AuditLogger::record('payment.failed', $intent, [], ['status' => 'failed'], [
            'gateway' => 'flutterwave',
            'reference' => $intent->reference,
        ]);
    }

    private function clearSession(Request $request): void
    {
        foreach ([
            'event_id', 'eventId', 'selTickets', 'arrData', 'paymentId', 'discount',
            'ticket_reservation_token', 'event_date',
        ] as $key) {
            $request->session()->forget($key);
        }
    }

    private function recoveryUrl(PaymentIntent $intent): string
    {
        return URL::temporarySignedRoute(
            'event_booking.flutterwave.recovery',
            now()->addHours(24),
            ['reference' => $intent->reference]
        );
    }

    private function safeAuthorizationUrl(PaymentIntent $intent): ?string
    {
        $url = trim((string) $intent->authorization_url);
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'checkout.flutterwave.com'
            ? $url
            : null;
    }
}
