<?php

namespace App\Contracts;

/**
 * A bank payout provider (Paystack, Monnify). Every call runs on the server;
 * keys never leave the backend. Transfer replies are normalised to:
 *   ['status' => success|failed|reversed|pending|processing,
 *    'transfer_code' => ?string, 'reference' => string,
 *    'amount' => int (kobo), 'currency' => string]
 */
interface PayoutProvider
{
  /** Machine name stored on accounts and transfers, e.g. "paystack". */
  public function name(): string;

  /** @return array<int, array{code: string, name: string}> */
  public function banks(): array;

  /** @return array{account_number: string, account_name: string} */
  public function resolveAccount(string $accountNumber, string $bankCode): array;

  /** Register the account with the provider and return its recipient reference. */
  public function createRecipient(string $accountName, string $accountNumber, string $bankCode, string $currency): string;

  public function initiateTransfer(string $recipient, float $amount, string $reference, string $reason, string $currency): array;

  public function verifyTransfer(string $reference): array;

  /** Throws with a readable message when keys are missing or rejected. */
  public function testConnection(): void;
}
