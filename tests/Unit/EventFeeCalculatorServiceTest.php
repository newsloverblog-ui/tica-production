<?php

namespace Tests\Unit;

use App\Services\EventFeeCalculatorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventFeeCalculatorServiceTest extends TestCase
{
    #[Test]
    public function it_matches_the_event_checkout_fee_formula(): void
    {
        $quote = (new EventFeeCalculatorService())->quote(5000, 100, 100, 5);

        self::assertSame(500000.0, $quote['ticket_sales']);
        self::assertSame(10000.0, $quote['attendee_booking_fees']);
        self::assertSame(510000.0, $quote['attendee_total']);
        self::assertSame(25500.0, $quote['platform_fee']);
        self::assertSame(474500.0, $quote['organizer_net']);
    }

    #[Test]
    public function it_bounds_unsafe_inputs_and_keeps_money_to_two_decimals(): void
    {
        $quote = (new EventFeeCalculatorService())->quote(-10, 0, -5, 200);

        self::assertSame(0.0, $quote['ticket_price']);
        self::assertSame(1, $quote['quantity']);
        self::assertSame(0.0, $quote['booking_fee_per_ticket']);
        self::assertSame(100.0, $quote['commission_percentage']);
        self::assertSame(0.0, $quote['organizer_net']);
    }

    #[Test]
    public function it_preserves_an_authoritative_discounted_subtotal(): void
    {
        $quote = (new EventFeeCalculatorService())->quoteGross(9999.99, 3, 100, 5);

        self::assertSame(9999.99, $quote['ticket_sales']);
        self::assertSame(300.0, $quote['attendee_booking_fees']);
        self::assertSame(10299.99, $quote['attendee_total']);
        self::assertSame(515.0, $quote['platform_fee']);
        self::assertSame(9484.99, $quote['organizer_net']);
    }
}
