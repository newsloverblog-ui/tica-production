<?php

namespace Tests\Unit;

use App\Services\PaystackPaymentVerifier;
use PHPUnit\Framework\TestCase;

class PaystackPaymentVerifierTest extends TestCase
{
    private array $expected = [
        'price' => 9000,
        'tax' => 1000,
        'currencyText' => 'NGN',
        'email' => 'buyer@example.com',
    ];

    public function test_a_matching_payment_is_accepted(): void
    {
        $this->assertSame([], PaystackPaymentVerifier::errors([
            'status' => 'success',
            'amount' => 1000000,
            'currency' => 'NGN',
            'customer' => ['email' => 'BUYER@example.com'],
        ], $this->expected));
    }

    public function test_amount_currency_status_and_customer_mismatches_are_rejected(): void
    {
        $this->assertSame([
            'payment_not_successful',
            'amount_mismatch',
            'currency_mismatch',
            'customer_mismatch',
        ], PaystackPaymentVerifier::errors([
            'status' => 'failed',
            'amount' => 999,
            'currency' => 'USD',
            'customer' => ['email' => 'attacker@example.com'],
        ], $this->expected));
    }

    public function test_provider_reference_must_match_the_local_payment_intent(): void
    {
        $errors = PaystackPaymentVerifier::errors([
            'reference' => 'provider-reference',
            'status' => 'success',
            'amount' => 10000,
            'currency' => 'NGN',
            'customer' => ['email' => 'buyer@example.com'],
        ], [
            'reference' => 'local-reference',
            'price' => 90,
            'tax' => 10,
            'currencyText' => 'NGN',
            'email' => 'buyer@example.com',
        ]);

        $this->assertSame(['reference_mismatch'], $errors);
    }
}

