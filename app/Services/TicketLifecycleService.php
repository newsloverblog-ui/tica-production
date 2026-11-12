<?php

namespace App\Services;

use App\Jobs\SendTicketTransferInvitation;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\BookingTicketEvent;
use App\Models\Event\BookingTicketTransfer;
use App\Models\Customer;
use App\Models\Event;
use App\Support\LifecycleMailCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TicketLifecycleService
{
    public function requestTransferForCustomer(
        int $ticketId,
        int $customerId,
        string $holderName,
        string $holderEmail
    ): array {
        $validated = Validator::make([
            'name' => $holderName,
            'email' => $holderEmail,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ])->validate();

        $recipientName = trim($validated['name']);
        $recipientEmail = mb_strtolower(trim($validated['email']));

        return DB::transaction(function () use ($ticketId, $customerId, $recipientName, $recipientEmail) {
            [$ticket, $booking] = $this->lockedTicket($ticketId);
            $this->authorize($ticket, $booking, 'customer', $customerId);
            $this->ensureMutable($ticket, $booking);
            $this->ensureTransferableEvent($booking);

            $owner = Customer::query()->find($customerId);
            if ($owner && mb_strtolower((string) $owner->email) === $recipientEmail) {
                throw ValidationException::withMessages([
                    'holder_email' => 'The recipient must use a different email address.',
                ]);
            }

            BookingTicketTransfer::query()
                ->where('booking_ticket_id', $ticket->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'superseded', 'cancelled_at' => now(),
                    'delivery_status' => 'suppressed', 'dispatch_token' => null,
                    'updated_at' => now(),
                ]);

            $plainToken = Str::random(64);
            $transfer = BookingTicketTransfer::create([
                'booking_ticket_id' => $ticket->id,
                'from_customer_id' => $customerId,
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
                'token_hash' => hash('sha256', $plainToken),
                'dispatch_token' => $plainToken,
                'dispatch_attempts' => 0,
                'dispatch_last_attempt_at' => null,
                'dispatch_last_error' => null,
                'status' => 'pending',
                'delivery_status' => 'pending',
                'expires_at' => now()->addHours((int) config('tica.ticket_transfer_expiry_hours', 48)),
            ]);

            $snapshot = $this->snapshot($ticket);
            $pending = array_merge($snapshot, [
                'pending_recipient_name' => $recipientName,
                'pending_recipient_email' => $recipientEmail,
                'transfer_expires_at' => $transfer->expires_at->toIso8601String(),
            ]);
            $this->history($ticket, 'transfer_requested', 'customer', $customerId, $snapshot, $pending, 'Recipient acceptance required.');
            AuditLogger::record('ticket_unit.transfer_requested', $ticket, $snapshot, $pending, [
                'transfer_id' => $transfer->id,
            ]);

            DB::afterCommit(fn () => $this->dispatchTransferInvitation($transfer->id));

            return ['transfer' => $transfer, 'token' => $plainToken];
        }, 3);
    }

    public function acceptTransfer(int $transferId, string $plainToken, int $customerId): BookingTicket
    {
        return DB::transaction(function () use ($transferId, $plainToken, $customerId) {
            $transferTicketId = (int) BookingTicketTransfer::query()->findOrFail($transferId)->booking_ticket_id;
            [$ticket, $booking] = $this->lockedTicket($transferTicketId);
            $transfer = BookingTicketTransfer::query()->lockForUpdate()->findOrFail($transferId);
            if ((int) $transfer->booking_ticket_id !== $ticket->id) {
                throw new RuntimeException('The transfer target changed unexpectedly.');
            }
            if (!hash_equals($transfer->token_hash, hash('sha256', $plainToken))) {
                throw new AuthorizationException('This transfer invitation is invalid.');
            }
            if ($transfer->status !== 'pending') {
                throw new RuntimeException('This transfer invitation is no longer available.');
            }
            if ($transfer->expires_at->isPast()) {
                throw new RuntimeException('This transfer invitation has expired.');
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            if ((int) $customer->status !== 1 || !$customer->email_verified_at) {
                throw new AuthorizationException('A verified active customer account is required.');
            }
            if (mb_strtolower((string) $customer->email) !== mb_strtolower($transfer->recipient_email)) {
                throw new AuthorizationException('Sign in with the email address that received this transfer.');
            }

            $this->ensureMutable($ticket, $booking);
            $this->ensureTransferableEvent($booking);
            if ($this->effectiveOwnerId($ticket, $booking) !== (int) $transfer->from_customer_id) {
                throw new RuntimeException('Ticket ownership changed after this invitation was created.');
            }

            $before = $this->snapshot($ticket);
            $ticket->update([
                'public_id' => (string) Str::uuid(),
                'holder_name' => $transfer->recipient_name,
                'holder_email' => $transfer->recipient_email,
                'holder_customer_id' => $customer->id,
            ]);
            $transfer->update([
                'status' => 'accepted',
                'accepted_by_customer_id' => $customer->id,
                'accepted_at' => now(),
                'dispatch_token' => null,
            ]);
            BookingTicketTransfer::query()
                ->where('booking_ticket_id', $ticket->id)
                ->where('id', '!=', $transfer->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'superseded', 'cancelled_at' => now(),
                    'delivery_status' => 'suppressed', 'dispatch_token' => null,
                    'updated_at' => now(),
                ]);

            $after = $this->snapshot($ticket);
            $this->history($ticket, 'transferred', 'customer', $customer->id, $before, $after, 'Accepted by the recipient.');
            AuditLogger::record('ticket_unit.transferred', $ticket, $before, $after, [
                'transfer_id' => $transfer->id,
            ]);

            $transferId = (int) $transfer->id;
            DB::afterCommit(function () use ($transferId, $before): void {
                if (!Schema::hasTable('application_mail_deliveries')) return;
                $accepted = BookingTicketTransfer::with('ticket.booking.event')->find($transferId);
                $booking = $accepted?->ticket?->booking;
                if (!$accepted || !$booking) return;

                app(LifecycleMailService::class)->queueSafely(
                    LifecycleMailCatalog::BUYER_TICKET_TRANSFERRED,
                    $accepted->recipient_email,
                    [
                        'event_name' => $booking->event->title ?? 'your event',
                        'ticket_label' => $accepted->ticket->variation_name ?: 'Event ticket',
                        'sender_name' => $before['holder_name'] ?? null,
                        'ticket_url' => url('/customer/booking/details/' . $booking->id),
                    ],
                    $accepted
                );
            });

            return $ticket->fresh();
        }, 3);
    }

    public function cancelTransferForCustomer(int $transferId, int $customerId): BookingTicketTransfer
    {
        return DB::transaction(function () use ($transferId, $customerId) {
            $transferTicketId = (int) BookingTicketTransfer::query()->findOrFail($transferId)->booking_ticket_id;
            [$ticket, $booking] = $this->lockedTicket($transferTicketId);
            $transfer = BookingTicketTransfer::query()->lockForUpdate()->findOrFail($transferId);
            if ((int) $transfer->booking_ticket_id !== $ticket->id) {
                throw new RuntimeException('The transfer target changed unexpectedly.');
            }
            $this->authorize($ticket, $booking, 'customer', $customerId);
            if ($transfer->status !== 'pending') {
                throw new RuntimeException('Only a pending transfer can be cancelled.');
            }

            $transfer->update([
                'status' => 'cancelled', 'cancelled_at' => now(),
                'delivery_status' => 'suppressed', 'dispatch_token' => null,
            ]);
            $snapshot = $this->snapshot($ticket);
            $this->history($ticket, 'transfer_cancelled', 'customer', $customerId, $snapshot, $snapshot, 'Cancelled by the current holder.');
            AuditLogger::record('ticket_unit.transfer_cancelled', $ticket, $snapshot, $snapshot, [
                'transfer_id' => $transfer->id,
            ]);
            return $transfer->fresh();
        }, 3);
    }

    public function reissueForCustomer(int $ticketId, int $customerId, string $reason): BookingTicket
    {
        $reason = $this->validatedReason($reason, 'reissue');
        return $this->rotate($ticketId, 'customer', $customerId, 'reissued', [], $reason);
    }

    public function reissueForOrganizer(int $ticketId, int $organizerId, string $reason): BookingTicket
    {
        return $this->rotate(
            $ticketId,
            'organizer',
            $organizerId,
            'reissued',
            [],
            $this->validatedReason($reason, 'reissue')
        );
    }

    public function revokeForOrganizer(int $ticketId, int $organizerId, string $reason): BookingTicket
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'A revocation reason of up to 500 characters is required.']);
        }

        return DB::transaction(function () use ($ticketId, $organizerId, $reason) {
            [$ticket, $booking] = $this->lockedTicket($ticketId);
            $this->authorize($ticket, $booking, 'organizer', $organizerId);
            $this->ensureMutable($ticket, $booking);

            $before = $this->snapshot($ticket);
            $this->cancelPendingTransfers($ticket);
            $ticket->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_reason' => $reason]);
            $this->history($ticket, 'revoked', 'organizer', $organizerId, $before, $this->snapshot($ticket), $reason);
            AuditLogger::record('ticket_unit.revoked', $ticket, $before, $this->snapshot($ticket), ['reason' => $reason]);
            return $ticket->fresh();
        }, 3);
    }

    private function rotate(
        int $ticketId,
        string $actorType,
        int $actorId,
        string $action,
        array $changes,
        string $reason
    ): BookingTicket {
        return DB::transaction(function () use ($ticketId, $actorType, $actorId, $action, $changes, $reason) {
            [$ticket, $booking] = $this->lockedTicket($ticketId);
            $this->authorize($ticket, $booking, $actorType, $actorId);
            $this->ensureMutable($ticket, $booking);

            $before = $this->snapshot($ticket);
            $this->cancelPendingTransfers($ticket);
            $ticket->update(array_merge($changes, ['public_id' => (string) Str::uuid()]));
            $after = $this->snapshot($ticket);
            $this->history($ticket, $action, $actorType, $actorId, $before, $after, $reason);
            AuditLogger::record('ticket_unit.' . $action, $ticket, $before, $after, ['reason' => $reason]);
            return $ticket->fresh();
        }, 3);
    }

    private function lockedTicket(int $ticketId): array
    {
        $ticket = BookingTicket::query()->lockForUpdate()->findOrFail($ticketId);
        $booking = Booking::query()->lockForUpdate()->findOrFail($ticket->booking_id);
        return [$ticket, $booking];
    }

    private function authorize(BookingTicket $ticket, Booking $booking, string $actorType, int $actorId): void
    {
        $allowed = $actorType === 'customer'
            ? $this->effectiveOwnerId($ticket, $booking) === $actorId
            : ($actorType === 'organizer' && (int) $ticket->organizer_id === $actorId);

        if (!$allowed) {
            throw new AuthorizationException('You do not own this ticket.');
        }
    }

    private function ensureMutable(BookingTicket $ticket, Booking $booking): void
    {
        if ($ticket->status !== 'issued') {
            throw new RuntimeException('Only an unused issued ticket can be changed.');
        }
        if (!in_array((string) $booking->paymentStatus, ['completed', 'free', '1'], true)) {
            throw new RuntimeException('The booking payment is not complete.');
        }
        if ($booking->refund_status === 'refunded') {
            throw new RuntimeException('A refunded ticket cannot be changed.');
        }
    }

    private function ensureTransferableEvent(Booking $booking): void
    {
        $event = Event::query()->find($booking->event_id);
        if (!$event
            || (int) $event->status !== 1
            || ($event->lifecycle_status ?: 'scheduled') !== 'scheduled'
            || $event->cancelled_at) {
            throw new RuntimeException('Tickets cannot be transferred for an inactive or cancelled event.');
        }
        if (isset($event->allow_transfers) && !$event->allow_transfers) {
            throw new RuntimeException('The organiser has turned off ticket transfers for this event.');
        }
    }

    private function history(
        BookingTicket $ticket,
        string $action,
        string $actorType,
        int $actorId,
        array $before,
        array $after,
        string $reason
    ): void {
        BookingTicketEvent::create([
            'booking_ticket_id' => $ticket->id,
            'action' => $action,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function snapshot(BookingTicket $ticket): array
    {
        return $ticket->only(['public_id', 'holder_name', 'holder_email', 'holder_customer_id', 'status', 'revoked_at', 'revoked_reason']);
    }

    private function effectiveOwnerId(BookingTicket $ticket, Booking $booking): int
    {
        return (int) ($ticket->holder_customer_id ?: $booking->customer_id);
    }

    private function cancelPendingTransfers(BookingTicket $ticket): void
    {
        BookingTicketTransfer::query()
            ->where('booking_ticket_id', $ticket->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'superseded', 'cancelled_at' => now(),
                'delivery_status' => 'suppressed', 'dispatch_token' => null,
                'updated_at' => now(),
            ]);
    }

    public function dispatchTransferInvitation(int $transferId): bool
    {
        $transfer = BookingTicketTransfer::query()->find($transferId);
        if (!$transfer || $transfer->status !== 'pending' || $transfer->delivery_status !== 'pending') {
            return true;
        }
        $transfer->forceFill([
            'dispatch_attempts' => (int) $transfer->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();
        try {
            $token = (string) $transfer->dispatch_token;
            if ($token === '' || !hash_equals($transfer->token_hash, hash('sha256', $token))) {
                throw new RuntimeException('The ticket transfer dispatch token is unavailable or invalid.');
            }
            $this->pushTransferInvitationJob($transfer->id, $token);
            return true;
        } catch (Throwable $exception) {
            $transfer->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushTransferInvitationJob(int $transferId, string $token): void
    {
        SendTicketTransferInvitation::dispatch($transferId, $token);
    }

    private function validatedReason(string $reason, string $action): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => 'A ' . $action . ' reason of up to 500 characters is required.',
            ]);
        }
        return $reason;
    }
}
