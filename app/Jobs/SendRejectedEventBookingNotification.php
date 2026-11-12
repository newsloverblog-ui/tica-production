<?php

namespace App\Jobs;

use App\Models\BookingDelivery;
use App\Models\Event\Booking;
use App\Services\ManualPaymentDecisionMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendRejectedEventBookingNotification implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $bookingId)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return 'rejected-event-booking-'.$this->bookingId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(ManualPaymentDecisionMailService $mail): void
    {
        $booking = Booking::query()->findOrFail($this->bookingId);
        if (strtolower((string) $booking->paymentStatus) !== 'rejected') {
            throw new RuntimeException('A booking rejection notice requires a rejected booking.');
        }

        $delivery = BookingDelivery::firstOrCreate(
            ['booking_id' => $booking->id, 'kind' => 'event_booking_rejection'],
            ['status' => 'pending']
        );
        if ($delivery->status === 'sent') {
            return;
        }

        $delivery->forceFill([
            'status' => 'processing',
            'attempts' => (int) $delivery->attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            $mail->sendBookingRejection($booking);
            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
