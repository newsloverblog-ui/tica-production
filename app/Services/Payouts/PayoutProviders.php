<?php

namespace App\Services\Payouts;

use App\Contracts\PayoutProvider;
use App\Support\TicaPlatform;
use InvalidArgumentException;

/** Picks the payout provider: the admin's choice for new work, or a named one for existing records. */
class PayoutProviders
{
  public function current(): PayoutProvider
  {
    return $this->for(TicaPlatform::payoutProvider());
  }

  public function for(string $name): PayoutProvider
  {
    return match ($name) {
      'paystack' => app(PaystackPayoutProvider::class),
      'monnify' => app(MonnifyPayoutProvider::class),
      default => throw new InvalidArgumentException('Unknown payout provider ' . $name . '.'),
    };
  }
}
