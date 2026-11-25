<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PaystackDispute extends Model
{
    protected $guarded = ['id'];

    public static function available(): bool
    {
        return Schema::hasTable('paystack_disputes');
    }

    public static function blocksPayment(string $reference): bool
    {
        return self::available()
            && static::where('reference', $reference)->where('status', '!=', 'declined')->exists();
    }

    public static function blocksBooking(int $bookingId): bool
    {
        return self::available()
            && static::where('booking_id', $bookingId)->where('status', '!=', 'declined')->exists();
    }

    public static function holdsOrganizer(int $organizerId): bool
    {
        return self::available()
            && static::where('organizer_id', $organizerId)->whereNull('reviewed_at')
                ->whereIn('status', ['open', 'charged_back', 'needs_attention'])->exists();
    }
}
