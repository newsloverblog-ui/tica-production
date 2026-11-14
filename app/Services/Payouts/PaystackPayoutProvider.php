<?php

namespace App\Services\Payouts;

use App\Contracts\PayoutProvider;
use App\Services\PaystackGatewayClient;

class PaystackPayoutProvider implements PayoutProvider
{
  public function __construct(private ?PaystackGatewayClient $client = null)
  {
  }

  public function name(): string
  {
    return 'paystack';
  }

  public function banks(): array
  {
    $payload = $this->client()->listBanks('nigeria', 'NGN');

    return collect((array) ($payload['data'] ?? []))
      ->filter(fn ($bank) => !empty($bank['code']) && !empty($bank['name']) && ($bank['active'] ?? true))
      ->map(fn ($bank) => ['code' => (string) $bank['code'], 'name' => (string) $bank['name']])
      ->unique('code')->sortBy(fn ($bank) => strtolower($bank['name']))->values()->all();
  }

  public function resolveAccount(string $accountNumber, string $bankCode): array
  {
    $data = (array) ($this->client()->resolveAccount($accountNumber, $bankCode)['data'] ?? []);

    return [
      'account_number' => preg_replace('/\D+/', '', (string) ($data['account_number'] ?? '')),
      'account_name' => trim((string) ($data['account_name'] ?? '')),
    ];
  }

  public function createRecipient(string $accountName, string $accountNumber, string $bankCode, string $currency): string
  {
    $payload = $this->client()->createTransferRecipient($accountName, $accountNumber, $bankCode, $currency);
    $code = trim((string) ($payload['data']['recipient_code'] ?? ''));
    if ($code === '') {
      throw new \RuntimeException('Paystack did not return a transfer recipient code.');
    }

    return $code;
  }

  public function initiateTransfer(string $recipient, float $amount, string $reference, string $reason, string $currency): array
  {
    $payload = $this->client()->initiateTransfer($recipient, $amount, $reference, $reason, $currency);

    return ['data' => (array) ($payload['data'] ?? []) + ['status' => 'pending'], 'raw' => $payload];
  }

  public function verifyTransfer(string $reference): array
  {
    $payload = $this->client()->verifyTransfer($reference);

    return ['data' => (array) ($payload['data'] ?? []), 'raw' => $payload];
  }

  public function testConnection(): void
  {
    $this->client()->listBanks('nigeria', 'NGN');
  }

  private function client(): PaystackGatewayClient
  {
    return $this->client ??= new PaystackGatewayClient();
  }
}
