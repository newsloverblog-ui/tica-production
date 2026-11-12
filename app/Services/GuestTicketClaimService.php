<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class GuestTicketClaimService
{
    public function claim(int $bookingId, Customer $customer): Booking
    {
        return DB::transaction(function () use ($bookingId, $customer): Booking {
            $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
            if (mb_strtolower((string) $booking->email) !== mb_strtolower((string) $customer->email)) {
                throw new AuthorizationException('Sign in with the email address used for this booking.');
            }
            if ($booking->customer_id && (int) $booking->customer_id !== (int) $customer->id) {
                throw new AuthorizationException('This booking already belongs to another account.');
            }
            if (!$booking->customer_id) {
                $booking->update(['customer_id' => $customer->id]);
                BookingTicket::where('booking_id', $booking->id)->whereNull('holder_customer_id')
                    ->update(['holder_customer_id' => $customer->id, 'updated_at' => now()]);
                AuditLogger::record('booking.guest_tickets_claimed', $booking, ['customer_id' => null], ['customer_id' => $customer->id]);
            }
            return $booking->fresh();
        }, 3);
    }
}
