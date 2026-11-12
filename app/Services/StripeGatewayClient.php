<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\PaymentGateway\OnlineGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout (hosted page): create a session, look it up, and check
 * webhook signatures. Amounts go to Stripe in minor units (kobo, cents).
 * Credentials live encrypted on the "stripe" online gateway row.
 */
class StripeGatewayClient
{
    private const API = 'https://api.stripe.com/v1';

    /** Event currencies Stripe charges in two-decimal minor units. */
    public const CURRENCIES = ['NGN', 'GHS', 'KES', 'ZAR', 'EGP', 'USD', 'GBP', 'EUR', 'CAD', 'AED'];

    private string $secret;
    private string $webhookSecret;

    public function __construct(?array $credentials = null)
    {
        if ($credentials === null) {
            $gateway = OnlineGateway::whereKeyword('stripe')->firstOrFail();
            $credentials = json_decode((string) $gateway->information, true) ?: [];
        }

        $this->secret = trim((string) ($credentials['secret'] ?? ''));
        $this->webhookSecret = trim((string) ($credentials['webhook_secret'] ?? ''));
        if (!str_starts_with($this->secret, 'sk_') && !str_starts_with($this->secret, 'rk_')) {
            throw new RuntimeException('The Stripe secret key is not configured.');
        }
    }

    public function isTestMode(): bool
    {
        return str_contains($this->secret, '_test_');
    }

    /** Start a hosted checkout for one order. Returns the session object. */
    public function createCheckoutSession(
        string $reference,
        int $amountMinor,
        string $currency,
        string $email,
        string $description,
        string $successUrl,
        string $cancelUrl
    ): array {
        try {
            $response = Http::withToken($this->secret)
                ->withHeaders(['Idempotency-Key' => 'tica-session-' . $reference])
                ->asForm()
                ->acceptJson()
                ->timeout(20)
                ->post(self::API . '/checkout/sessions', [
                    'mode' => 'payment',
                    'client_reference_id' => $reference,
                    'customer_email' => $email,
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => strtolower($currency),
                            'unit_amount' => $amountMinor,
                            'product_data' => ['name' => mb_substr($description, 0, 250)],
                        ],
                    ]],
                    'metadata' => ['reference' => $reference, 'payment_kind' => 'event_booking'],
                    'payment_intent_data' => ['metadata' => ['reference' => $reference]],
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'expires_at' => now()->addMinutes(60)->timestamp,
                ]);
        } catch (ConnectionException $exception) {
            throw new GatewayOutcomeUnknownException('Stripe did not answer the checkout request in time.');
        }

        $payload = $response->json() ?: [];
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new GatewayOutcomeUnknownException((string) ($payload['error']['message'] ?? 'Stripe returned an indeterminate response.'));
        }
        if (!$response->successful() || !str_starts_with((string) ($payload['id'] ?? ''), 'cs_')) {
            throw new RuntimeException((string) ($payload['error']['message'] ?? 'Stripe rejected the checkout session.'));
        }
        if ((string) ($payload['client_reference_id'] ?? '') !== $reference || (int) ($payload['amount_total'] ?? -1) !== $amountMinor) {
            throw new RuntimeException('Stripe returned a session that does not match the order.');
        }
        if (!self::isCheckoutUrl((string) ($payload['url'] ?? ''))) {
            throw new RuntimeException('Stripe returned an unexpected checkout address.');
        }

        return $payload;
    }

    public function retrieveSession(string $sessionId): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get(self::API . '/checkout/sessions/' . rawurlencode($sessionId));

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['id'] ?? null) !== $sessionId) {
            throw new RuntimeException((string) ($payload['error']['message'] ?? 'Unable to look up the Stripe checkout session.'));
        }

        return $payload;
    }

    /** Log in once with the key, to check keys an admin just entered. */
    public function verifyCredentials(): void
    {
        $response = Http::withToken($this->secret)->acceptJson()->timeout(20)->get(self::API . '/balance');
        if (!$response->successful()) {
            throw new RuntimeException((string) ($response->json('error.message') ?? 'Stripe did not accept this secret key.'));
        }
    }

    /**
     * Stripe-Signature is "t=<unix time>,v1=<hex>": an HMAC-SHA256 of
     * "<t>.<raw body>" with the endpoint's signing secret.
     */
    public function webhookSignatureIsValid(string $payload, string $header, int $tolerance = 300): bool
    {
        if ($this->webhookSecret === '' || $header === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === [] || abs(time() - $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public static function isCheckoutUrl(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'checkout.stripe.com';
    }

    public static function minorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
