<?php

namespace App\Services;

/**
 * Checks a Monnify transaction (from the query API) against the checkout we
 * created. Only a full payment of the exact amount, in naira, for this
 * reference and buyer may release tickets.
 */
class MonnifyPaymentVerifier
{
    public static function errors(array $payment, array $expected, string $paymentReference): array
    {
        $errors = [];

        if (strtoupper((string) ($payment['paymentStatus'] ?? '')) !== 'PAID') {
            $errors[] = 'payment_not_successful';
        }
        if ((string) ($payment['paymentReference'] ?? '') !== $paymentReference) {
            $errors[] = 'reference_mismatch';
        }

        $expectedAmount = (int) round(((float) ($expected['price'] ?? 0) + (float) ($expected['tax'] ?? 0)) * 100);
        $actualAmount = (int) round(((float) ($payment['amountPaid'] ?? -1)) * 100);
        if ($actualAmount !== $expectedAmount) {
            $errors[] = 'amount_mismatch';
        }

        $currency = strtoupper((string) ($payment['currencyCode'] ?? $payment['currency'] ?? ''));
        if ($currency !== 'NGN' || strtoupper((string) ($expected['currencyText'] ?? 'NGN')) !== 'NGN') {
            $errors[] = 'currency_mismatch';
        }

        $expectedEmail = strtolower(trim((string) ($expected['email'] ?? '')));
        $actualEmail = strtolower(trim((string) ($payment['customerDTO']['email'] ?? $payment['customer']['email'] ?? '')));
        if ($expectedEmail !== '' && $actualEmail !== '' && $actualEmail !== $expectedEmail) {
            $errors[] = 'customer_mismatch';
        }

        return $errors;
    }
}
