<?php

namespace Tests\Unit;

use App\Models\ProductPaymentIntent;
use App\Services\ProductPaystackPaymentVerifier;
use PHPUnit\Framework\TestCase;

class ProductPaystackPaymentVerifierTest extends TestCase
{
    public function test_exact_payment_contract_is_accepted(): void
    {
        $intent = new ProductPaymentIntent([
            'reference' => 'tica-shop-1', 'amount' => 100.25,
            'currency' => 'NGN', 'email' => 'Buyer@Example.com',
        ]);

        $this->assertSame([], ProductPaystackPaymentVerifier::errors([
            'status' => 'success', 'reference' => 'tica-shop-1', 'amount' => 10025,
            'currency' => 'ngn', 'customer' => ['email' => 'buyer@example.com'],
        ], $intent));
    }

    public function test_every_financial_identity_mismatch_fails_closed(): void
    {
        $intent = new ProductPaymentIntent([
            'reference' => 'tica-shop-1', 'amount' => 100.25,
            'currency' => 'NGN', 'email' => 'buyer@example.com',
        ]);
        $errors = ProductPaystackPaymentVerifier::errors([
            'status' => 'pending', 'reference' => 'other', 'amount' => 1,
            'currency' => 'USD', 'customer' => ['email' => 'attacker@example.com'],
        ], $intent);

        $this->assertEqualsCanonicalizing([
            'payment_not_successful', 'reference_mismatch', 'amount_mismatch',
            'currency_mismatch', 'customer_mismatch',
        ], $errors);
    }
}
