<?php

namespace App\Services;

use App\Models\ProductPaymentIntent;

class ProductPaystackPaymentVerifier
{
    public static function errors(array $payment, ProductPaymentIntent $intent): array
    {
        $errors = [];

        if (strtolower((string) ($payment['status'] ?? '')) !== 'success') {
            $errors[] = 'payment_not_successful';
        }
        if ((string) ($payment['reference'] ?? '') !== (string) $intent->reference) {
            $errors[] = 'reference_mismatch';
        }
        if ((int) ($payment['amount'] ?? -1) !== (int) round(((float) $intent->amount) * 100)) {
            $errors[] = 'amount_mismatch';
        }
        if (strtoupper((string) ($payment['currency'] ?? '')) !== strtoupper((string) $intent->currency)) {
            $errors[] = 'currency_mismatch';
        }

        $actualEmail = strtolower(trim((string) ($payment['customer']['email'] ?? '')));
        if ($actualEmail === '' || $actualEmail !== strtolower(trim((string) $intent->email))) {
            $errors[] = 'customer_mismatch';
        }

        return $errors;
    }
}
