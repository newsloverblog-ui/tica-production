<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\PaymentGateway\OnlineGateway;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwaveGatewayClient
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        if ($secret) {
            $this->secret = $secret;
            return;
        }

        $gateway = OnlineGateway::whereKeyword('flutterwave')->firstOrFail();
        $information = json_decode($gateway->information, true) ?: [];
        $this->secret = (string) ($information['secret_key'] ?? '');
        if ($this->secret === '') {
            throw new RuntimeException('Flutterwave secret key is not configured.');
        }
    }

    public function initialize(array $payment): array
    {
        try {
            $response = Http::withToken($this->secret)
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.flutterwave.com/v3/payments', $payment);
        } catch (ConnectionException $exception) {
            throw new GatewayOutcomeUnknownException(
                'Flutterwave payment initialization could not be confirmed.',
                0,
                $exception
            );
        }

        $payload = $response->json() ?: [];
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new GatewayOutcomeUnknownException(
                (string) ($payload['message'] ?? 'Flutterwave initialization returned an indeterminate response.')
            );
        }
        $link = (string) ($payload['data']['link'] ?? '');
        if (!$response->successful() || ($payload['status'] ?? null) !== 'success' || !$this->isCheckoutUrl($link)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Flutterwave rejected the payment request.'));
        }

        return $payload;
    }

    public function verifyTransaction(int $transactionId): array
    {
        if ($transactionId < 1) {
            throw new RuntimeException('A valid Flutterwave transaction ID is required.');
        }

        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.flutterwave.com/v3/transactions/' . $transactionId . '/verify');

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? null) !== 'success' || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to verify the Flutterwave transaction.'));
        }

        return $payload;
    }

    public function verifyByReference(string $reference): array
    {
        if (trim($reference) === '') {
            throw new RuntimeException('A valid Flutterwave reference is required.');
        }

        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.flutterwave.com/v3/transactions/verify_by_reference', [
                'tx_ref' => $reference,
            ]);

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? null) !== 'success' || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to verify the Flutterwave transaction.'));
        }

        return $payload;
    }

    public function listTransactions(DateTimeInterface $from, DateTimeInterface $to, int $page = 1): array
    {
        if ($page < 1) {
            throw new RuntimeException('Flutterwave transaction page must be at least 1.');
        }

        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(30)
            ->get('https://api.flutterwave.com/v3/transactions', [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'page' => $page,
                'status' => 'successful',
            ]);

        $payload = $response->json() ?: [];
        if (!$response->successful()
            || ($payload['status'] ?? null) !== 'success'
            || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to list Flutterwave transactions.'));
        }

        return $payload;
    }

    public function createRefund(int $transactionId, string $amount, string $reason): array
    {
        if ($transactionId < 1) {
            throw new RuntimeException('A verified Flutterwave transaction ID is required to issue a refund.');
        }

        try {
            $response = Http::withToken($this->secret)
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.flutterwave.com/v3/transactions/' . $transactionId . '/refund', [
                    'amount' => round((float) $amount, 2),
                    'comments' => $reason,
                ]);
        } catch (ConnectionException $exception) {
            throw new GatewayOutcomeUnknownException('Flutterwave refund submission could not be confirmed.', 0, $exception);
        }

        $payload = $response->json() ?: [];
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new GatewayOutcomeUnknownException(
                (string) ($payload['message'] ?? 'Flutterwave refund submission returned an indeterminate response.')
            );
        }
        if (!$response->successful() || ($payload['status'] ?? null) !== 'success' || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Flutterwave rejected the refund request.'));
        }

        return $payload;
    }

    public function fetchRefund(string $refundId): array
    {
        if (trim($refundId) === '') {
            throw new RuntimeException('A valid Flutterwave refund ID is required.');
        }

        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.flutterwave.com/v3/refunds/' . rawurlencode($refundId));

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? null) !== 'success' || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve the Flutterwave refund.'));
        }

        return $payload;
    }

    private function isCheckoutUrl(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'checkout.flutterwave.com';
    }
}
