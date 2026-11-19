<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PaystackCheckoutDiagnosticTest extends TestCase
{
    public function test_checkout_diagnostic_redacts_keys_and_customer_email(): void
    {
        $method = new ReflectionMethod(PaystackController::class, 'safeGatewayFailureReason');
        $method->setAccessible(true);

        $result = $method->invoke(
            new PaystackController(),
            new RuntimeException('Paystack rejected sk_test_1234567890ABCDEFGHIJ for buyer@example.com')
        );

        $this->assertSame(
            'Paystack rejected [redacted-key] for [redacted-email]',
            $result
        );
    }
}
