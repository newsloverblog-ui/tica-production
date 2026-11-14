<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * The bank list, per provider. Fresh copies are kept for 12 hours. A last
 * good copy is kept for 30 days and used when the provider is slow or down,
 * so organisers can still pick their bank.
 */
class BankDirectory
{
  public function __construct(private PayoutProviders $providers)
  {
  }

  /** @return array{banks: array<int, array{code: string, name: string}>, stale: bool} */
  public function list(?string $provider = null, ?\App\Contracts\PayoutProvider $instance = null): array
  {
    $provider = $instance?->name() ?? $provider ?? $this->providers->current()->name();
    $fresh = Cache::get($this->freshKey($provider));
    if (is_array($fresh) && $fresh !== []) {
      return ['banks' => $fresh, 'stale' => false];
    }

    try {
      $banks = ($instance ?? $this->providers->for($provider))->banks();
      if ($banks === []) {
        throw new RuntimeException('The provider returned an empty bank list.');
      }
      Cache::put($this->freshKey($provider), $banks, now()->addHours(12));
      Cache::put($this->lastGoodKey($provider), $banks, now()->addDays(30));

      return ['banks' => $banks, 'stale' => false];
    } catch (Throwable $exception) {
      report($exception);
      $lastGood = Cache::get($this->lastGoodKey($provider));
      if (is_array($lastGood) && $lastGood !== []) {
        return ['banks' => $lastGood, 'stale' => true];
      }
      throw new RuntimeException('We couldn\'t load the bank list. Try again in a minute.', 0, $exception);
    }
  }

  /** @return array{code: string, name: string} */
  public function find(string $bankCode, ?string $provider = null, ?\App\Contracts\PayoutProvider $instance = null): array
  {
    foreach ($this->list($provider, $instance)['banks'] as $bank) {
      if (hash_equals($bank['code'], $bankCode)) {
        return $bank;
      }
    }
    throw new RuntimeException('Choose your bank from the list.');
  }

  public function forget(string $provider): void
  {
    Cache::forget($this->freshKey($provider));
  }

  private function freshKey(string $provider): string
  {
    return 'payout-banks:' . $provider;
  }

  private function lastGoodKey(string $provider): string
  {
    return 'payout-banks-last-good:' . $provider;
  }
}
