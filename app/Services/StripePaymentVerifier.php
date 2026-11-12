<?php

namespace App\Services;

/**
 * Checks a Stripe Checkout session against the checkout we created. Only a
 * completed, paid session for exactly this order's amount and currency may
 * release tickets.
 */
class StripePaymentVerifier
{
    public static function errors(array $session, array $expected, string $reference): array
    {
        $errors = [];

        if (($session['status'] ?? null) !== 'complete' || ($session['payment_status'] ?? null) !== 'paid') {
            $errors[] = 'payment_not_successful';
        }
        if ((string) ($session['client_reference_id'] ?? '') !== $reference) {
            $errors[] = 'reference_mismatch';
        }

        $expectedAmount = StripeGatewayClient::minorUnits((float) ($expected['price'] ?? 0) + (float) ($expected['tax'] ?? 0));
        if ((int) ($session['amount_total'] ?? -1) !== $expectedAmount) {
            $errors[] = 'amount_mismatch';
        }
        if (strtoupper((string) ($session['currency'] ?? '')) !== strtoupper((string) ($expected['currencyText'] ?? ''))) {
            $errors[] = 'currency_mismatch';
        }

        return $errors;
    }
}
