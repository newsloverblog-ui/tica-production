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

        // Sign the path rather than the deployment host. TICA runs behind Vercel's
        // proxy, where the public scheme/host can differ from Laravel's request
        // context during the Paystack return. The route remains tamper-evident,
        // while the resulting link can safely survive that proxy boundary.
        $relativeUrl = URL::temporarySignedRoute(
            'event_booking.complete',
            now()->addHours(24),
            $parameters,
            false
        );

        return URL::to($relativeUrl);
    }
}
