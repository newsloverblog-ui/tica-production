<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\EventCancellationRequest;
use App\Models\EventCancellationNotification;
use App\Models\EventCancellationCase;
use App\Models\EventGuestInvitation;
use App\Models\EventRegistrationSubmission;
use App\Models\EventRegistrationNotification;
use App\Models\EventRegistrationAllocation;
use App\Models\EventRegistrationAllocationClaim;
use App\Models\EventRegistrationSession;
use App\Models\EventRegistrationSessionSelection;
use App\Models\EventRegistrationInvoice;
use App\Models\PaymentIntent;
use App\Services\PaidBookingFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class EventCancellationService
{
    private const ACTIVE_REQUEST_STATUSES = ['approval_pending', 'approved'];

    public function requestByOrganizer(
        int $eventId,
        int $organizerId,
        string $reason,
        ?string $customerMessage = null
    ): EventCancellationRequest {
        return $this->createRequest($eventId, $reason, $customerMessage, $organizerId, null);
    }

    public function requestByAdmin(
        int $eventId,
        int $adminId,
        string $reason,
        ?string $customerMessage = null
    ): EventCancellationRequest {
        return $this->createRequest($eventId, $reason, $customerMessage, null, $adminId);
    }

    public function approve(int $requestId, int $adminId): EventCancellationRequest
    {
        $notificationIds = [];
        $registrationNotificationIds = [];
        $result = DB::transaction(function () use ($requestId, $adminId, &$notificationIds, &$registrationNotificationIds) {
            $request = EventCancellationRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ($request->status === 'approved') {
                return $request;
            }
            if ($request->status !== 'approval_pending') {
                throw new RuntimeException('Only a pending cancellation request can be approved.');
            }

            $event = Event::whereKey($request->event_id)->lockForUpdate()->firstOrFail();
            if (($event->lifecycle_status ?: 'scheduled') !== 'cancellation_pending') {
                throw new RuntimeException('The event is not awaiting cancellation approval.');
            }

            $impact = $this->queueRefunds($request, $adminId);
            $event->forceFill([
                'status' => 0,
                'lifecycle_status' => 'cancelled',
                'sales_closed_at' => $event->sales_closed_at ?: now(),
                'cancelled_at' => now(),
                'cancellation_reason' => $request->reason,
            ])->save();

            $cancelledGuestInvitations = 0;
            if (Schema::hasTable('event_guest_invitations')) {
                EventGuestInvitation::where('event_id', $event->id)
                    ->whereNull('checked_in_at')
                    ->whereIn('status', ['pending', 'accepted'])
                    ->whereIn('delivery_status', ['pending', 'processing', 'failed'])
                    ->update([
                        'delivery_status' => 'suppressed',
                        'dispatch_token' => null,
                        'processing_at' => null,
                        'updated_at' => now(),
                    ]);
                $cancelledGuestInvitations = EventGuestInvitation::where('event_id', $event->id)
                    ->whereNull('checked_in_at')
                    ->whereIn('status', ['pending', 'accepted'])
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            $cancelledRegistrations = 0;
            if (Schema::hasTable('event_registration_submissions')) {
                $registrationIds = EventRegistrationSubmission::where('event_id', $event->id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->orderBy('id')
                    ->pluck('id');
                $cancelledRegistrations = EventRegistrationSubmission::whereIn('id', $registrationIds->all())
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' => now(),
                    ]);
                if (Schema::hasTable('event_registration_notifications')) {
                    foreach ($registrationIds as $submissionId) {
                        $notification = EventRegistrationNotification::firstOrCreate([
                            'submission_id' => $submissionId,
                            'kind' => 'cancelled',
                        ], [
                            'event_id' => $event->id,
                            'status' => 'pending',
                        ]);
                        $registrationNotificationIds[] = $notification->id;
                    }
                }
            }
            if (Schema::hasTable('event_registration_invoices')) {
                EventRegistrationInvoice::where('event_id', $event->id)
                    ->where('status', 'issued')
                    ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            }

            if (Schema::hasTable('event_registration_allocations')) {
                $allocationIds = EventRegistrationAllocation::where('event_id', $event->id)
                    ->where('status', 'active')->pluck('id');
                EventRegistrationAllocation::whereIn('id', $allocationIds)->update([
                    'status' => 'cancelled', 'cancelled_at' => now(), 'delivery_status' => 'suppressed',
                    'processing_at' => null, 'updated_at' => now(),
                ]);
                if (Schema::hasTable('event_registration_allocation_claims')) {
                    EventRegistrationAllocationClaim::whereIn('allocation_id', $allocationIds)
                        ->where('status', 'pending')->update([
                            'status' => 'cancelled', 'cancelled_at' => now(), 'delivery_status' => 'suppressed',
                            'processing_at' => null, 'updated_at' => now(),
                        ]);
                }
            }

            if (Schema::hasTable('event_registration_sessions')) {
                EventRegistrationSession::where('event_id', $event->id)->where('status', 'published')->update([
                    'status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now(),
                ]);
                if (Schema::hasTable('event_registration_session_selections')) {
                    EventRegistrationSessionSelection::whereIn('session_id',
                        EventRegistrationSession::where('event_id', $event->id)->pluck('id')
                    )->whereNull('cancelled_at')->update([
                        'cancelled_at' => now(), 'cancellation_reason' => 'event_cancelled', 'updated_at' => now(),
                    ]);
                }
            }

            $request->forceFill(array_merge($impact, [
                'status' => 'approved',
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]))->save();

            foreach (Booking::where('event_id', $event->id)->orderBy('id')->pluck('id') as $bookingId) {
                $notification = EventCancellationNotification::firstOrCreate([
                    'event_cancellation_request_id' => $request->id,
                    'booking_id' => $bookingId,
                ], ['status' => 'pending']);
                $notificationIds[] = $notification->id;
            }
            AuditLogger::record('event.cancellation_approved', $event, [
                'lifecycle_status' => 'cancellation_pending',
            ], [
                'lifecycle_status' => 'cancelled',
                'queued_refund_count' => $impact['queued_refund_count'],
                'queued_refund_amount' => $impact['queued_refund_amount'],
                'manual_review_count' => $impact['manual_review_count'],
                'manual_review_amount' => $impact['manual_review_amount'],
                'cancelled_guest_invitation_count' => $cancelledGuestInvitations,
                'cancelled_registration_count' => $cancelledRegistrations,
            ], ['cancellation_request_id' => $request->id]);

            return $request->fresh();
        }, 3);

        foreach ($notificationIds as $notificationId) {
            app(EventCancellationNotificationDispatchService::class)->dispatchNotice((int) $notificationId);
        }
        foreach ($registrationNotificationIds as $notificationId) {
            app(EventRegistrationNotificationDispatchService::class)
                ->dispatchNotification((int) $notificationId);
        }
        if ((int) $result->manual_review_count > 0) {
            app(IncidentAlertService::class)->alert(
                'event-cancellation-manual-review:' . $result->id,
                'Cancelled event payments require manual finance review.',
                [
                    'event_id' => $result->event_id,
                    'cancellation_request_id' => $result->id,
                    'booking_count' => (int) $result->manual_review_count,
                    'amount' => (float) $result->manual_review_amount,
                ],
                'critical'
            );
        }

        return $result;
    }

    public function reject(int $requestId, int $adminId, string $reason): EventCancellationRequest
    {
        $bookingIds = [];
        $result = DB::transaction(function () use ($requestId, $adminId, $reason, &$bookingIds) {
            $request = EventCancellationRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ($request->status === 'rejected') {
                return $request;
            }
            if ($request->status !== 'approval_pending') {
                throw new RuntimeException('Only a pending cancellation request can be rejected.');
            }

            $event = Event::whereKey($request->event_id)->lockForUpdate()->firstOrFail();
            $event->forceFill([
                'status' => $request->event_status_before_request ? 1 : 0,
                'lifecycle_status' => 'scheduled',
                'sales_closed_at' => null,
                'cancellation_reason' => null,
            ])->save();
            $request->forceFill([
                'status' => 'rejected',
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();
            $bookingIds = Booking::where('event_id', $event->id)
                ->whereIn('paymentStatus', ['1', 'paid', 'completed'])
                ->pluck('id')
                ->all();

            AuditLogger::record('event.cancellation_rejected', $event, [
                'lifecycle_status' => 'cancellation_pending',
            ], [
                'lifecycle_status' => 'scheduled',
                'status' => (int) $event->status,
            ], ['cancellation_request_id' => $request->id, 'reason' => $reason]);

            return $request->fresh();
        }, 3);

        foreach ($bookingIds as $bookingId) {
            app(PaidBookingFulfillmentService::class)->dispatch((int) $bookingId);
        }

        return $result;
    }

    public function queueLateCompletedBooking(int $bookingId): ?EventCancellationRequest
    {
        $notificationId = null;
        $result = DB::transaction(function () use ($bookingId, &$notificationId) {
            $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $event = Event::whereKey($booking->event_id)->lockForUpdate()->firstOrFail();
            if (($event->lifecycle_status ?: 'scheduled') !== 'cancelled') {
                return null;
            }

            $request = EventCancellationRequest::where('event_id', $event->id)
                ->where('status', 'approved')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if (!$request || !$request->reviewed_by_admin_id) {
                throw new RuntimeException('The cancelled event does not have an approved cancellation ledger.');
            }

            $intent = PaymentIntent::where('booking_id', $booking->id)
                ->where('status', 'completed')
                ->whereIn('gateway', ['paystack', 'flutterwave'])
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if (!$intent) {
                return $request;
            }

            $remaining = app(PaymentRefundService::class)->remainingRefundableAmount($intent);
            if ($remaining > 0 && !$this->isCheckedIn($booking)) {
                try {
                    $refund = app(PaymentRefundService::class)->requestForEventCancellation(
                        $intent->id,
                        $remaining,
                        'Refund for payment completed after event cancellation #' . $event->id . ': ' . $request->reason,
                        (int) $request->reviewed_by_admin_id,
                        $request->id
                    );
                    EventCancellationCase::updateOrCreate([
                        'event_cancellation_request_id' => $request->id,
                        'booking_id' => $booking->id,
                    ], [
                        'payment_intent_id' => $intent->id,
                        'payment_refund_id' => $refund->id,
                        'status' => 'refund_queued',
                        'amount' => $remaining,
                        'reason' => 'Late completed payment queued to the original payment gateway.',
                    ]);
                } catch (RuntimeException $exception) {
                    EventCancellationCase::updateOrCreate([
                        'event_cancellation_request_id' => $request->id,
                        'booking_id' => $booking->id,
                    ], [
                        'payment_intent_id' => $intent->id,
                        'payment_refund_id' => null,
                        'status' => 'manual_review',
                        'amount' => $remaining,
                        'reason' => mb_substr($exception->getMessage(), 0, 2000),
                    ]);
                    app(IncidentAlertService::class)->alert(
                        'cancelled-event-late-payment:' . $intent->id,
                        'A payment completed after event cancellation and requires finance review.',
                        [
                            'event_id' => $event->id,
                            'booking_id' => $booking->id,
                            'payment_intent_id' => $intent->id,
                            'exception_type' => $exception::class,
                            'exception_code' => $exception->getCode(),
                        ],
                        'critical'
                    );
                }
            }

            $this->refreshApprovedImpact($request);
            $notification = EventCancellationNotification::firstOrCreate([
                'event_cancellation_request_id' => $request->id,
                'booking_id' => $booking->id,
            ], ['status' => 'pending']);
            if ($notification->status === 'sent') {
                $notification->forceFill([
                    'status' => 'pending',
                    'processing_at' => null,
                    'sent_at' => null,
                    'last_error' => null,
                ])->save();
            }
            $notificationId = $notification->id;

            AuditLogger::record('event.cancellation_late_payment_reviewed', $request, [], [
                'booking_id' => $booking->id,
                'payment_intent_id' => $intent->id,
                'remaining_refundable_amount' => $remaining,
            ]);

            return $request->fresh();
        }, 3);

        if ($notificationId) {
            app(EventCancellationNotificationDispatchService::class)->dispatchNotice((int) $notificationId);
        }

        return $result;
    }

    private function createRequest(
        int $eventId,
        string $reason,
        ?string $customerMessage,
        ?int $organizerId,
        ?int $adminId
    ): EventCancellationRequest {
        return DB::transaction(function () use ($eventId, $reason, $customerMessage, $organizerId, $adminId) {
            $event = Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
            if ($organizerId !== null && (int) $event->organizer_id !== $organizerId) {
                throw new RuntimeException('The organizer does not own this event.');
            }
            if (($event->lifecycle_status ?: 'scheduled') !== 'scheduled') {
                throw new RuntimeException('Only a scheduled event can enter the cancellation workflow.');
            }
            if (EventCancellationRequest::where('event_id', $event->id)
                ->whereIn('status', self::ACTIVE_REQUEST_STATUSES)
                ->lockForUpdate()
                ->first()) {
                throw new RuntimeException('This event already has an active cancellation request.');
            }

            $impact = $this->estimateImpact($event->id);
            $request = EventCancellationRequest::create(array_merge($impact, [
                'public_id' => (string) Str::uuid(),
                'event_id' => $event->id,
                'organizer_id' => $event->organizer_id,
                'requested_by_organizer_id' => $organizerId,
                'requested_by_admin_id' => $adminId,
                'status' => 'approval_pending',
                'event_status_before_request' => (bool) $event->status,
                'reason' => $reason,
                'customer_message' => $customerMessage,
                'requested_at' => now(),
            ]));

            $event->forceFill([
                'status' => 0,
                'lifecycle_status' => 'cancellation_pending',
                'sales_closed_at' => now(),
            ])->save();
            app(TicketReservationService::class)->releaseForEvent($event->id, 'event_cancellation_pending');

            AuditLogger::record('event.cancellation_requested', $event, [
                'lifecycle_status' => 'scheduled',
            ], [
                'lifecycle_status' => 'cancellation_pending',
                'status' => 0,
            ], [
                'cancellation_request_id' => $request->id,
                'eligible_refund_count' => $impact['eligible_refund_count'],
                'manual_review_count' => $impact['manual_review_count'],
            ]);

            return $request;
        }, 3);
    }

    private function estimateImpact(int $eventId): array
    {
        $paidBookings = Booking::where('event_id', $eventId)
            ->whereIn('paymentStatus', ['1', 'paid', 'completed'])
            ->get()
            ->keyBy('id');
        $intents = PaymentIntent::where('event_id', $eventId)
            ->where('status', 'completed')
            ->whereNotNull('booking_id')
            ->orderBy('id')
            ->get();
        $eligibleCount = 0;
        $eligibleAmount = 0.0;
        $handled = [];

        foreach ($intents as $intent) {
            $booking = $paidBookings->get((int) $intent->booking_id);
            if (!$booking || isset($handled[$booking->id])) {
                continue;
            }
            $remaining = app(PaymentRefundService::class)->remainingRefundableAmount($intent);
            if ($remaining <= 0) {
                $handled[$booking->id] = true;
                continue;
            }
            if (in_array($intent->gateway, ['paystack', 'flutterwave'], true)
                && !$this->isCheckedIn($booking)) {
                $eligibleCount++;
                $eligibleAmount += $remaining;
                $handled[$booking->id] = true;
            }
        }

        $manual = $paidBookings->reject(fn (Booking $booking) => isset($handled[$booking->id]));
        return [
            'eligible_refund_count' => $eligibleCount,
            'eligible_refund_amount' => round($eligibleAmount, 2),
            'manual_review_count' => $manual->count(),
            'manual_review_amount' => round($manual->sum(fn (Booking $booking) => $this->bookingAmount($booking)), 2),
        ];
    }

    private function queueRefunds(EventCancellationRequest $request, int $adminId): array
    {
        $paidBookings = Booking::where('event_id', $request->event_id)
            ->whereIn('paymentStatus', ['1', 'paid', 'completed'])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $intents = PaymentIntent::where('event_id', $request->event_id)
            ->where('status', 'completed')
            ->whereNotNull('booking_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $queuedCount = 0;
        $queuedAmount = 0.0;
        $handled = [];
        $classifications = [];

        foreach ($intents as $intent) {
            $booking = $paidBookings->get((int) $intent->booking_id);
            if (!$booking || isset($handled[$booking->id])) {
                continue;
            }
            $remaining = app(PaymentRefundService::class)->remainingRefundableAmount($intent);
            if ($remaining <= 0) {
                $handled[$booking->id] = true;
                $classifications[$booking->id] = [
                    'payment_intent_id' => $intent->id,
                    'payment_refund_id' => null,
                    'status' => 'already_refunded',
                    'amount' => 0,
                    'reason' => 'No refundable balance remains on the verified payment.',
                ];
                continue;
            }
            if (!in_array($intent->gateway, ['paystack', 'flutterwave'], true) || $this->isCheckedIn($booking)) {
                $classifications[$booking->id] = [
                    'payment_intent_id' => $intent->id,
                    'payment_refund_id' => null,
                    'status' => 'manual_review',
                    'amount' => $remaining,
                    'reason' => $this->isCheckedIn($booking)
                        ? 'One or more ticket units were checked in.'
                        : 'The payment gateway does not support controlled automated refunds.',
                ];
                continue;
            }

            try {
                $refund = app(PaymentRefundService::class)->requestForEventCancellation(
                    $intent->id,
                    $remaining,
                    'Full refund for cancelled event #' . $request->event_id . ': ' . $request->reason,
                    $adminId,
                    $request->id
                );
                $queuedCount++;
                $queuedAmount += $remaining;
                $handled[$booking->id] = true;
                $classifications[$booking->id] = [
                    'payment_intent_id' => $intent->id,
                    'payment_refund_id' => $refund->id,
                    'status' => 'refund_queued',
                    'amount' => $remaining,
                    'reason' => 'Full remaining balance queued to the original payment gateway.',
                ];
            } catch (RuntimeException $exception) {
                $classifications[$booking->id] = [
                    'payment_intent_id' => $intent->id,
                    'payment_refund_id' => null,
                    'status' => 'manual_review',
                    'amount' => $remaining,
                    'reason' => mb_substr($exception->getMessage(), 0, 2000),
                ];
            }
        }

        $manual = $paidBookings->reject(fn (Booking $booking) => isset($handled[$booking->id]));
        foreach ($paidBookings as $booking) {
            $classification = $classifications[$booking->id] ?? [
                'payment_intent_id' => null,
                'payment_refund_id' => null,
                'status' => 'manual_review',
                'amount' => $this->bookingAmount($booking),
                'reason' => 'No completed hardened payment intent and exact sale-ledger identity were found.',
            ];
            EventCancellationCase::updateOrCreate([
                'event_cancellation_request_id' => $request->id,
                'booking_id' => $booking->id,
            ], $classification);
        }
        return [
            'eligible_refund_count' => $queuedCount,
            'eligible_refund_amount' => round($queuedAmount, 2),
            'queued_refund_count' => $queuedCount,
            'queued_refund_amount' => round($queuedAmount, 2),
            'manual_review_count' => $manual->count(),
            'manual_review_amount' => round($manual->sum(fn (Booking $booking) => $this->bookingAmount($booking)), 2),
        ];
    }

    private function isCheckedIn(Booking $booking): bool
    {
        return (int) $booking->scan_status === 1
            || BookingTicket::where('booking_id', $booking->id)->where('status', 'checked_in')->exists();
    }

    private function bookingAmount(Booking $booking): float
    {
        return max(0, round((float) $booking->price + (float) $booking->tax - (float) $booking->refunded_amount, 2));
    }

    private function refreshApprovedImpact(EventCancellationRequest $request): void
    {
        $queued = $request->cases()->where('status', 'refund_queued')->get();
        $manual = $request->cases()->where('status', 'manual_review')->get();

        $request->forceFill([
            'eligible_refund_count' => $queued->count(),
            'eligible_refund_amount' => round((float) $queued->sum('amount'), 2),
            'queued_refund_count' => $queued->count(),
            'queued_refund_amount' => round((float) $queued->sum('amount'), 2),
            'manual_review_count' => $manual->count(),
            'manual_review_amount' => round((float) $manual->sum('amount'), 2),
        ])->save();
    }
}
