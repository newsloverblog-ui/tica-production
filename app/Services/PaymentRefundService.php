<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Organizer;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundDispute;
use App\Models\Transaction;
use App\Support\LifecycleMailCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentRefundService
{
    private const SUPPORTED_GATEWAYS = ['paystack', 'flutterwave'];

    private const RESERVED_STATUSES = [
        'approval_pending', 'requested', 'submitting', 'verification_required', 'pending',
        'processing', 'needs_attention', 'processed',
    ];

    private const IN_FLIGHT_STATUSES = [
        'approval_pending', 'requested', 'submitting', 'verification_required', 'pending',
        'processing', 'needs_attention',
    ];

    public function request(int $paymentIntentId, float $amount, string $reason, int $adminId): PaymentRefund
    {
        return $this->createRequest($paymentIntentId, $amount, $reason, $adminId, null, 'requested');
    }

    public function requestForEventCancellation(
        int $paymentIntentId,
        float $amount,
        string $reason,
        int $adminId,
        int $cancellationRequestId
    ): PaymentRefund {
        return $this->createRequest(
            $paymentIntentId,
            $amount,
            $reason,
            $adminId,
            null,
            'requested',
            $cancellationRequestId
        );
    }

    public function requestFromApprovedDispute(
        PaymentRefundDispute $dispute,
        int $adminId
    ): PaymentRefund {
        return DB::transaction(function () use ($dispute, $adminId) {
            $locked = PaymentRefundDispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, [
                PaymentRefundDispute::STATUS_OPEN,
                PaymentRefundDispute::STATUS_UNDER_REVIEW,
            ], true) || $locked->payment_refund_id || (int) $locked->checked_in_ticket_count < 1) {
                throw new RuntimeException('The checked-in refund dispute is not eligible for approval.');
            }

            $intent = PaymentIntent::whereKey($locked->payment_intent_id)->lockForUpdate()->firstOrFail();
            if ((int) $intent->booking_id !== (int) $locked->booking_id) {
                throw new RuntimeException('The refund dispute payment identity no longer matches its booking.');
            }

            return $this->createRequest(
                (int) $locked->payment_intent_id,
                (float) $locked->requested_amount,
                mb_substr('Approved checked-in refund dispute '.$locked->public_id.': '.$locked->description, 0, 2000),
                $adminId,
                null,
                'requested',
                null,
                (int) $locked->id,
                true
            );
        }, 3);
    }

    public function remainingRefundableAmount(PaymentIntent $intent): float
    {
        $reserved = (float) PaymentRefund::where('payment_intent_id', $intent->id)
            ->whereIn('status', self::RESERVED_STATUSES)
            ->sum('amount');

        return max(0, round((float) $intent->amount - $reserved, 2));
    }

    public function requestByOrganizer(
        int $paymentIntentId,
        float $amount,
        string $reason,
        int $organizerId
    ): PaymentRefund {
        return $this->createRequest(
            $paymentIntentId,
            $amount,
            $reason,
            null,
            $organizerId,
            'approval_pending'
        );
    }

    public function approveOrganizerRequest(int $refundId, int $adminId): PaymentRefund
    {
        return DB::transaction(function () use ($refundId, $adminId) {
            $refund = PaymentRefund::whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($refund->status === 'requested' && $refund->requested_by_organizer_id) {
                return $refund;
            }
            if ($refund->status !== 'approval_pending' || !$refund->requested_by_organizer_id) {
                throw new RuntimeException('Only a pending organizer refund request can be approved.');
            }

            $intent = PaymentIntent::whereKey($refund->payment_intent_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($refund->booking_id)->lockForUpdate()->firstOrFail();
            $this->assertRefundablePayment($intent, $booking);

            $ledger = Transaction::whereKey($refund->transaction_id)
                ->where('gateway', $intent->gateway)
                ->where('gateway_reference', $intent->reference)
                ->where('booking_id', $booking->id)
                ->where('transcation_type', 1)
                ->lockForUpdate()
                ->first();
            if (!$ledger) {
                throw new RuntimeException('The payment sale ledger identity changed before approval.');
            }

            $reservedElsewhere = (float) PaymentRefund::where('payment_intent_id', $intent->id)
                ->where('id', '!=', $refund->id)
                ->whereIn('status', self::RESERVED_STATUSES)
                ->sum('amount');
            if ((float) $refund->amount > round((float) $intent->amount - $reservedElsewhere, 2)) {
                throw new RuntimeException('Refund exceeds the remaining refundable amount.');
            }

            $refund->update([
                'status' => 'requested',
                'requested_by_admin_id' => $adminId,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            AuditLogger::record('refund.organizer_request_approved', $refund, [
                'status' => 'approval_pending',
            ], [
                'status' => 'requested',
                'reviewed_by_admin_id' => $adminId,
            ]);

            return $refund->fresh();
        }, 3);
    }

    public function rejectOrganizerRequest(
        int $refundId,
        int $adminId,
        string $reason
    ): PaymentRefund {
        return DB::transaction(function () use ($refundId, $adminId, $reason) {
            $refund = PaymentRefund::whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($refund->status === 'rejected' && $refund->requested_by_organizer_id) {
                return $refund;
            }
            if ($refund->status !== 'approval_pending' || !$refund->requested_by_organizer_id) {
                throw new RuntimeException('Only a pending organizer refund request can be rejected.');
            }

            $refund->update([
                'status' => 'rejected',
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLogger::record('refund.organizer_request_rejected', $refund, [
                'status' => 'approval_pending',
            ], [
                'status' => 'rejected',
                'reviewed_by_admin_id' => $adminId,
            ], ['reason' => $reason]);

            return $refund->fresh();
        }, 3);
    }

    private function createRequest(
        int $paymentIntentId,
        float $amount,
        string $reason,
        ?int $adminId,
        ?int $organizerId,
        string $status,
        ?int $cancellationRequestId = null,
        ?int $refundDisputeId = null,
        bool $allowCheckedIn = false
    ): PaymentRefund {
        return DB::transaction(function () use (
            $paymentIntentId,
            $amount,
            $reason,
            $adminId,
            $organizerId,
            $status,
            $cancellationRequestId,
            $refundDisputeId,
            $allowCheckedIn
        ) {
            $intent = PaymentIntent::whereKey($paymentIntentId)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($intent->booking_id)->lockForUpdate()->firstOrFail();
            $this->assertRefundablePayment($intent, $booking, $allowCheckedIn);
            if ($organizerId !== null && (int) $booking->organizer_id !== $organizerId) {
                throw new RuntimeException('The organizer does not own this booking.');
            }

            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw new RuntimeException('Refund amount must be greater than zero.');
            }

            $hasInFlightRefund = PaymentRefund::where('payment_intent_id', $intent->id)
                ->whereIn('status', self::IN_FLIGHT_STATUSES)
                ->exists();
            if ($hasInFlightRefund) {
                throw new RuntimeException('A refund for this payment is still in progress. Reconcile it before requesting another.');
            }

            $alreadyReserved = (float) PaymentRefund::where('payment_intent_id', $intent->id)
                ->whereIn('status', self::RESERVED_STATUSES)
                ->sum('amount');
            $remaining = round((float) $intent->amount - $alreadyReserved, 2);
            if ($amount > $remaining) {
                throw new RuntimeException('Refund exceeds the remaining refundable amount.');
            }

            $transaction = Transaction::where('gateway', $intent->gateway)
                ->where('gateway_reference', $intent->reference)
                ->where('booking_id', $booking->id)
                ->where('transcation_type', 1)
                ->lockForUpdate()
                ->first();
            if (!$transaction) {
                throw new RuntimeException(
                    'The payment sale ledger identity is missing or mismatched. Run the gateway ledger backfill and complete finance review before refunding.'
                );
            }

            $refundAttributes = [
                'public_id' => (string) Str::uuid(),
                'payment_intent_id' => $intent->id,
                'transaction_id' => $transaction->id,
                'booking_id' => $booking->id,
                'organizer_id' => $booking->organizer_id,
                'requested_by_admin_id' => $adminId,
                'requested_by_organizer_id' => $organizerId,
                'gateway' => $intent->gateway,
                'gateway_reference' => $intent->reference,
                'amount' => $amount,
                'currency' => strtoupper($intent->currency),
                'reason' => $reason,
                'status' => $status,
                'requested_at' => now(),
            ];
            if ($cancellationRequestId !== null) {
                $refundAttributes['event_cancellation_request_id'] = $cancellationRequestId;
            }
            if ($refundDisputeId !== null) {
                $refundAttributes['payment_refund_dispute_id'] = $refundDisputeId;
            }
            $refund = PaymentRefund::create($refundAttributes);

            AuditLogger::record(
                $organizerId === null ? 'refund.requested' : 'refund.organizer_requested',
                $refund,
                [],
                [
                'status' => $status,
                'amount' => $amount,
                'currency' => $refund->currency,
            ], [
                'payment_reference' => $intent->reference,
                'booking_id' => $booking->id,
            ]);

            return $refund;
        }, 3);
    }

    private function assertRefundablePayment(
        PaymentIntent $intent,
        Booking $booking,
        bool $allowCheckedIn = false
    ): void
    {
        if (!in_array($intent->gateway, self::SUPPORTED_GATEWAYS, true) || $intent->status !== 'completed') {
            throw new RuntimeException('Only completed payments from a refund-enabled gateway can be refunded here.');
        }

        if (!$allowCheckedIn && (
            (int) $booking->scan_status === 1 ||
            BookingTicket::where('booking_id', $booking->id)->where('status', 'checked_in')->exists()
        )) {
            throw new RuntimeException('A checked-in ticket cannot be refunded without a manual dispute review.');
        }
    }

    public function submit(
        PaymentRefund $refund,
        PaystackGatewayClient|FlutterwaveGatewayClient|null $client = null
    ): PaymentRefund
    {
        $claimed = DB::transaction(function () use ($refund) {
            $locked = PaymentRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'requested') {
                return false;
            }
            $locked->update(['status' => 'submitting', 'last_error' => null]);
            return true;
        }, 3);

        if (!$claimed) {
            return $refund->fresh();
        }

        try {
            $payload = $this->submitToGateway($refund->fresh(), $client);
            $data = $payload['data'] ?? [];
            $gatewayStatus = (string) ($data['status'] ?? 'pending');
            $refund->fresh()->update(['submitted_at' => now(), 'gateway_response' => $payload]);
            $result = $this->recordGatewayStatus($refund->fresh(), $gatewayStatus, (array) $data);

            AuditLogger::record('refund.submitted', $result, ['status' => 'submitting'], [
                'status' => $result->status,
                'gateway_status' => $gatewayStatus,
            ], ['gateway_refund_id' => $data['id'] ?? null]);
        } catch (ConnectionException|GatewayOutcomeUnknownException $exception) {
            $refund->fresh()->update([
                'status' => 'verification_required',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            $refund->fresh()->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        }

        return $refund->fresh();
    }

    public function handleWebhook(string $eventName, array $data): ?PaymentRefund
    {
        $reference = (string) ($data['transaction_reference'] ?? '');
        $amount = round(((float) ($data['amount'] ?? 0)) / 100, 2);
        if ($reference === '' || $amount <= 0) {
            return null;
        }

        $refund = PaymentRefund::where('gateway', 'paystack')
            ->where('gateway_reference', $reference)
            ->where('amount', $amount)
            ->whereNotIn('status', ['processed'])
            ->orderByDesc('id')
            ->first();
        if (!$refund) {
            return null;
        }

        $gatewayStatus = str_replace('refund.', '', $eventName);
        return $this->recordGatewayStatus($refund, $gatewayStatus, $data);
    }

    public function handleFlutterwaveWebhook(array $data): ?PaymentRefund
    {
        $refundId = (string) ($data['id'] ?? $data['refund_id'] ?? '');
        if ($refundId === '') {
            return null;
        }

        $refund = PaymentRefund::where('gateway', 'flutterwave')
            ->where('gateway_refund_id', $refundId)
            ->whereNotIn('status', ['processed'])
            ->first();
        if (!$refund) {
            return null;
        }

        $reportedAmount = $data['amount_refunded'] ?? $data['AmountRefunded'] ?? null;
        $reportedTransactionId = $data['tx_id'] ?? $data['transaction_id'] ?? $data['TransactionId'] ?? null;
        $intent = PaymentIntent::find($refund->payment_intent_id);
        $amountMismatch = $reportedAmount !== null
            && round((float) $reportedAmount, 2) !== round((float) $refund->amount, 2);
        $transactionMismatch = $reportedTransactionId !== null
            && $intent?->gateway_transaction_id
            && (string) $reportedTransactionId !== (string) $intent->gateway_transaction_id;
        if ($amountMismatch || $transactionMismatch) {
            app(IncidentAlertService::class)->alert(
                'flutterwave-refund-mismatch:' . $refund->id,
                'Flutterwave refund status did not match the controlled refund.',
                [
                    'refund_id' => $refund->public_id,
                    'amount_mismatch' => $amountMismatch,
                    'transaction_mismatch' => $transactionMismatch,
                ]
            );
            return null;
        }

        return $this->recordGatewayStatus($refund, (string) ($data['status'] ?? 'processing'), $data);
    }

    public function recoverUnknownPaystackSubmission(
        PaymentRefund $refund,
        ?PaystackGatewayClient $client = null
    ): PaymentRefund {
        $refund->refresh();
        if ($refund->gateway !== 'paystack'
            || $refund->gateway_refund_id
            || !in_array($refund->status, ['verification_required', 'submitting'], true)) {
            return $refund;
        }

        $intent = PaymentIntent::find($refund->payment_intent_id);
        $transactionId = trim((string) $intent?->gateway_transaction_id);
        if ($transactionId === '') {
            return $this->markPaystackRecoveryAttention(
                $refund,
                'The original Paystack transaction ID is unavailable for deterministic refund recovery.'
            );
        }

        $payload = ($client ?: new PaystackGatewayClient())->listRefundsForTransaction($transactionId);
        $matches = collect((array) ($payload['data'] ?? []))->filter(function ($candidate) use ($refund, $transactionId): bool {
            if (!is_array($candidate) || empty($candidate['id'])) {
                return false;
            }
            $reportedTransaction = data_get($candidate, 'transaction.id', $candidate['transaction'] ?? null);
            return ($reportedTransaction === null || (string) $reportedTransaction === $transactionId)
                && round(((float) ($candidate['amount'] ?? 0)) / 100, 2) === round((float) $refund->amount, 2)
                && strtoupper((string) ($candidate['currency'] ?? '')) === strtoupper($refund->currency);
        })->values();

        if ($matches->isEmpty()) {
            $refund->update([
                'status' => 'verification_required',
                'last_error' => 'Paystack has not returned a matching refund yet; reconciliation will retry.',
            ]);
            $this->alertIfPaystackRecoveryStale($refund->fresh());
            return $refund->fresh();
        }
        if ($matches->count() !== 1) {
            return $this->markPaystackRecoveryAttention(
                $refund,
                'Multiple Paystack refunds matched the controlled transaction, amount and currency.'
            );
        }

        $data = (array) $matches->first();
        return $this->recordGatewayStatus($refund, (string) ($data['status'] ?? 'pending'), $data);
    }

    public function recordGatewayStatus(PaymentRefund $refund, string $gatewayStatus, array $data): PaymentRefund
    {
        $refund->refresh();
        if ($refund->status === 'processed') {
            $this->applyAfterProviderSuccess($refund);
            return $refund->fresh();
        }

        if ($refund->gateway === 'paystack') {
            $reportedReference = (string) ($data['transaction_reference'] ?? data_get($data, 'transaction.reference', ''));
            $reportedAmount = $data['amount'] ?? null;
            $reportedCurrency = strtoupper((string) ($data['currency'] ?? ''));
            $reportedRefundId = (string) ($data['id'] ?? '');
            $mismatches = array_filter([
                'reference' => $reportedReference !== '' && $reportedReference !== $refund->gateway_reference,
                'amount' => $reportedAmount !== null
                    && round(((float) $reportedAmount) / 100, 2) !== round((float) $refund->amount, 2),
                'currency' => $reportedCurrency !== '' && $reportedCurrency !== strtoupper($refund->currency),
                'refund_id' => $reportedRefundId !== '' && $refund->gateway_refund_id
                    && $reportedRefundId !== (string) $refund->gateway_refund_id,
            ]);
            if ($mismatches !== []) {
                $refund->update([
                    'status' => 'verification_required',
                    'last_error' => 'Provider refund identity mismatch.',
                ]);
                app(IncidentAlertService::class)->alert(
                    'paystack-refund-mismatch:' . $refund->id,
                    'Paystack event refund status did not match the controlled refund.',
                    ['refund_id' => $refund->public_id, 'mismatches' => array_keys($mismatches)]
                );
                return $refund->fresh();
            }
        }

        $updated = DB::transaction(function () use ($refund, $gatewayStatus, $data) {
            $locked = PaymentRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'processed') {
                return $locked;
            }

            $localStatus = $this->localStatus($locked->gateway, $gatewayStatus);
            $before = $locked->status;
            $locked->update([
                'status' => $localStatus,
                'gateway_status' => $gatewayStatus,
                'gateway_response' => $data,
                'gateway_refund_id' => $data['refund_reference'] ?? $data['id'] ?? $locked->gateway_refund_id,
                'completed_at' => $localStatus === 'processed' ? now() : $locked->completed_at,
                'last_error' => $localStatus === 'failed' ? ($data['reason'] ?? 'Gateway refund failed.') : null,
            ]);

            if ($before !== $localStatus || $locked->wasChanged('gateway_status')) {
                AuditLogger::record('refund.status_changed', $locked, ['status' => $before], [
                    'status' => $localStatus,
                    'gateway_status' => $gatewayStatus,
                ]);
            }

            return $locked->fresh();
        }, 3);

        if ($updated->status === 'processed') {
            $this->applyAfterProviderSuccess($updated);
        }

        return $updated->fresh();
    }

    public function applyFinancialEffects(PaymentRefund $refund): void
    {
        DB::transaction(function () use ($refund) {
            $refund = PaymentRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'processed' || $refund->financial_applied_at) {
                return;
            }

            $intent = PaymentIntent::whereKey($refund->payment_intent_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($refund->booking_id)->lockForUpdate()->firstOrFail();
            $ratio = min(1, (float) $refund->amount / max(0.01, (float) $intent->amount));
            $organizerReversal = round(max(0, (float) $booking->price - (float) $booking->commission) * $ratio, 2);
            $platformReversal = $booking->organizer_id
                ? round(((float) $booking->tax + (float) $booking->commission) * $ratio, 2)
                : round((float) $refund->amount, 2);

            $preBalance = 0;
            $afterBalance = 0;
            $currency = \App\Support\TicaCurrency::normalize($booking->currencyText ?? null);
            $baseCurrency = \App\Support\TicaCurrency::isBase($currency);
            if ($booking->organizer_id) {
                if ($baseCurrency) {
                    $organizer = Organizer::whereKey($booking->organizer_id)->lockForUpdate()->first();
                    if ($organizer) {
                        $preBalance = (float) $organizer->amount;
                        $afterBalance = round($preBalance - $organizerReversal, 2);
                        $organizer->update(['amount' => $afterBalance]);
                    }
                } else {
                    // Refunds in another currency come out of that currency's balance.
                    [$preBalance, $afterBalance] = \App\Support\TicaCurrency::adjustBalance((int) $booking->organizer_id, $currency, -$organizerReversal);
                }
            }

            if ($baseCurrency) {
                $earning = app(PlatformEarningService::class)->lock();
                $earning->update([
                    'total_revenue' => round((float) $earning->total_revenue - (float) $refund->amount, 2),
                    'total_earning' => round((float) $earning->total_earning - $platformReversal, 2),
                ]);
            } else {
                \App\Support\TicaCurrency::addPlatformEarning($currency, -(float) $refund->amount, -$platformReversal);
            }

            $refundLedger = [
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'organizer_id' => $booking->organizer_id,
                'payment_status' => 'processed',
                'grand_total' => $refund->amount,
                'commission' => $platformReversal,
                'tax' => 0,
                'pre_balance' => $preBalance,
                'after_balance' => $afterBalance,
                'gateway_type' => 'online',
                'currency_symbol' => $booking->currencySymbol,
                'currency_symbol_position' => $booking->currencySymbolPosition,
            ];
            if (Schema::hasColumn('transactions', 'currency')) {
                $refundLedger['currency'] = $currency;
            }

            Transaction::firstOrCreate([
                'transcation_id' => $refund->public_id,
                'transcation_type' => 6,
                'payment_method' => ucfirst($refund->gateway) . ' Refund',
            ], $refundLedger);

            $newRefundedAmount = round((float) $booking->refunded_amount + (float) $refund->amount, 2);
            $fullyRefunded = $newRefundedAmount >= (float) $intent->amount;
            $booking->update([
                'refunded_amount' => $newRefundedAmount,
                'refund_status' => $fullyRefunded ? 'refunded' : 'partially_refunded',
            ]);
            if ($fullyRefunded) {
                BookingTicket::where('booking_id', $booking->id)
                    ->where('status', 'issued')
                    ->update(['status' => 'refunded', 'updated_at' => now()]);
            }

            $refund->update(['financial_applied_at' => now()]);
            AuditLogger::record('refund.financial_effects_applied', $refund, [], [
                'amount' => (float) $refund->amount,
                'organizer_reversal' => $organizerReversal,
                'platform_reversal' => $platformReversal,
            ], ['booking_id' => $booking->id]);

            $refundId = (int) $refund->id;
            DB::afterCommit(function () use ($refundId): void {
                if (!Schema::hasTable('application_mail_deliveries')) return;
                $issued = PaymentRefund::with('booking.event')->find($refundId);
                $booking = $issued?->booking;
                if (!$issued || !$booking) return;

                app(LifecycleMailService::class)->queueSafely(
                    LifecycleMailCatalog::BUYER_REFUND_ISSUED,
                    $booking->email,
                    [
                        'event_name' => $booking->event->title ?? 'your event',
                        'refund_amount' => strtoupper((string) $issued->currency) . ' ' . number_format((float) $issued->amount, 2),
                        'refund_reference' => $issued->gateway_refund_id ?: $issued->public_id,
                        'expected_by' => now()->addBusinessDays(10)->format('D, j M Y'),
                        'order_url' => url('/customer/booking/details/' . $booking->id),
                    ],
                    $issued
                );
            });
        }, 3);
    }

    private function markPaystackRecoveryAttention(PaymentRefund $refund, string $message): PaymentRefund
    {
        $refund->update(['status' => 'needs_attention', 'last_error' => $message]);
        app(IncidentAlertService::class)->alert(
            'refund-recovery:' . $refund->id,
            'A Paystack event refund could not be recovered deterministically.',
            ['refund_id' => $refund->public_id, 'failure_recorded' => filled($message)],
            'critical'
        );
        return $refund->fresh();
    }

    private function applyAfterProviderSuccess(PaymentRefund $refund): void
    {
        try {
            $this->applyFinancialEffects($refund);
        } catch (Throwable $exception) {
            $refund->fresh()->update([
                'status' => 'needs_attention',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            app(IncidentAlertService::class)->alert(
                'refund-financial-apply:' . $refund->id,
                'A provider-confirmed event refund could not be applied locally.',
                ['refund_id' => $refund->public_id, 'booking_id' => $refund->booking_id],
                'critical'
            );
        }
    }

    private function alertIfPaystackRecoveryStale(PaymentRefund $refund): void
    {
        $minutes = (int) config('operations.refund_recovery_alert_minutes', 30);
        if (!$refund->requested_at || $refund->requested_at->isAfter(now()->subMinutes($minutes))) {
            return;
        }
        app(IncidentAlertService::class)->alert(
            'refund-recovery-stale:' . $refund->id,
            'A Paystack event refund remains indeterminate after provider reconciliation.',
            ['refund_id' => $refund->public_id, 'age_minutes' => $refund->requested_at->diffInMinutes(now())],
            'critical'
        );
    }

    private function submitToGateway(
        PaymentRefund $refund,
        PaystackGatewayClient|FlutterwaveGatewayClient|null $client
    ): array {
        if ($refund->gateway === 'paystack') {
            if ($client && !$client instanceof PaystackGatewayClient) {
                throw new RuntimeException('The selected refund client does not match Paystack.');
            }

            return ($client ?: new PaystackGatewayClient())->createRefund(
                $refund->gateway_reference,
                $refund->amount,
                $refund->currency,
                $refund->reason
            );
        }

        if ($refund->gateway === 'flutterwave') {
            if ($client && !$client instanceof FlutterwaveGatewayClient) {
                throw new RuntimeException('The selected refund client does not match Flutterwave.');
            }

            $flutterwave = $client ?: new FlutterwaveGatewayClient();
            $intent = PaymentIntent::findOrFail($refund->payment_intent_id);
            $transactionId = (int) $intent->gateway_transaction_id;
            if ($transactionId < 1) {
                $verification = $flutterwave->verifyByReference($intent->reference);
                $payment = (array) ($verification['data'] ?? []);
                if (strtolower((string) ($payment['status'] ?? '')) !== 'successful') {
                    throw new RuntimeException('Flutterwave no longer reports the original payment as successful.');
                }
                $errors = FlutterwavePaymentVerifier::errors($payment, $intent);
                if ($errors) {
                    throw new RuntimeException('Flutterwave payment verification mismatch: ' . implode(', ', $errors));
                }
                $transactionId = (int) ($payment['id'] ?? 0);
                if ($transactionId < 1) {
                    throw new RuntimeException('Flutterwave did not return the original provider transaction ID.');
                }
                $intent->forceFill([
                    'gateway_transaction_id' => (string) $transactionId,
                    'last_verified_at' => now(),
                ])->save();
            }

            return $flutterwave->createRefund($transactionId, $refund->amount, $refund->reason);
        }

        throw new RuntimeException('This payment gateway does not support automated refunds.');
    }

    private function localStatus(string $gateway, string $gatewayStatus): string
    {
        $gatewayStatus = strtolower($gatewayStatus);
        if ($gateway === 'flutterwave') {
            return match ($gatewayStatus) {
                'completed-bank-transfer', 'completed-momo', 'completed-mpgs',
                'completed-offline', 'completed-preauth', 'succeeded', 'successful' => 'processed',
                'failed', 'cancelled' => 'failed',
                'requires_action', 'needs-attention', 'needs_attention' => 'needs_attention',
                'completed', 'new', 'pending', 'pending-momo', 'pending-bank-transfer',
                'pending-mpgs', 'processing' => 'processing',
                default => 'pending',
            };
        }

        return match (strtolower($gatewayStatus)) {
            'processed' => 'processed',
            'processing' => 'processing',
            'needs-attention', 'needs_attention' => 'needs_attention',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
