<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Organizer;
use App\Models\PaymentIntent;
use App\Models\PaystackDispute;
use App\Models\ProductPaymentIntent;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PaystackDisputeService
{
    public function record(array $data): ?PaystackDispute
    {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return null;
        }
        $transaction = is_array($data['transaction'] ?? null) ? $data['transaction'] : [];
        $reference = $transaction['reference'] ?? $data['transaction_reference'] ?? '';
        $reference = is_string($reference) && strlen($reference) <= 191 ? $reference : '';
        $currency = $transaction['currency'] ?? $data['currency'] ?? '';
        $currency = is_string($currency) && preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
        $captured = filter_var($transaction['amount'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $debit = filter_var($data['refund_amount'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return DB::transaction(function () use ($data, $id, $transaction, $reference, $currency, $captured, $debit): PaystackDispute {
            $intent = PaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->lockForUpdate()->first()
                ?? ProductPaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->lockForUpdate()->first();
            $booking = $intent instanceof PaymentIntent && $intent->booking_id
                ? Booking::whereKey($intent->booking_id)->lockForUpdate()->first()
                : null;
            $organizerId = $booking?->organizer_id ?? ($intent instanceof PaymentIntent ? $intent->payload['organizer_id'] ?? null : null);
            if ($organizerId) {
                Organizer::whereKey($organizerId)->lockForUpdate()->first();
            }
            $row = PaystackDispute::firstOrCreate(['provider_dispute_id' => (string) $id], [
                'status' => 'open', 'reference' => $reference,
            ]);
            $row = PaystackDispute::whereKey($row->id)->lockForUpdate()->firstOrFail();
            if ($row->reference !== '' && $row->reference !== $reference) {
                $this->alert($row, 'reference_changed');
                return $row;
            }
            if (in_array($row->status, ['charged_back', 'declined'], true)) {
                if (($data['status'] ?? null) === 'resolved'
                    && (($row->status === 'charged_back' && (($data['resolution'] ?? null) !== 'merchant-accepted' || $debit !== (int) $row->amount_minor))
                        || ($row->status === 'declined' && ($data['resolution'] ?? null) !== 'declined'))) {
                    $this->alert($row, 'conflicting_resolution');
                }
                return $row;
            }

            $exception = null;
            $state = 'open';
            if (!$intent || !$currency || $captured !== Money::toMinor($intent->amount)
                || $currency !== strtoupper($intent->currency)
                || ($intent->gateway_transaction_id && (string) ($transaction['id'] ?? '') !== (string) $intent->gateway_transaction_id)) {
                $exception = 'payment_identity_unverified';
            } elseif (($data['status'] ?? null) === 'resolved') {
                $resolution = $data['resolution'] ?? null;
                if ($resolution === 'declined' && ($debit === false || $debit === 0)) {
                    $state = 'declined';
                } elseif ($resolution === 'merchant-accepted' && $debit > 0 && $debit <= $captured) {
                    $alreadyPosted = (int) PaystackDispute::where('reference', $reference)->where('status', 'charged_back')->sum('amount_minor');
                    if ($alreadyPosted + $debit <= $captured) {
                        app(FinancialLedgerService::class)->postPaystackChargeback($row->id, $currency, $debit);
                        $state = 'charged_back';
                    } else {
                        $exception = 'chargeback_exceeds_capture';
                    }
                } else {
                    $exception = 'resolution_unverified';
                }
            }
            if ($exception) {
                $state = 'needs_attention';
            }
            $row->fill([
                'reference' => $reference, 'booking_id' => $booking?->id, 'organizer_id' => $organizerId,
                'status' => $state, 'currency' => $currency, 'amount_minor' => $debit === false ? null : $debit,
                'exception' => $exception,
            ]);
            if ($row->isDirty()) {
                $row->save();
                AuditLogger::record('payment.paystack_dispute_updated', $row, [], [
                    'status' => $state, 'amount_minor' => $row->amount_minor, 'currency' => $currency,
                ]);
                if ($state !== 'declined') {
                    $this->alert($row, $exception ?? $state);
                }
            }
            return $row;
        }, 3);
    }

    private function alert(PaystackDispute $dispute, string $reason): void
    {
        DB::afterCommit(fn () => app(IncidentAlertService::class)->alert(
            'paystack-dispute:' . $dispute->id . ':' . $reason,
            'A Paystack payment dispute requires finance review.',
            ['dispute_id' => $dispute->id, 'reason' => $reason],
        ));
    }
}
