<?php

namespace Tests\Unit;

use App\Models\PaymentIntent;
use App\Services\FlutterwavePaymentVerifier;
use PHPUnit\Framework\TestCase;

class FlutterwavePaymentVerifierTest extends TestCase
{
    public function test_exact_verified_payment_is_accepted(): void
    {
        $intent = $this->intent();

        $this->assertSame([], FlutterwavePaymentVerifier::errors([
            'status' => 'successful',
            'tx_ref' => 'tica-flw-123',
            'amount' => 12500.50,
            'currency' => 'ngn',
            'customer' => ['email' => 'BUYER@example.com'],
        ], $intent));
    }

    public function test_status_reference_amount_currency_and_customer_must_all_match(): void
    {
        $intent = $this->intent();

        $this->assertSame([
            'payment_not_successful',
            'reference_mismatch',
            'amount_mismatch',
            'currency_mismatch',
            'customer_mismatch',
        ], FlutterwavePaymentVerifier::errors([
            'status' => 'failed',
            'tx_ref' => 'attacker-ref',
            'amount' => 1,
            'currency' => 'USD',
            'customer' => ['email' => 'attacker@example.com'],
        ], $intent));
    }

    private function intent(): PaymentIntent
    {
        $intent = new PaymentIntent();
        // This is a framework-free verifier unit test, so hydrate the persisted
        // legacy representation directly instead of invoking Laravel's
        // application-bound encrypter cast.
        $intent->setRawAttributes([
            'reference' => 'tica-flw-123',
            'amount' => 12500.50,
            'currency' => 'NGN',
            'email' => 'buyer@example.com',
        ]);
        return $intent;
    }
}
