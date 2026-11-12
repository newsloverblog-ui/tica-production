<?php

namespace App\Services;

class PaystackPaymentVerifier
{
    public static function errors(array $payment, array $expected): array
    {
        $errors = [];
        $expectedAmount = (int) round(((float) ($expected['price'] ?? 0) + (float) ($expected['tax'] ?? 0)) * 100);
        $actualAmount = (int) ($payment['amount'] ?? -1);

        if (($payment['status'] ?? null) !== 'success') {
            $errors[] = 'payment_not_successful';
        }
        if ($actualAmount !== $expectedAmount) {
            $errors[] = 'amount_mismatch';
        }

        $expectedCurrency = strtoupper((string) ($expected['currencyText'] ?? 'NGN'));
        $actualCurrency = strtoupper((string) ($payment['currency'] ?? ''));
        if ($actualCurrency !== $expectedCurrency) {
            $errors[] = 'currency_mismatch';
        }

        $expectedEmail = strtolower(trim((string) ($expected['email'] ?? '')));
        $actualEmail = strtolower(trim((string) ($payment['customer']['email'] ?? '')));
        if ($expectedEmail !== '' && $actualEmail !== $expectedEmail) {
            $errors[] = 'customer_mismatch';
        }

        return $errors;
    }
}

