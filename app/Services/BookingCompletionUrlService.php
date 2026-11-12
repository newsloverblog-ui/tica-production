<?php

namespace App\Services;

use App\Models\Event\Booking;
use Illuminate\Support\Facades\URL;

final class BookingCompletionUrlService
{
    public function booking(int $eventId, int $bookingId): Booking
    {
        return Booking::query()
            ->whereKey($bookingId)
            ->where('event_id', $eventId)
            ->firstOrFail();
    }

    public function make(int $eventId, int $bookingId, ?string $via = null): string
    {
        $parameters = [
            'id' => $eventId,
            'booking_id' => $bookingId,
        ];

        if ($via !== null && $via !== '') {
            $parameters['via'] = $via;
        }

        return URL::temporarySignedRoute(
            'event_booking.complete',
            now()->addHours(24),
            $parameters
        );
    }
}
