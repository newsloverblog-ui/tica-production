<?php

namespace App\Services;

use App\Jobs\ResendEventBookingTickets;
use App\Models\BookingTicketDeliveryRequest;
use App\Models\Event;
use App\Models\Event\Booking;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BookingTicketDeliveryService
{
    public function requestForCustomer(int $bookingId, int $customerId): BookingTicketDeliveryRequest
    {
        return $this->request($bookingId, 'customer', $customerId);
    }

    public function requestForOrganizer(int $bookingId, int $organizerId): BookingTicketDeliveryRequest
    {
        return $this->request($bookingId, 'organizer', $organizerId);
    }

    public function assertDeliverable(Booking $booking): void
    {
        if (!in_array((string) $booking->paymentStatus, ['completed', 'free', '1'], true)) {
            throw new DomainException('Tickets can only be resent for a completed or free booking.');
        }
        if ($booking->refund_status === 'refunded') {
            throw new DomainException('This booking has been fully refunded and its tickets are no longer active.');
        }

        $lifecycle = Event::query()->whereKey($booking->event_id)->value('lifecycle_status') ?: 'scheduled';
        if (in_array($lifecycle, ['cancelled', 'cancellation_pending'], true)) {
            throw new DomainException('Tickets cannot be resent while this event is cancelled or under cancellation review.');
        }
        $ticketUnits = $booking->ticketUnits();
        $activeBookingRecipientTicket = (clone $ticketUnits)
            ->where('status', 'issued')
            ->where(function ($query) use ($booking) {
                $query->whereNull('holder_customer_id');
                if ($booking->customer_id) {
                    $query->orWhere('holder_customer_id', $booking->customer_id);
                }
            })
            ->exists();
        if ((clone $ticketUnits)->exists() && !$activeBookingRecipientTicket) {
            throw new DomainException('This booking has no active tickets available for delivery.');
        }
        if (!filter_var($booking->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('The booking does not have a valid delivery email address.');
        }
    }

    private function request(int $bookingId, string $actorType, int $actorId): BookingTicketDeliveryRequest
    {
        if (!in_array($actorType, ['customer', 'organizer'], true)) {
            throw new DomainException('Unsupported ticket-delivery requester.');
        }

        $request = DB::transaction(function () use ($bookingId, $actorType, $actorId) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);
            $ownerColumn = $actorType === 'customer' ? 'customer_id' : 'organizer_id';
            if ((int) $booking->{$ownerColumn} !== $actorId) {
                throw new DomainException('You cannot resend tickets for this booking.');
            }

            $this->assertDeliverable($booking);

            $recent = BookingTicketDeliveryRequest::query()
                ->where('booking_id', $booking->id)
                ->where('requested_by_type', $actorType)
                ->where('requested_by_id', $actorId)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->whereIn('status', ['pending', 'processing', 'sent'])
                ->exists();
            if ($recent) {
                throw new DomainException('A ticket email was recently requested. Please allow a few minutes for delivery before trying again.');
            }

            $delivery = BookingTicketDeliveryRequest::create([
                'public_id' => (string) Str::uuid(),
                'booking_id' => $booking->id,
                'requested_by_type' => $actorType,
                'requested_by_id' => $actorId,
                'recipient_email_hash' => hash_hmac('sha256', mb_strtolower(trim((string) $booking->email)), (string) config('app.key')),
                'status' => 'pending',
            ]);

            AuditLogger::record('event_booking.tickets_resend_requested', $booking, [], [
                'delivery_request_id' => $delivery->public_id,
                'requested_by_type' => $actorType,
            ]);

            return $delivery;
        }, 3);

        $this->dispatchRequest((int) $request->id);
        return $request;
    }

    public function dispatchRequest(int $deliveryRequestId): bool
    {
        $request = BookingTicketDeliveryRequest::query()->find($deliveryRequestId);
        if (!$request || $request->status !== 'pending') {
            return true;
        }

        $request->forceFill([
            'dispatch_attempts' => (int) $request->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->pushDeliveryJob((int) $request->id);
            return true;
        } catch (Throwable $exception) {
            $request->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushDeliveryJob(int $deliveryRequestId): void
    {
        ResendEventBookingTickets::dispatch($deliveryRequestId);
    }
}
