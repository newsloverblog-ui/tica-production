<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use App\Models\Event\Booking;
use App\Services\FunnelAnalyticsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PaymentIntent extends Model
{
    protected $fillable = [
        'gateway', 'reference', 'event_id', 'customer_id', 'booking_id',
        'status', 'amount', 'currency', 'email', 'payload', 'authorization_url', 'last_error',
        'gateway_transaction_id', 'verification_attempts', 'last_verified_at', 'expires_at', 'processed_at',
    ];

    protected $casts = [
        'payload' => 'encrypted:array',
        'authorization_url' => 'encrypted',
        'email' => PrefixedEncryptedString::class,
        'last_error' => PrefixedEncryptedString::class,
        'amount' => 'decimal:2',
        'verification_attempts' => 'integer',
        'last_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $intent): void {
            if (in_array((string) $intent->status, ['initialized', 'completed', 'failed'], true)) {
                self::recordStatusAnalytics($intent);
            }
        });

        static::updated(function (self $intent): void {
            if (!$intent->wasChanged('status')) {
                return;
            }

            self::recordStatusAnalytics($intent);
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    private static function recordStatusAnalytics(self $intent): void
    {
        $eventName = match ((string) $intent->status) {
                'initialized' => 'payment_attempt',
                'completed' => 'payment_success',
                'failed' => 'payment_failed',
                default => null,
            };
        if (!$eventName) {
            return;
        }

        $booking = null;
        try {
            $booking = $intent->booking_id && Schema::hasTable('bookings')
                ? Booking::find($intent->booking_id)
                : null;
        } catch (Throwable $exception) {
            // Analytics enrichment must never interrupt a payment state transition.
        }
        app(FunnelAnalyticsService::class)->recordServerSafely($eventName, [
            'event_id' => $intent->event_id,
            'booking_id' => $intent->booking_id,
            'payment_intent_id' => $intent->id,
            'organizer_id' => optional($booking)->organizer_id,
            'customer_id' => $intent->customer_id,
            'gateway' => $intent->gateway,
            'amount' => (float) $intent->amount,
            'currency' => $intent->currency,
            'status' => $intent->status,
        ], 'payment:' . $eventName . ':' . $intent->id);
    }
}

