<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundDispute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentRefundDisputeService
{
    private const ACTIVE_STATUSES = [
        PaymentRefundDispute::STATUS_OPEN,
        PaymentRefundDispute::STATUS_UNDER_REVIEW,
    ];

    public function __construct(private readonly PaymentRefundService $refunds)
    {
    }

    public function openForOrganizer(
        int $paymentIntentId,
        int $organizerId,
        float $amount,
        string $category,
        string $description
    ): PaymentRefundDispute {
        return $this->open($paymentIntentId, $amount, $category, $description, $organizerId, null);
    }

    public function openByAdmin(
        int $paymentIntentId,
        int $adminId,
        float $amount,
        string $category,
        string $description
    ): PaymentRefundDispute {
        return $this->open($paymentIntentId, $amount, $category, $description, null, $adminId);
    }

    private function open(
        int $paymentIntentId,
        float $amount,
        string $category,
        string $description,
        ?int $organizerId,
        ?int $adminId
    ): PaymentRefundDispute {
        return DB::transaction(function () use (
            $paymentIntentId, $amount, $category, $description, $organizerId, $adminId
        ) {
            $intent = PaymentIntent::whereKey($paymentIntentId)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($intent->booking_id)->lockForUpdate()->firstOrFail();
            if ($organizerId !== null && (int) $booking->organizer_id !== $organizerId) {
                throw new RuntimeException('The organizer does not own this booking.');
            }
            if (!in_array($intent->gateway, ['paystack', 'flutterwave'], true) || $intent->status !== 'completed') {
                throw new RuntimeException('Only a completed payment from a refund-enabled gateway can enter dispute review.');
            }
            if (!in_array($category, PaymentRefundDispute::CATEGORIES, true)) {
                throw new RuntimeException('Select a valid refund dispute category.');
            }

            $description = trim($description);
            if (mb_strlen($description) < 20 || mb_strlen($description) > 2000) {
                throw new RuntimeException('Describe the dispute in 20 to 2000 characters.');
            }

            $checkedInCount = BookingTicket::where('booking_id', $booking->id)
                ->where('status', 'checked_in')->count();
            if ($checkedInCount === 0 && (int) $booking->scan_status !== 1) {
                throw new RuntimeException('This payment has no checked-in admission and does not require dispute review.');
            }
            $checkedInCount = max($checkedInCount, (int) $booking->scan_status === 1 ? 1 : 0);

            $active = PaymentRefundDispute::where('payment_intent_id', $intent->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();
            if ($active) {
                throw new RuntimeException('This payment already has an active refund dispute.');
            }

            $amount = round($amount, 2);
            $remaining = $this->refunds->remainingRefundableAmount($intent);
            if ($amount <= 0 || $amount > $remaining) {
                throw new RuntimeException('The requested amount must be within the remaining refundable balance.');
            }

            $dispute = PaymentRefundDispute::create([
                'public_id' => (string) Str::uuid(),
                'payment_intent_id' => $intent->id,
                'booking_id' => $booking->id,
                'organizer_id' => $booking->organizer_id,
                'requested_by_organizer_id' => $organizerId,
                'requested_by_admin_id' => $adminId,
                'category' => $category,
                'description' => $description,
                'status' => PaymentRefundDispute::STATUS_OPEN,
                'requested_amount' => $amount,
                'currency' => strtoupper($intent->currency),
                'checked_in_ticket_count' => $checkedInCount,
                'opened_at' => now(),
            ]);

            AuditLogger::record('refund.dispute_opened', $dispute, [], [
                'status' => $dispute->status,
                'category' => $category,
                'requested_amount' => $amount,
            ], [
                'payment_intent_id' => $intent->id,
                'booking_id' => $booking->id,
                'checked_in_ticket_count' => $checkedInCount,
            ]);

            return $dispute;
        }, 3);
    }

    public function review(
        int $disputeId,
        int $adminId,
        string $status,
        ?string $resolution
    ): PaymentRefundDispute {
        return DB::transaction(function () use ($disputeId, $adminId, $status, $resolution) {
            $dispute = PaymentRefundDispute::whereKey($disputeId)->lockForUpdate()->firstOrFail();
            if (!in_array($dispute->status, self::ACTIVE_STATUSES, true)) {
                throw new RuntimeException('A closed refund dispute cannot be changed.');
            }
            if (!in_array($status, [
                PaymentRefundDispute::STATUS_UNDER_REVIEW,
                PaymentRefundDispute::STATUS_REJECTED,
            ], true)) {
                throw new RuntimeException('Select a valid dispute review status.');
            }

            $resolution = trim((string) $resolution);
            if ($status === PaymentRefundDispute::STATUS_REJECTED
                && (mb_strlen($resolution) < 10 || mb_strlen($resolution) > 2000)) {
                throw new RuntimeException('A 10 to 2000 character resolution is required to reject a dispute.');
            }
            if ($status === PaymentRefundDispute::STATUS_UNDER_REVIEW && mb_strlen($resolution) > 2000) {
                throw new RuntimeException('The review note may not exceed 2000 characters.');
            }

            $before = $dispute->status;
            $dispute->forceFill([
                'status' => $status,
                'resolution' => $resolution !== '' ? $resolution : null,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'closed_at' => $status === PaymentRefundDispute::STATUS_REJECTED ? now() : null,
            ])->save();

            AuditLogger::record('refund.dispute_reviewed', $dispute, ['status' => $before], [
                'status' => $status,
                'reviewed_by_admin_id' => $adminId,
            ], ['resolution_recorded' => $resolution !== '']);

            app(EventCancellationCaseService::class)->synchronizeFromDispute($dispute->fresh());

            return $dispute->fresh();
        }, 3);
    }

    public function approveAndQueue(
        int $disputeId,
        int $adminId,
        string $resolution
    ): PaymentRefundDispute {
        return DB::transaction(function () use ($disputeId, $adminId, $resolution) {
            $dispute = PaymentRefundDispute::whereKey($disputeId)->lockForUpdate()->firstOrFail();
            if ($dispute->status === PaymentRefundDispute::STATUS_REFUND_QUEUED && $dispute->payment_refund_id) {
                return $dispute;
            }
            if (!in_array($dispute->status, self::ACTIVE_STATUSES, true)) {
                throw new RuntimeException('Only an active refund dispute can be approved.');
            }

            $resolution = trim($resolution);
            if (mb_strlen($resolution) < 10 || mb_strlen($resolution) > 2000) {
                throw new RuntimeException('A 10 to 2000 character approval resolution is required.');
            }

            $refund = $this->refunds->requestFromApprovedDispute($dispute, $adminId);
            $before = $dispute->status;
            $dispute->forceFill([
                'status' => PaymentRefundDispute::STATUS_REFUND_QUEUED,
                'payment_refund_id' => $refund->id,
                'resolution' => $resolution,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'closed_at' => now(),
            ])->save();

            AuditLogger::record('refund.dispute_approved', $dispute, ['status' => $before], [
                'status' => PaymentRefundDispute::STATUS_REFUND_QUEUED,
                'reviewed_by_admin_id' => $adminId,
                'payment_refund_id' => $refund->id,
            ], ['provider_submission_status' => $refund->status]);

            app(EventCancellationCaseService::class)->synchronizeFromDispute($dispute->fresh());

            return $dispute->fresh();
        }, 3);
    }
}
