<?php

namespace App\Services;

use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class GatewayLedgerIntegrityService
{
    public function forReferences(string $gateway, array $references): Collection
    {
        $references = array_values(array_unique(array_filter(array_map(
            static fn ($reference) => trim((string) $reference),
            $references
        ))));

        if ($references === []) {
            return collect();
        }

        return Transaction::query()
            ->where('gateway', strtolower(trim($gateway)))
            ->whereIn('gateway_reference', $references)
            ->get()
            ->keyBy('gateway_reference');
    }

    public function issues(PaymentIntent $intent, ?Transaction $transaction): array
    {
        if (!$transaction) {
            return ['ledger_identity_missing'];
        }

        $issues = [];
        if ((int) $transaction->transcation_type !== 1) {
            $issues[] = 'ledger_type_mismatch';
        }
        if ((string) $transaction->booking_id !== (string) $intent->booking_id) {
            $issues[] = 'ledger_booking_mismatch';
        }
        $chargedTotal = round((float) $transaction->grand_total + (float) $transaction->tax, 2);
        if (abs($chargedTotal - (float) $intent->amount) > 0.009) {
            $issues[] = 'ledger_amount_mismatch';
        }
        if (!in_array(strtolower((string) $transaction->payment_status), [
            '1', 'completed', 'paid', 'success',
        ], true)) {
            $issues[] = 'ledger_status_not_paid';
        }

        return $issues;
    }
}
