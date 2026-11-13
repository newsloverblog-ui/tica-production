<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\PaymentGateway\OnlineGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Monnify collections API: log in, start a hosted checkout and look up a
 * transaction. Monnify charges in naira only, and amounts are in naira (not
 * kobo). Credentials live encrypted on the "monnify" online gateway row.
 */
class MonnifyGatewayClient
{
    private const SANDBOX = 'https://sandbox.monnify.com';
    private const LIVE = 'https://api.monnify.com';

    private string $apiKey;
    private string $secretKey;
    private string $contractCode;
    private bool $sandbox;

    public function __construct(?array $credentials = null)
    {
        if ($credentials === null) {
            $gateway = OnlineGateway::whereKeyword('monnify')->firstOrFail();
            $credentials = json_decode((string) $gateway->information, true) ?: [];
        }

        $this->apiKey = trim((string) ($credentials['api_key'] ?? ''));
        $this->secretKey = trim((string) ($credentials['secret_key'] ?? ''));
        $this->contractCode = trim((string) ($credentials['contract_code'] ?? ''));
        $this->sandbox = ($credentials['mode'] ?? 'sandbox') !== 'live';

        if ($this->apiKey === '' || $this->secretKey === '' || $this->contractCode === '') {
            throw new RuntimeException('Monnify API key, secret key and contract code must all be configured.');
        }
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /** Start a hosted checkout. Returns Monnify's responseBody. */
    public function initializeTransaction(
        string $paymentReference,
        float $amount,
        string $customerName,
        string $customerEmail,
        string $description,
        string $redirectUrl,
        array $metadata = []
    ): array {
        try {
            $response = Http::withToken($this->token())
                ->acceptJson()
                ->timeout(20)
                ->post($this->base() . '/api/v1/merchant/transactions/init-transaction', [
                    'amount' => round($amount, 2),
                    'customerName' => mb_substr($customerName, 0, 100),
                    'customerEmail' => $customerEmail,
                    'paymentReference' => $paymentReference,
                    'paymentDescription' => mb_substr($description, 0, 100),
                    'currencyCode' => 'NGN',
                    'contractCode' => $this->contractCode,
                    'redirectUrl' => $redirectUrl,
                    'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER', 'USSD'],
                    'metadata' => $metadata,
                ]);
        } catch (ConnectionException $exception) {
            throw new GatewayOutcomeUnknownException('Monnify did not answer the checkout request in time.');
        }

        $payload = $response->json() ?: [];
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new GatewayOutcomeUnknownException(
                (string) ($payload['responseMessage'] ?? 'Monnify initialization returned an indeterminate response.')
            );
        }

        $body = $payload['responseBody'] ?? null;
        if (!$response->successful() || ($payload['requestSuccessful'] ?? false) !== true || !is_array($body)) {
            throw new RuntimeException((string) ($payload['responseMessage'] ?? 'Monnify rejected the payment initialization.'));
        }
        // Monnify asks merchants to confirm the echo matches what was sent.
        if ((string) ($body['paymentReference'] ?? '') !== $paymentReference) {
            throw new RuntimeException('Monnify returned a different payment reference.');
        }
        if (!self::isCheckoutUrl((string) ($body['checkoutUrl'] ?? ''))) {
            throw new RuntimeException('Monnify returned an unexpected checkout address.');
        }

        return $body;
    }

    /** Look up a transaction by our own payment reference. Returns Monnify's responseBody. */
    public function queryByPaymentReference(string $paymentReference): array
    {
        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(20)
            ->get($this->base() . '/api/v2/merchant/transactions/query', ['paymentReference' => $paymentReference]);

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['requestSuccessful'] ?? false) !== true || !is_array($payload['responseBody'] ?? null)) {
            throw new RuntimeException((string) ($payload['responseMessage'] ?? 'Unable to verify the Monnify transaction.'));
        }

        return $payload['responseBody'];
    }

    /** Monnify's hosted checkout lives on monnify.com over HTTPS. */
    public static function isCheckoutUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $scheme === 'https' && ($host === 'monnify.com' || str_ends_with($host, '.monnify.com'));
    }

    private function base(): string
    {
        return $this->sandbox ? self::SANDBOX : self::LIVE;
    }

    /**
     * Authenticated call for disbursements (banks, account check, transfers).
     * Returns responseBody; throws with Monnify's message when it refuses.
     */
    public function call(string $method, string $path, array $data = [], int $timeout = 15): array
    {
        $request = Http::withToken($this->token())->acceptJson()->timeout($timeout);
        $response = strtoupper($method) === 'GET'
            ? $request->get($this->base() . $path, $data)
            : $request->post($this->base() . $path, $data);

        if (!$response->successful() || $response->json('requestSuccessful') === false) {
            $exception = new RuntimeException((string) ($response->json('responseMessage') ?? 'Monnify could not complete the request.'));
            if ($response->serverError()) {
                throw new \App\Exceptions\GatewayOutcomeUnknownException($exception->getMessage(), 0, $exception);
            }
            throw $exception;
        }

        return (array) ($response->json('responseBody') ?? []);
    }

    /** Log in once without the cache, to check keys an admin just entered. */
    public function verifyCredentials(): void
    {
        $this->login();
    }

    /** Access tokens last an hour; keep one for 50 minutes per key and environment. */
    private function token(): string
    {
        $cacheKey = 'monnify_token:' . ($this->sandbox ? 'sandbox' : 'live') . ':' . hash('sha256', $this->apiKey);

        return Cache::remember($cacheKey, now()->addMinutes(50), fn () => $this->login());
    }

    private function login(): string
    {
        $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
            ->acceptJson()
            ->timeout(20)
            ->post($this->base() . '/api/v1/auth/login');

        $token = (string) ($response->json('responseBody.accessToken') ?? '');
        if (!$response->successful() || $token === '') {
            throw new RuntimeException((string) ($response->json('responseMessage') ?? 'Monnify login failed. Check the API key and secret key.'));
        }

        return $token;
    }
}
