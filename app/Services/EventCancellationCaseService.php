<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\EventCancellationCase;
use App\Models\EventCancellationNotification;
use App\Models\EventCancellationRequest;
use App\Models\Organizer;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundDispute;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class EventCancellationCaseService
{
    public function openCheckedInDispute(
        int $caseId,
        int $adminId,
        string $category,
        string $description
    ): EventCancellationCase {
        return DB::transaction(function () use ($caseId, $adminId, $category, $description) {
            $case = EventCancellationCase::whereKey($caseId)->lockForUpdate()->firstOrFail();
            $this->assertActiveManualCase($case);
            if (!$case->payment_intent_id) {
                throw new RuntimeException('This cancellation case has no durable payment intent for dispute review.');
            }

            $booking = Booking::whereKey($case->booking_id)->lockForUpdate()->firstOrFail();
            $checkedIn = (int) $booking->scan_status === 1
                || BookingTicket::where('booking_id', $booking->id)->where('status', 'checked_in')->exists();
            if (!$checkedIn) {
                throw new RuntimeException('Only a checked-in cancellation case can enter the refund-dispute workflow.');
            }

            $dispute = app(PaymentRefundDisputeService::class)->openByAdmin(
                (int) $case->payment_intent_id,
                $adminId,
                (float) $case->amount,
                $category,
                $description
            );
            $case->forceFill([
                'status' => 'dispute_open',
                'payment_refund_dispute_id' => $dispute->id,
                'resolved_by_admin_id' => $adminId,
                'resolution' => 'Escalated to checked-in refund dispute '.$dispute->public_id.'.',
            ])->save();
            $this->refreshImpact((int) $case->event_cancellation_request_id);

            AuditLogger::record('event.cancellation_case_dispute_opened', $case, [
                'status' => 'manual_review',
            ], [
                'status' => 'dispute_open',
                'payment_refund_dispute_id' => $dispute->id,
            ]);

            return $case->fresh();
        }, 3);
    }

    public function resolveWithoutRefund(int $caseId, int $adminId, string $resolution): EventCancellationCase
    {
        return DB::transaction(function () use ($caseId, $adminId, $resolution) {
            $case = EventCancellationCase::whereKey($caseId)->lockForUpdate()->firstOrFail();
            $this->assertActiveManualCase($case);
            $resolution = $this->validatedResolution($resolution);

            $case->forceFill([
                'status' => 'resolved_no_refund',
                'resolved_by_admin_id' => $adminId,
                'resolution' => $resolution,
                'resolved_at' => now(),
            ])->save();
            $this->refreshImpact((int) $case->event_cancellation_request_id);

            AuditLogger::record('event.cancellation_case_resolved_without_refund', $case, [
                'status' => 'manual_review',
            ], [
                'status' => 'resolved_no_refund',
                'resolved_by_admin_id' => $adminId,
            ]);

            $this->queueOutcomeNotice($case);

            return $case->fresh();
        }, 3);
    }

    public function retryControlledRefund(int $caseId, int $adminId): EventCancellationCase
    {
        return DB::transaction(function () use ($caseId, $adminId) {
            $case = EventCancellationCase::whereKey($caseId)->lockForUpdate()->firstOrFail();
            $this->assertActiveManualCase($case);
            if (!$case->payment_intent_id) {
                throw new RuntimeException('This cancellation case has no durable payment intent.');
            }
            $booking = Booking::whereKey($case->booking_id)->lockForUpdate()->firstOrFail();
            if ((int) $booking->scan_status === 1
                || BookingTicket::where('booking_id', $booking->id)->where('status', 'checked_in')->exists()) {
                throw new RuntimeException('A checked-in cancellation case must use the refund-dispute workflow.');
            }
            $intent = PaymentIntent::whereKey($case->payment_intent_id)->lockForUpdate()->firstOrFail();
            if ($intent->status !== 'completed' || !in_array($intent->gateway, ['paystack', 'flutterwave'], true)) {
                throw new RuntimeException('This payment is not eligible for a controlled provider refund.');
            }
            $request = EventCancellationRequest::whereKey($case->event_cancellation_request_id)
                ->lockForUpdate()->firstOrFail();
            $remaining = app(PaymentRefundService::class)->remainingRefundableAmount($intent);
            if ($remaining <= 0) {
                throw new RuntimeException('No refundable provider balance remains.');
            }

            $refund = app(PaymentRefundService::class)->requestForEventCancellation(
                $intent->id,
                $remaining,
                'Reconciled full refund for cancelled event #'.$request->event_id.': '.$request->reason,
                $adminId,
                $request->id
            );
            $case->forceFill([
                'status' => 'refund_queued',
                'payment_refund_id' => $refund->id,
                'resolved_by_admin_id' => $adminId,
                'resolution' => 'Controlled provider identity was repaired and the remaining refund was queued.',
                'resolved_at' => now(),
            ])->save();
            $this->refreshImpact((int) $case->event_cancellation_request_id);

            AuditLogger::record('event.cancellation_case_provider_refund_retried', $case, [
                'status' => 'manual_review',
            ], [
                'status' => 'refund_queued',
                'payment_refund_id' => $refund->id,
                'amount' => $remaining,
            ]);

            $this->queueOutcomeNotice($case);

            return $case->fresh();
        }, 3);
    }

    public function recordExternalRefund(
        int $caseId,
        int $adminId,
        string $externalReference,
        string $resolution
    ): EventCancellationCase {
        return DB::transaction(function () use ($caseId, $adminId, $externalReference, $resolution) {
            $case = EventCancellationCase::whereKey($caseId)->lockForUpdate()->firstOrFail();
            $this->assertActiveManualCase($case);
            $resolution = $this->validatedResolution($resolution);
            $externalReference = strtoupper(trim($externalReference));
            if (!preg_match('/^[A-Z0-9][A-Z0-9._:\/-]{5,190}$/', $externalReference)) {
                throw new RuntimeException('Enter a valid unique external refund reference (6 to 191 characters).');
            }
            if (EventCancellationCase::where('external_refund_reference', $externalReference)
                ->where('id', '!=', $case->id)->exists()) {
                throw new RuntimeException('That external refund reference has already been recorded.');
            }

            $booking = Booking::whereKey($case->booking_id)->lockForUpdate()->firstOrFail();
            if ((int) $booking->scan_status === 1
                || BookingTicket::where('booking_id', $booking->id)->where('status', 'checked_in')->exists()) {
                throw new RuntimeException('A checked-in cancellation case must use the refund-dispute workflow.');
            }
            if ($case->payment_intent_id) {
                $intent = PaymentIntent::whereKey($case->payment_intent_id)->lockForUpdate()->first();
                if ($intent && $intent->status === 'completed'
                    && in_array($intent->gateway, ['paystack', 'flutterwave'], true)) {
                    throw new RuntimeException('Repair and use the controlled provider refund for this hardened payment.');
                }
            }
            if (PaymentRefund::where('booking_id', $booking->id)
                ->whereIn('status', ['approval_pending', 'requested', 'submitting', 'verification_required', 'pending', 'processing', 'needs_attention', 'processed'])
                ->exists()) {
                throw new RuntimeException('This booking already has a reserved or completed provider refund.');
            }

            $remaining = max(0, round((float) $booking->price + (float) $booking->tax - (float) $booking->refunded_amount, 2));
            $amount = round((float) $case->amount, 2);
            if ($amount <= 0 || $amount > $remaining) {
                throw new RuntimeException('The cancellation case amount exceeds the remaining booking balance.');
            }
            if (Transaction::where('gateway', 'external_refund')
                ->where('gateway_reference', $externalReference)->lockForUpdate()->exists()) {
                throw new RuntimeException('That external refund reference has already been used.');
            }

            $ratio = min(1, $amount / max(0.01, (float) $booking->price + (float) $booking->tax));
            $organizerReversal = round(max(0, (float) $booking->price - (float) $booking->commission) * $ratio, 2);
            $platformReversal = $booking->organizer_id
                ? round(((float) $booking->tax + (float) $booking->commission) * $ratio, 2)
                : $amount;
            $preBalance = 0.0;
            $afterBalance = 0.0;
            if ($booking->organizer_id) {
                $organizer = Organizer::whereKey($booking->organizer_id)->lockForUpdate()->first();
                if ($organizer) {
                    $preBalance = (float) $organizer->amount;
                    $afterBalance = round($preBalance - $organizerReversal, 2);
                    $organizer->update(['amount' => $afterBalance]);
                }
            }
            $earning = app(PlatformEarningService::class)->lock();
            $earning->update([
                'total_revenue' => round((float) $earning->total_revenue - $amount, 2),
                'total_earning' => round((float) $earning->total_earning - $platformReversal, 2),
            ]);

            Transaction::create([
                'transcation_id' => $externalReference,
                'gateway' => 'external_refund',
                'gateway_reference' => $externalReference,
                'booking_id' => $booking->id,
                'transcation_type' => 6,
                'payment_method' => 'Externally Verified Refund',
                'payment_status' => 'processed',
                'grand_total' => $amount,
                'commission' => $platformReversal,
                'tax' => 0,
                'pre_balance' => $preBalance,
                'after_balance' => $afterBalance,
                'gateway_type' => 'manual',
                'currency_symbol' => $booking->currencySymbol,
                'currency_symbol_position' => $booking->currencySymbolPosition,
            ]);

            $refundedAmount = round((float) $booking->refunded_amount + $amount, 2);
            $fullyRefunded = $refundedAmount >= round((float) $booking->price + (float) $booking->tax, 2);
            $booking->forceFill([
                'refunded_amount' => $refundedAmount,
                'refund_status' => $fullyRefunded ? 'refunded' : 'partially_refunded',
            ])->save();
            if ($fullyRefunded) {
                BookingTicket::where('booking_id', $booking->id)->where('status', 'issued')
                    ->update(['status' => 'refunded', 'updated_at' => now()]);
            }

            $case->forceFill([
                'status' => 'external_refund_recorded',
                'external_refund_reference' => $externalReference,
                'resolved_by_admin_id' => $adminId,
                'resolution' => $resolution,
                'resolved_at' => now(),
            ])->save();
            $this->refreshImpact((int) $case->event_cancellation_request_id);

            AuditLogger::record('event.cancellation_case_external_refund_recorded', $case, [
                'status' => 'manual_review',
            ], [
                'status' => 'external_refund_recorded',
                'amount' => $amount,
                'resolved_by_admin_id' => $adminId,
            ], ['external_reference' => $externalReference, 'booking_id' => $booking->id]);

            $this->queueOutcomeNotice($case);

            return $case->fresh();
        }, 3);
    }

    public function synchronizeFromDispute(PaymentRefundDispute $dispute): void
    {
        if (!Schema::hasTable('event_cancellation_cases')
            || !Schema::hasColumn('event_cancellation_cases', 'payment_refund_dispute_id')) {
            return;
        }
        $case = EventCancellationCase::where('payment_refund_dispute_id', $dispute->id)->first();
        if (!$case) {
            return;
        }

        DB::transaction(function () use ($case, $dispute) {
            $case = EventCancellationCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($dispute->status === PaymentRefundDispute::STATUS_REFUND_QUEUED) {
                $case->forceFill([
                    'status' => 'refund_queued',
                    'payment_refund_id' => $dispute->payment_refund_id,
                    'resolved_by_admin_id' => $dispute->reviewed_by_admin_id,
                    'resolution' => $dispute->resolution,
                    'resolved_at' => now(),
                ])->save();
            } elseif ($dispute->status === PaymentRefundDispute::STATUS_REJECTED) {
                $case->forceFill([
                    'status' => 'resolved_no_refund',
                    'resolved_by_admin_id' => $dispute->reviewed_by_admin_id,
                    'resolution' => $dispute->resolution,
                    'resolved_at' => now(),
                ])->save();
            }
            $this->refreshImpact((int) $case->event_cancellation_request_id);
            if (in_array($case->status, ['refund_queued', 'resolved_no_refund'], true)) {
                $this->queueOutcomeNotice($case);
            }
        }, 3);
    }

    private function assertActiveManualCase(EventCancellationCase $case): void
    {
        if ($case->status !== 'manual_review') {
            throw new RuntimeException('Only an unresolved manual-review cancellation case can be changed.');
        }
        $request = EventCancellationRequest::whereKey($case->event_cancellation_request_id)
            ->lockForUpdate()->firstOrFail();
        if ($request->status !== 'approved') {
            throw new RuntimeException('The event cancellation is not approved.');
        }
    }

    private function validatedResolution(string $resolution): string
    {
        $resolution = trim($resolution);
        if (mb_strlen($resolution) < 10 || mb_strlen($resolution) > 2000) {
            throw new RuntimeException('A 10 to 2000 character finance resolution is required.');
        }
        return $resolution;
    }

    private function refreshImpact(int $requestId): void
    {
        $request = EventCancellationRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
        $queued = $request->cases()->where('status', 'refund_queued')->get();
        $manual = $request->cases()->whereIn('status', ['manual_review', 'dispute_open'])->get();
        $request->forceFill([
            'eligible_refund_count' => $queued->count(),
            'eligible_refund_amount' => round((float) $queued->sum('amount'), 2),
            'queued_refund_count' => $queued->count(),
            'queued_refund_amount' => round((float) $queued->sum('amount'), 2),
            'manual_review_count' => $manual->count(),
            'manual_review_amount' => round((float) $manual->sum('amount'), 2),
        ])->save();
    }

    private function queueOutcomeNotice(EventCancellationCase $case): void
    {
        $notification = EventCancellationNotification::where(
            'event_cancellation_request_id',
            $case->event_cancellation_request_id
        )->where('booking_id', $case->booking_id)->lockForUpdate()->first();
        if (!$notification) {
            $notification = EventCancellationNotification::create([
                'event_cancellation_request_id' => $case->event_cancellation_request_id,
                'booking_id' => $case->booking_id,
                'status' => 'pending',
            ]);
        } elseif (in_array($notification->status, ['sent', 'failed'], true)) {
            $notification->forceFill([
                'status' => 'pending',
                'processing_at' => null,
                'sent_at' => null,
                'last_error' => null,
                'alerted_at' => null,
            ])->save();
        }

        app(EventCancellationNotificationDispatchService::class)->dispatchNotice((int) $notification->id);
    }
}
