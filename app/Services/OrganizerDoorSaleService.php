<?php

namespace App\Services;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Records money collected in person without moving the organiser's online payout balance. */
final class OrganizerDoorSaleService
{
    public function record(Event $event, Ticket $ticket, array $data): Booking
    {
        if ((int) $ticket->event_id !== (int) $event->id) {
            throw new RuntimeException('The selected ticket does not belong to this event.');
        }
        if (! $event->isBookable()) {
            throw new RuntimeException('Ticket sales are closed for this event.');
        }
        if ($ticket->pricing_type === 'variation') {
            throw new RuntimeException('Legacy ticket options must be sold through the normal checkout.');
        }
        if ($ticket->seating_mode === 'reserved') {
            throw new RuntimeException('Reserved seats must be selected through the normal checkout.');
        }

        $quantity = (int) $data['quantity'];
        $method = (string) $data['payment_method'];
        $complimentary = $method === 'complimentary';
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $deliverableEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        $name = trim((string) $data['name']);
        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = array_shift($parts) ?: $name;
        $title = TicketContent::query()->where('ticket_id', $ticket->id)->orderBy('language_id')->value('title')
            ?: ($ticket->title ?: 'Ticket');
        $amount = $complimentary ? 0.0 : round((float) $data['amount'], 2);
        $selection = [[
            'ticket_id' => $ticket->id,
            'early_bird_dicount' => 0,
            'name' => $title,
            'qty' => $quantity,
            'price' => $quantity > 0 ? round($amount / $quantity, 2) : 0,
        ]];

        $booking = app(BookingController::class)->storeData([
            'event_id' => $event->id,
            'price' => $amount,
            'tax' => 0,
            'commission' => 0,
            'quantity' => $quantity,
            'discount' => 0,
            'total_early_bird_dicount' => 0,
            'currencyText' => $event->currency ?: 'NGN',
            'currencyTextPosition' => null,
            'currencySymbol' => null,
            'currencySymbolPosition' => null,
            'fname' => $first,
            'lname' => $parts ? implode(' ', $parts) : '',
            'email' => $deliverableEmail,
            'phone' => trim((string) ($data['phone'] ?? '')),
            'paymentMethod' => 'Door ' . match ($method) {
                'cash' => 'cash', 'pos' => 'POS', 'bank_transfer' => 'bank transfer', default => 'complimentary',
            },
            'gatewayType' => 'door_sale',
            'paymentStatus' => $complimentary ? 'free' : 'completed',
            'event_date' => $event->date_type === 'multiple' ? null : FullDateTime(trim((string) $event->start_date . ' ' . (string) $event->start_time)),
            'selected_tickets' => $selection,
            'customer_id' => null,
            'attendees' => array_map(fn (int $index) => [
                'name' => $quantity === 1 ? $name : $name . ' ' . ($index + 1),
                'email' => $deliverableEmail,
            ], range(0, $quantity - 1)),
        ]);

        if (! empty($data['check_in'])) {
            DB::transaction(function () use ($booking): void {
                $booking->forceFill(['scan_status' => 1])->save();
                BookingTicket::query()->where('booking_id', $booking->id)->whereNull('revoked_at')->update([
                    'status' => 'checked_in',
                    'checked_in_at' => now(),
                    'checked_in_by_organizer_id' => $booking->organizer_id,
                    'checked_in_source' => 'door_sale',
                    'updated_at' => now(),
                ]);
            });
        }

        if ($deliverableEmail) {
            app(PaidBookingFulfillmentService::class)->dispatch($booking->id);
        }

        AuditLogger::record('organizer.door_sale_recorded', $booking, [], [
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'quantity' => $quantity,
            'payment_method' => $method,
            'amount' => $amount,
            'checked_in' => ! empty($data['check_in']),
        ]);

        return $booking->fresh();
    }
}
