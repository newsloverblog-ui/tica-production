<?php

namespace App\Services;

use App\Models\BasicSettings\Basic;
use App\Models\Event;
use App\Models\Event\Coupon;
use App\Models\Event\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;

final class EventCheckoutQuoteService
{
    public function __construct(
        private readonly EventCouponService $coupons,
        private readonly EventFeeCalculatorService $fees,
        private readonly EventTicketPurchaseLimitService $purchaseLimits,
    ) {
    }

    /**
     * Rebuild the payable quote from current catalogue records. Prices and
     * discount amounts already present in the browser session are ignored.
     *
     * @return array<string, mixed>
     */
    public function refreshSession(int $eventId): array
    {
        $event = Event::query()->findOrFail($eventId);
        if (!$event->isBookable()) {
            throw new RuntimeException('Ticket sales are closed for this event.');
        }

        $selections = Session::get('selTickets');
        if (!is_array($selections) || $selections === [] || count($selections) > 500) {
            throw new RuntimeException('Your ticket selection has expired. Please select tickets again.');
        }

        $ticketIds = collect($selections)
            ->pluck('ticket_id')
            ->map(static fn ($id): int => filter_var($id, FILTER_VALIDATE_INT) !== false ? (int) $id : 0)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $tickets = Ticket::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ticketIds)
            ->get()
            ->keyBy('id');

        $reviewed = [];
        $seen = [];
        $subtotal = 0.0;
        $earlyBirdDiscount = 0.0;
        $quantity = 0;
        $paidQuantity = 0;
        $now = CarbonImmutable::now();

        foreach ($selections as $selection) {
            $ticketId = filter_var($selection['ticket_id'] ?? null, FILTER_VALIDATE_INT);
            $selectedQuantity = filter_var($selection['qty'] ?? null, FILTER_VALIDATE_INT);
            if ($ticketId === false || $selectedQuantity === false || $selectedQuantity < 1 || $selectedQuantity > 10000) {
                throw new RuntimeException('The ticket selection contains an invalid quantity.');
            }

            /** @var Ticket|null $ticket */
            $ticket = $tickets->get((int) $ticketId);
            if (!$ticket || !in_array($ticket->pricing_type, ['normal', 'free', 'variation'], true)) {
                throw new RuntimeException('A selected ticket is no longer available.');
            }

            $name = trim((string) ($selection['name'] ?? ''));
            $price = 0.0;
            $availabilityType = $ticket->ticket_available_type;
            $available = (int) $ticket->ticket_available;
            $identity = (string) $ticket->id;

            if ($ticket->pricing_type === 'normal') {
                $price = round(max(0, (float) $ticket->price), 2);
                $name = $name !== '' ? $name : ($ticket->title ?: 'General admission');
            } elseif ($ticket->pricing_type === 'free') {
                $name = $name !== '' ? $name : ($ticket->title ?: 'General admission');
            } else {
                $variations = json_decode((string) $ticket->variations, true);
                if (!is_array($variations) || $name === '') {
                    throw new RuntimeException('The selected ticket option is no longer available.');
                }
                $matches = array_values(array_filter(
                    $variations,
                    static fn ($variation): bool => is_array($variation)
                        && hash_equals((string) ($variation['name'] ?? ''), $name)
                ));
                if (count($matches) !== 1) {
                    throw new RuntimeException('The selected ticket option is ambiguous or unavailable.');
                }
                $variation = $matches[0];
                $price = round(max(0, (float) ($variation['price'] ?? 0)), 2);
                $availabilityType = $variation['ticket_available_type'] ?? null;
                $available = (int) ($variation['ticket_available'] ?? 0);
                $identity .= '|'.$name;
            }

            if (isset($seen[$identity])) {
                throw new RuntimeException('The same ticket option cannot appear more than once.');
            }
            $seen[$identity] = true;

            $this->purchaseLimits->assertCanSelect(
                $ticket,
                $ticket->pricing_type === 'variation' ? $name : null,
                (int) $selectedQuantity,
                Auth::guard('customer')->id(),
            );
            if ($availabilityType === 'limited' && $selectedQuantity > $available) {
                throw new RuntimeException('The selected ticket is no longer available in that quantity.');
            }

            $lineEarlyBird = $this->earlyBirdDiscount($ticket, $price, $selectedQuantity, $now);
            $subtotal += $price * $selectedQuantity;
            $earlyBirdDiscount += $lineEarlyBird;
            $quantity += $selectedQuantity;
            if ($price > 0) {
                $paidQuantity += $selectedQuantity;
            }
            if ($quantity > 100000) {
                throw new RuntimeException('The ticket selection is too large.');
            }

            $reviewed[] = [
                'ticket_id' => (int) $ticket->id,
                'early_bird_dicount' => round($lineEarlyBird, 2),
                'name' => $name,
                'qty' => (int) $selectedQuantity,
                'price' => $price,
            ];
        }

        $subtotal = round($subtotal, 2);
        $earlyBirdDiscount = round(min($subtotal, max(0, $earlyBirdDiscount)), 2);
        $couponId = Session::get('event_coupon_id');
        $coupon = filter_var($couponId, FILTER_VALIDATE_INT) !== false
            ? Coupon::query()->find((int) $couponId)
            : null;
        $discount = $coupon
            ? $this->coupons->discount($coupon, $event->id, $subtotal, $earlyBirdDiscount)
            : null;
        if ($coupon && $discount === null) {
            Session::forget('event_coupon_id');
        }
        $discount = round(max(0, (float) ($discount ?? 0)), 2);

        $percentage = (float) (Basic::query()->value('booking_fee_percentage') ?? 6);
        $ticketSales = round(max(0, $subtotal - $earlyBirdDiscount - $discount), 2);
        $quote = $this->fees->quotePercentage($ticketSales, $paidQuantity, $percentage, (string) ($event->fee_payer ?: 'buyer'));

        $result = [
            'selTickets' => $reviewed,
            'sub_total' => $subtotal,
            'total_early_bird_dicount' => $earlyBirdDiscount,
            'discount' => $discount,
            'total' => $ticketSales,
            'tax' => $quote['attendee_booking_fees'],
            'commission' => $quote['commission'],
            'grand_total' => $quote['attendee_total'],
            'quantity' => $quantity,
        ];

        Session::put($result);

        return $result;
    }

    private function earlyBirdDiscount(Ticket $ticket, float $price, int $quantity, CarbonImmutable $now): float
    {
        if ($price <= 0 || $ticket->early_bird_discount !== 'enable') {
            return 0.0;
        }

        try {
            $expiresAt = CarbonImmutable::parse(
                trim((string) $ticket->early_bird_discount_date.' '.(string) $ticket->early_bird_discount_time)
            );
        } catch (\Throwable) {
            return 0.0;
        }
        if ($now->greaterThan($expiresAt)) {
            return 0.0;
        }

        $perTicket = match ((string) $ticket->early_bird_discount_type) {
            'fixed' => min($price, max(0, (float) $ticket->early_bird_discount_amount)),
            'percentage' => min(100, max(0, (float) $ticket->early_bird_discount_amount)) * $price / 100,
            default => 0.0,
        };

        return round(min($price * $quantity, $perTicket * $quantity), 2);
    }
}
