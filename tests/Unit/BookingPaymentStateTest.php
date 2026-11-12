<?php

namespace Tests\Unit;

use App\Services\BookingPaymentState;
use PHPUnit\Framework\TestCase;

class BookingPaymentStateTest extends TestCase
{
    public function test_only_pending_offline_bookings_can_be_decided(): void
    {
        $this->assertTrue(BookingPaymentState::canManuallyTransition('pending', 'completed'));
        $this->assertTrue(BookingPaymentState::canManuallyTransition('pending', 'rejected'));
        $this->assertFalse(BookingPaymentState::canManuallyTransition('completed', 'rejected'));
        $this->assertFalse(BookingPaymentState::canManuallyTransition('rejected', 'completed'));
        $this->assertFalse(BookingPaymentState::canManuallyTransition('pending', 'pending'));
    }

    public function test_financial_booking_records_cannot_be_deleted(): void
    {
        $this->assertFalse(BookingPaymentState::canDelete('completed'));
        $this->assertFalse(BookingPaymentState::canDelete('free'));
        $this->assertFalse(BookingPaymentState::canDelete(1));
        $this->assertTrue(BookingPaymentState::canDelete('pending'));
        $this->assertTrue(BookingPaymentState::canDelete('rejected'));
    }
}

