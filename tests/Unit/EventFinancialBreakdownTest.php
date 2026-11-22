<?php

namespace Tests\Unit;

use App\Support\EventFinancialBreakdown;
use LogicException;
use PHPUnit\Framework\TestCase;

class EventFinancialBreakdownTest extends TestCase
{
    public function test_buyer_paid_checkout_fee_belongs_only_to_tica(): void
    {
        $split = EventFinancialBreakdown::calculate(5000, 400, 0);

        $this->assertSame(5000.0, $split['ticket_sales']);
        $this->assertSame(5400.0, $split['customer_paid']);
        $this->assertSame(400.0, $split['platform_revenue']);
        $this->assertSame(5000.0, $split['organizer_revenue']);
    }

    public function test_organizer_paid_fee_reduces_only_organizer_revenue(): void
    {
        $split = EventFinancialBreakdown::calculate(5000, 0, 400);

        $this->assertSame(5000.0, $split['customer_paid']);
        $this->assertSame(400.0, $split['platform_revenue']);
        $this->assertSame(4600.0, $split['organizer_revenue']);
    }

    public function test_invalid_financial_split_fails_closed(): void
    {
        $this->expectException(LogicException::class);

        EventFinancialBreakdown::calculate(100, 0, 101);
    }
}
