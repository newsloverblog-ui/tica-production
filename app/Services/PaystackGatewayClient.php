<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\PaymentGateway\OnlineGateway;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGatewayClient
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        if ($secret) {
            $this->secret = $secret;
            return;
        }

        $gateway = OnlineGateway::whereKeyword('paystack')->firstOrFail();
        $information = json_decode($gateway->information, true) ?: [];
        $this->secret = (string) ($information['secret'] ?? $information['key'] ?? '');
        if ($this->secret === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }
    }

    public function initializeTransaction(
        string $reference,
        string $email,
        float $amount,
        string $currency,
        string $callbackUrl,
        array $metadata = []
    ): array {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.paystack.co/transaction/initialize', [
                'reference' => $reference,
                'email' => $email,
                'amount' => (int) round($amount * 100),
                'currency' => strtoupper($currency),
                'callback_url' => $callbackUrl,
                'metadata' => $metadata,
            ]);

        $payload = $response->json() ?: [];
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new GatewayOutcomeUnknownException(
                (string) ($payload['message'] ?? 'Paystack initialization returned an indeterminate response.')
            );
        }
        $authorizationUrl = trim((string) ($payload['data']['authorization_url'] ?? ''));
        $authorizationScheme = strtolower((string) parse_url($authorizationUrl, PHP_URL_SCHEME));
        $authorizationHost = strtolower((string) parse_url($authorizationUrl, PHP_URL_HOST));
        if (!$response->successful()
            || ($payload['status'] ?? false) !== true
            || !is_array($payload['data'] ?? null)
            || $authorizationScheme !== 'https'
            || $authorizationHost !== 'checkout.paystack.com') {
            throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the payment initialization.'));
        }

        return $payload;
    }

    public function createRefund(string $reference, string $amount, string $currency, string $reason): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.paystack.co/refund', [
                'transaction' => $reference,
                'amount' => (int) round(((float) $amount) * 100),
                'currency' => strtoupper($currency),
                'customer_note' => $reason,
                'merchant_note' => $reason,
            ]);

        $payload = $response->json() ?: [];
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new GatewayOutcomeUnknownException(
                (string) ($payload['message'] ?? 'Paystack refund submission returned an indeterminate response.')
            );
        }
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the refund request.'));
        }

        return $payload;
    }

    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to verify the Paystack transaction.'));
        }

        return $payload;
    }

    public function fetchRefund(string $refundId): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.paystack.co/refund/' . rawurlencode($refundId));

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve the Paystack refund.'));
        }

        return $payload;
    }

    public function listRefundsForTransaction(
        string $transactionId,
        int $page = 1,
        int $perPage = 50
    ): array {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            throw new RuntimeException('A Paystack transaction ID is required to recover a refund.');
        }

        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.paystack.co/refund', [
                'transaction' => $transactionId,
                'page' => max(1, $page),
                'perPage' => min(100, max(1, $perPage)),
            ]);

        $payload = $response->json() ?: [];
        if (!$response->successful()
            || ($payload['status'] ?? false) !== true
            || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to list Paystack refunds.'));
        }

        return $payload;
    }

    public function listTransactions(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $page = 1,
        int $perPage = 100
    ): array {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(30)
            ->get('https://api.paystack.co/transaction', [
                'status' => 'success',
                'from' => $from->format(DateTimeInterface::ATOM),
                'to' => $to->format(DateTimeInterface::ATOM),
                'page' => $page,
                'perPage' => min(100, max(1, $perPage)),
            ]);

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve Paystack transactions.'));
        }

        return $payload;
    }

    public function listSettlements(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $page = 1,
        int $perPage = 100
    ): array {
        return $this->getPaginated('https://api.paystack.co/settlement', [
            'from' => $from->format(DateTimeInterface::ATOM),
            'to' => $to->format(DateTimeInterface::ATOM),
            'page' => $page,
            'perPage' => min(100, max(1, $perPage)),
        ], 'Unable to retrieve Paystack settlements.');
    }

    public function listSettlementTransactions(
        string $settlementId,
        int $page = 1,
        int $perPage = 100
    ): array {
        return $this->getPaginated(
            'https://api.paystack.co/settlement/' . rawurlencode($settlementId) . '/transactions',
            ['page' => $page, 'perPage' => min(100, max(1, $perPage))],
            'Unable to retrieve Paystack settlement transactions.'
        );
    }

    public function listBanks(string $country = 'nigeria', string $currency = 'NGN'): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.paystack.co/bank', [
                'country' => strtolower($country),
                'currency' => strtoupper($currency),
                'use_cursor' => 'false',
                'perPage' => 100,
            ]);

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve Paystack banks.'));
        }

        return $payload;
    }

    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.paystack.co/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to verify the bank account.'));
        }

        return $payload;
    }

    public function createTransferRecipient(
        string $name,
        string $accountNumber,
        string $bankCode,
        string $currency = 'NGN'
    ): array {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.paystack.co/transferrecipient', [
                'type' => 'nuban',
                'name' => $name,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'currency' => strtoupper($currency),
            ]);

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the transfer recipient.'));
        }

        return $payload;
    }

    public function initiateTransfer(
        string $recipientCode,
        float $amount,
        string $reference,
        string $reason,
        string $currency = 'NGN'
    ): array {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.paystack.co/transfer', [
                'source' => 'balance',
                'amount' => (int) round($amount * 100),
                'recipient' => $recipientCode,
                'reference' => $reference,
                'reason' => $reason,
                'currency' => strtoupper($currency),
            ]);

        $payload = $response->json() ?: [];
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new GatewayOutcomeUnknownException(
                (string) ($payload['message'] ?? 'Paystack transfer submission returned an indeterminate response.')
            );
        }
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the transfer request.'));
        }

        return $payload;
    }

    public function verifyTransfer(string $reference): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.paystack.co/transfer/verify/' . rawurlencode($reference));

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException((string) ($payload['message'] ?? 'Unable to verify the Paystack transfer.'));
        }

        return $payload;
    }

    private function getPaginated(string $url, array $query, string $fallbackMessage): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(30)
            ->get($url, $query);

        $payload = $response->json() ?: [];
        if (!$response->successful() || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? $fallbackMessage));
        }

        return $payload;
    }
}
