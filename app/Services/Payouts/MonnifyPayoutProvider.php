<?php

namespace App\Services\Payouts;

use App\Contracts\PayoutProvider;
use App\Services\MonnifyGatewayClient;
use RuntimeException;

/**
 * Monnify disbursements. Transfers leave from the Monnify wallet set in
 * MONNIFY_DISBURSEMENT_WALLET. Monnify has no recipient object, so the
 * "recipient" is the bank code and account number together.
 */
class MonnifyPayoutProvider implements PayoutProvider
{
  public function __construct(private ?MonnifyGatewayClient $client = null)
  {
  }

  public function name(): string
  {
    return 'monnify';
  }

  public function banks(): array
  {
    return collect($this->client()->call('GET', '/api/v1/banks'))
      ->filter(fn ($bank) => !empty($bank['code']) && !empty($bank['name']))
      ->map(fn ($bank) => ['code' => (string) $bank['code'], 'name' => (string) $bank['name']])
      ->unique('code')->sortBy(fn ($bank) => strtolower($bank['name']))->values()->all();
  }

  public function resolveAccount(string $accountNumber, string $bankCode): array
  {
    $body = $this->client()->call('GET', '/api/v1/disbursements/account/validate', [
      'accountNumber' => $accountNumber,
      'bankCode' => $bankCode,
    ], 10);

    return [
      'account_number' => preg_replace('/\D+/', '', (string) ($body['accountNumber'] ?? '')),
      'account_name' => trim((string) ($body['accountName'] ?? '')),
    ];
  }

  public function createRecipient(string $accountName, string $accountNumber, string $bankCode, string $currency): string
  {
    return 'monnify:' . $bankCode . ':' . $accountNumber;
  }

  public function initiateTransfer(string $recipient, float $amount, string $reference, string $reason, string $currency): array
  {
    [$prefix, $bankCode, $accountNumber] = array_pad(explode(':', $recipient, 3), 3, '');
    if ($prefix !== 'monnify' || $bankCode === '' || $accountNumber === '') {
      throw new RuntimeException('This payout account was saved for another provider. Ask the organiser to re-verify it.');
    }
    $wallet = (string) config('services.monnify.disbursement_wallet');
    if ($wallet === '') {
      throw new RuntimeException('Set MONNIFY_DISBURSEMENT_WALLET before sending Monnify payouts.');
    }
    $body = $this->client()->call('POST', '/api/v2/disbursements/single', [
      'amount' => round($amount, 2),
      'reference' => $reference,
      'narration' => mb_substr($reason, 0, 100),
      'destinationBankCode' => $bankCode,
      'destinationAccountNumber' => $accountNumber,
      'currency' => strtoupper($currency),
      'sourceAccountNumber' => $wallet,
    ], 20);

    return ['data' => $this->normalise($body, $reference), 'raw' => $body];
  }

  public function verifyTransfer(string $reference): array
  {
    $body = $this->client()->call('GET', '/api/v2/disbursements/single/summary', ['reference' => $reference]);

    return ['data' => $this->normalise($body, $reference), 'raw' => $body];
  }

  public function testConnection(): void
  {
    $this->client()->verifyCredentials();
    if ((string) config('services.monnify.disbursement_wallet') === '') {
      throw new RuntimeException('Monnify keys work, but MONNIFY_DISBURSEMENT_WALLET is not set, so payouts can\'t be sent.');
    }
  }

  /** Map Monnify's reply onto the fields the transfer service reads. */
  public static function normalise(array $body, string $reference): array
  {
    $status = match (strtoupper((string) ($body['status'] ?? ''))) {
      'SUCCESS', 'COMPLETED' => 'success',
      'FAILED', 'EXPIRED' => 'failed',
      'REVERSED' => 'reversed',
      'PENDING', 'PENDING_AUTHORIZATION', 'OTP_EMAIL_DISPATCH_FAILED', 'AWAITING_PROCESSING', 'IN_PROGRESS' => 'pending',
      default => 'processing',
    };
    $data = ['status' => $status, 'reference' => (string) ($body['reference'] ?? $reference)];
    if (isset($body['amount'])) {
      $data['amount'] = (int) round((float) $body['amount'] * 100);
    }
    if (!empty($body['currency'])) {
      $data['currency'] = (string) $body['currency'];
    }
    if (!empty($body['transactionReference'])) {
      $data['transfer_code'] = (string) $body['transactionReference'];
    }

    return $data;
  }

  private function client(): MonnifyGatewayClient
  {
    return $this->client ??= new MonnifyGatewayClient();
  }
}
