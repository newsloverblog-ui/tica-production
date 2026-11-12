<?php

namespace App\Services;

use InvalidArgumentException;
use Stripe\StripeClient;

class StripeChargeService
{
    public function charge(string $token, float $amount, string $currency = 'usd'): array
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new InvalidArgumentException('Stripe is not configured.');
        }

        $minorAmount = (int) round($amount * 100);

        if ($minorAmount <= 0) {
            throw new InvalidArgumentException('The Stripe charge amount must be greater than zero.');
        }

        $charge = (new StripeClient($secret))->charges->create([
            'amount' => $minorAmount,
            'currency' => strtolower($currency),
            'source' => $token,
        ]);

        return $charge->toArray();
    }
}
