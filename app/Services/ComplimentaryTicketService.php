<?php

namespace App\Services;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use RuntimeException;
use Throwable;

/** Issues one inventory-backed complimentary admission through normal fulfilment. */
final class ComplimentaryTicketService
{
    public function issue(
        Event $event,
        Ticket $ticket,
        string $name,
        string $email,
        string $paymentMethod,
        ?int $seatId = null,
    ): Booking {
        if ((int) $ticket->event_id !== (int) $event->id) {
            throw new RuntimeException('The selected ticket category does not belong to this event.');
        }
        if ($ticket->pricing_type === 'variation') {
            throw new RuntimeException('Choose a ticket category without legacy options.');
        }
        if ($ticket->seating_mode === 'reserved' && !$seatId) {
            throw new RuntimeException('Choose an available seat for this reserved ticket.');
        }
        if ($ticket->seating_mode !== 'reserved' && $seatId) {
            throw new RuntimeException('A seat can only be selected for a reserved ticket category.');
        }

        $email = mb_strtolower(trim($email));
        $emailHash = app(EventTicketPurchaseLimitService::class)->buyerIdentityHash($email);
        $title = TicketContent::query()
            ->where('ticket_id', $ticket->id)
            ->orderBy('language_id')
            ->value('title') ?: ($ticket->title ?: 'Complimentary');
        $selection = [[
            'ticket_id' => $ticket->id,
            'early_bird_dicount' => 0,
            'name' => $title,
            'qty' => 1,
            'price' => 0,
        ]];
        $token = null;

        try {
            $token = app(TicketReservationService::class)->reserve(
                (int) $event->id,
                $selection,
                null,
                null,
                buyerEmailHash: $emailHash,
            );
            if ($seatId) {
                app(SeatReservationService::class)->reserve(
                    $token,
                    (int) $event->id,
                    [$seatId],
                    null,
                    null,
                    $emailHash,
                );
            }

            $parts = preg_split('/\s+/u', trim($name)) ?: [];
            $first = array_shift($parts) ?: trim($name);
            $booking = app(BookingController::class)->storeData([
                'event_id' => $event->id,
                'price' => 0,
                'tax' => 0,
                'commission' => 0,
                'quantity' => 1,
                'discount' => 0,
                'total_early_bird_dicount' => 0,
                'currencyText' => $event->currency,
                'currencyTextPosition' => null,
                'currencySymbol' => null,
                'currencySymbolPosition' => null,
                'fname' => $first,
                'lname' => $parts ? implode(' ', $parts) : '',
                'email' => $email,
                'phone' => '',
                'paymentMethod' => $paymentMethod,
                'gatewayType' => null,
                'paymentStatus' => 'free',
                'event_date' => $event->date_type === 'multiple'
                    ? null
                    : FullDateTime(trim((string) $event->start_date.' '.(string) $event->start_time)),
                'selected_tickets' => $selection,
                'reservation_token' => $token,
                'customer_id' => null,
            ]);
        } catch (Throwable $exception) {
            if ($token) {
                rescue(
                    fn () => app(TicketReservationService::class)->release($token, 'complimentary_issue_failed'),
                    null,
                    false,
                );
            }
            throw $exception;
        }

        app(PaidBookingFulfillmentService::class)->dispatch($booking->id);

        return $booking;
    }
}
