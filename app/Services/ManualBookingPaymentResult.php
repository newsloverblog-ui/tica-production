<?php

namespace App\Services;

use App\Models\Event\Booking;

final readonly class ManualBookingPaymentResult
{
    public function __construct(
        public Booking $booking,
        public array $before,
        public bool $changed,
    ) {}
}
