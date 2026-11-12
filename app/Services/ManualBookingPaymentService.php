<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\BookingDelivery;
use App\Models\Organizer;
use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Facades\DB;

class ManualBookingPaymentService
{
    public function transition(
        int $bookingId,
        string $targetStatus,
        ?int $requiredOrganizerId = null,
    ): ManualBookingPaymentResult {
        if (!in_array($targetStatus, ['completed', 'rejected'], true)) {
            throw new DomainException('Manual payment review can only approve or reject a pending booking.');
        }

        return DB::transaction(function () use ($bookingId, $targetStatus, $requiredOrganizerId) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);
            $event = Event::query()->lockForUpdate()->findOrFail($booking->event_id);

            if ($requiredOrganizerId !== null && (
                (int) $event->organizer_id !== $requiredOrganizerId
                || (int) $booking->organizer_id !== $requiredOrganizerId
            )) {
                throw new DomainException('You cannot review payment for this booking.');
            }

            $before = $booking->only(['paymentStatus', 'invoice']);
            if ((string) $booking->gatewayType !== 'offline') {
                throw new DomainException('Only offline bookings can be manually approved or rejected.');
            }

            if ((string) $booking->paymentStatus === $targetStatus) {
                $this->verifyTerminalFinancialEvidence($booking, $targetStatus);
                if ($targetStatus === 'completed') {
                    $this->ensureFulfillmentPending($booking);
                } else {
                    $this->ensureRejectionNotificationPending($booking);
                }
                return new ManualBookingPaymentResult($booking, $before, false);
            }

            if (!BookingPaymentState::canManuallyTransition($booking->paymentStatus, $targetStatus)) {
                throw new DomainException('Only pending offline bookings can be approved or rejected.');
            }

            $this->assertNoExistingFinancialEvidence($booking);

            if ($targetStatus === 'completed') {
                $this->approve($booking);
            } else {
                $this->reject($booking, $event);
            }

            $booking->refresh();
            AuditLogger::record(
                'booking.payment_status_changed',
                $booking,
                ['payment_status' => $before['paymentStatus']],
                ['payment_status' => $booking->paymentStatus],
                [
                    'gateway' => 'offline',
                    'gateway_reference' => $targetStatus === 'completed'
                        ? 'booking:'.$booking->id
                        : null,
                ]
            );

            return new ManualBookingPaymentResult($booking, $before, true);
        }, 3);
    }

    private function approve(Booking $booking): void
    {
        $earning = app(PlatformEarningService::class)->lock();

        $price = (float) $booking->price;
        $tax = (float) $booking->tax;
        $commission = (float) $booking->commission;
        $earning->update([
            'total_revenue' => (float) $earning->total_revenue + $price + $tax,
            'total_earning' => (float) $earning->total_earning
                + ($booking->organizer_id ? $tax + $commission : $price + $tax),
        ]);

        $organizer = null;
        $preBalance = 0.0;
        $afterBalance = null;
        if ($booking->organizer_id) {
            $organizer = Organizer::query()->lockForUpdate()->findOrFail($booking->organizer_id);
            $preBalance = (float) $organizer->amount;
            $afterBalance = $preBalance + $price - $commission;
            $organizer->update(['amount' => $afterBalance]);
        }

        $booking->update(['paymentStatus' => 'completed']);

        $ledger = [
            'transcation_id' => 'offline-booking-'.$booking->id,
            'gateway' => 'offline',
            'gateway_reference' => 'booking:'.$booking->id,
            'booking_id' => $booking->id,
            'transcation_type' => 1,
            'customer_id' => $booking->customer_id,
            'organizer_id' => $booking->organizer_id,
            'payment_status' => 1,
            'payment_method' => $booking->paymentMethod,
            'grand_total' => $price,
            'tax' => $tax,
            'commission' => $commission,
            'pre_balance' => $preBalance,
            'after_balance' => $afterBalance,
            'gateway_type' => 'offline',
            'currency_symbol' => $booking->currencySymbol,
            'currency_symbol_position' => $booking->currencySymbolPosition,
        ];

        Transaction::create($ledger);
        $this->ensureFulfillmentPending($booking);
    }

    private function ensureFulfillmentPending(Booking $booking): void
    {
        BookingDelivery::firstOrCreate(
            ['booking_id' => $booking->id, 'kind' => 'event_booking_confirmation'],
            ['status' => 'pending']
        );
    }

    private function reject(Booking $booking, Event $event): void
    {
        $this->restoreInventory($booking, $event);
        $booking->update(['paymentStatus' => 'rejected']);
        $this->ensureRejectionNotificationPending($booking);
    }

    private function ensureRejectionNotificationPending(Booking $booking): void
    {
        BookingDelivery::firstOrCreate(
            ['booking_id' => $booking->id, 'kind' => 'event_booking_rejection'],
            ['status' => 'pending']
        );
    }

    private function assertNoExistingFinancialEvidence(Booking $booking): void
    {
        $hasLedger = Transaction::query()
            ->where('booking_id', (string) $booking->id)
            ->where('transcation_type', 1)
            ->lockForUpdate()
            ->exists();

        if ($hasLedger) {
            throw new DomainException(
                'This booking already has financial ledger evidence and requires finance review.'
            );
        }
    }

    private function verifyTerminalFinancialEvidence(Booking $booking, string $status): void
    {
        if ($status === 'rejected') {
            $this->assertNoExistingFinancialEvidence($booking);
            return;
        }

        $ledgers = Transaction::query()
            ->where('booking_id', (string) $booking->id)
            ->where('transcation_type', 1)
            ->lockForUpdate()
            ->get();
        if ($ledgers->count() !== 1) {
            throw new DomainException(
                'This completed booking has no single paid ledger record and requires finance review.'
            );
        }

        $ledger = $ledgers->first();
        $paid = in_array(strtolower((string) $ledger->payment_status), [
            '1', 'completed', 'paid', 'success',
        ], true);
        $methodMatches = trim((string) $ledger->payment_method) !== ''
            && trim((string) $booking->paymentMethod) !== ''
            && trim((string) $ledger->payment_method) === trim((string) $booking->paymentMethod);
        $matches = $paid
            && strtolower(trim((string) $ledger->gateway)) === 'offline'
            && (string) $ledger->gateway_reference === 'booking:'.$booking->id
            && abs((float) $ledger->grand_total - (float) $booking->price) < 0.005
            && abs((float) $ledger->tax - (float) $booking->tax) < 0.005
            && abs((float) $ledger->commission - (float) $booking->commission) < 0.005
            && (string) ($ledger->customer_id ?? '') === (string) ($booking->customer_id ?? '')
            && (string) ($ledger->organizer_id ?? '') === (string) ($booking->organizer_id ?? '')
            && $methodMatches;

        if (!$matches) {
            throw new DomainException(
                'This completed booking ledger does not reconcile and requires finance review.'
            );
        }
    }

    private function restoreInventory(Booking $booking, Event $event): void
    {
        $bookingQuantity = $this->positiveInteger($booking->quantity, 'booking quantity');
        $variations = json_decode((string) $booking->variation, true);
        if (!is_array($variations) || $variations === []) {
            if ($event->event_type === 'online') {
                $this->restoreLegacySingleTicket($booking, $event, $bookingQuantity);
                return;
            }

            throw new DomainException(
                'The booking has no complete ticket allocation evidence and requires inventory review.'
            );
        }

        $plans = [];
        $seenSelections = [];
        $selectedQuantity = 0;

        foreach ($variations as $selection) {
            if (!is_array($selection) || !array_key_exists('ticket_id', $selection)
                || !array_key_exists('qty', $selection)) {
                throw new DomainException(
                    'The booking has malformed ticket allocation evidence and requires inventory review.'
                );
            }

            $ticketId = $this->positiveInteger($selection['ticket_id'], 'ticket identifier');
            $quantity = $this->positiveInteger($selection['qty'], 'ticket quantity');
            $ticket = $plans[$ticketId]['ticket'] ?? Ticket::query()
                ->whereKey($ticketId)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if (!$ticket) {
                throw new DomainException(
                    'The booking ticket allocation no longer matches this event and requires inventory review.'
                );
            }

            $variationName = null;
            if ($ticket->pricing_type === 'variation') {
                $variationName = trim((string) ($selection['name'] ?? ''));
                if ($variationName === '') {
                    throw new DomainException(
                        'The booking ticket option is missing and requires inventory review.'
                    );
                }

                $ticketVariations = json_decode((string) $ticket->variations, true);
                if (!is_array($ticketVariations)) {
                    throw new DomainException(
                        'The ticket option inventory is malformed and requires inventory review.'
                    );
                }
                $matches = array_filter(
                    $ticketVariations,
                    fn ($variation): bool => is_array($variation)
                        && (string) ($variation['name'] ?? '') === $variationName
                );
                if (count($matches) !== 1) {
                    throw new DomainException(
                        'The booking ticket option cannot be matched exactly and requires inventory review.'
                    );
                }
            }

            $selectionKey = $ticketId.'|'.($variationName ?? 'ticket');
            if (isset($seenSelections[$selectionKey])) {
                throw new DomainException(
                    'The booking contains duplicate ticket allocation evidence and requires inventory review.'
                );
            }
            $seenSelections[$selectionKey] = true;
            $selectedQuantity += $quantity;

            $plans[$ticketId] ??= [
                'ticket' => $ticket,
                'quantity' => 0,
                'variations' => [],
            ];
            if ($variationName === null) {
                $plans[$ticketId]['quantity'] = $quantity;
            } else {
                $plans[$ticketId]['variations'][$variationName] = $quantity;
            }
        }

        if ($selectedQuantity !== $bookingQuantity) {
            throw new DomainException(
                'The booking ticket quantities do not reconcile and require inventory review.'
            );
        }

        foreach ($plans as $plan) {
            /** @var Ticket $ticket */
            $ticket = $plan['ticket'];
            if ($ticket->pricing_type !== 'variation') {
                if ($ticket->ticket_available_type === 'limited') {
                    $ticket->increment('ticket_available', $plan['quantity']);
                }
                continue;
            }

            $ticketVariations = json_decode((string) $ticket->variations, true);
            foreach ($ticketVariations as &$variation) {
                $name = (string) ($variation['name'] ?? '');
                if (isset($plan['variations'][$name])
                    && ($variation['ticket_available_type'] ?? null) === 'limited') {
                    $variation['ticket_available'] = (int) ($variation['ticket_available'] ?? 0)
                        + $plan['variations'][$name];
                }
            }
            unset($variation);

            $ticket->update(['variations' => json_encode($ticketVariations)]);
        }
    }

    private function restoreLegacySingleTicket(
        Booking $booking,
        Event $event,
        int $quantity,
    ): void {
        $ticketId = $booking->getAttribute('ticket_id');
        if ($ticketId !== null && trim((string) $ticketId) !== '') {
            $ticket = Ticket::query()
                ->whereKey($this->positiveInteger($ticketId, 'ticket identifier'))
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();
        } else {
            $tickets = Ticket::query()
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->get();
            $ticket = $tickets->count() === 1 ? $tickets->first() : null;
        }

        if (!$ticket || $ticket->pricing_type === 'variation') {
            throw new DomainException(
                'The legacy booking ticket cannot be matched exactly and requires inventory review.'
            );
        }

        if ($ticket->ticket_available_type === 'limited') {
            $ticket->increment('ticket_available', $quantity);
        }
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        $normalized = trim((string) $value);
        if (!preg_match('/^[1-9][0-9]*$/', $normalized)) {
            throw new DomainException(
                'The '.$field.' is invalid and requires inventory review.'
            );
        }

        $integer = filter_var($normalized, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);
        if ($integer === false) {
            throw new DomainException(
                'The '.$field.' is invalid and requires inventory review.'
            );
        }

        return $integer;
    }
}
