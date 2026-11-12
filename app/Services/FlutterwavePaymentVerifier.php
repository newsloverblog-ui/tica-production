<?php

namespace App\Services;

use App\Models\PaymentIntent;

class FlutterwavePaymentVerifier
{
    public static function errors(array $payment, PaymentIntent $intent): array
    {
        $errors = [];

        if (strtolower((string) ($payment['status'] ?? '')) !== 'successful') {
            $errors[] = 'payment_not_successful';
        }
        if (!hash_equals($intent->reference, (string) ($payment['tx_ref'] ?? ''))) {
            $errors[] = 'reference_mismatch';
        }

        $actualAmount = round((float) ($payment['amount'] ?? -1), 2);
        $expectedAmount = round((float) $intent->amount, 2);
        if ($actualAmount !== $expectedAmount) {
            $errors[] = 'amount_mismatch';
        }
        if (strtoupper((string) ($payment['currency'] ?? '')) !== strtoupper($intent->currency)) {
            $errors[] = 'currency_mismatch';
        }

        $actualEmail = strtolower(trim((string) ($payment['customer']['email'] ?? '')));
        $expectedEmail = strtolower(trim((string) $intent->email));
        if ($expectedEmail !== '' && $actualEmail !== $expectedEmail) {
            $errors[] = 'customer_mismatch';
        }

        return $errors;
    }
}
