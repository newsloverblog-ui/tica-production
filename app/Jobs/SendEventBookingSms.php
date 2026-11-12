<?php

namespace App\Jobs;

use App\Models\BookingDelivery;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Services\IncidentAlertService;
use App\Services\TicketSmsService;
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

final class SendEventBookingSms implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $uniqueFor = 3600;

    public function __construct(public int $bookingId)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return 'event-booking-sms-'.$this->bookingId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(120)];
    }

    public function handle(TicketSmsService $sms, IncidentAlertService $alerts): void
    {
        $booking = Booking::query()->findOrFail($this->bookingId);
        if (!in_array(strtolower((string) $booking->paymentStatus), ['1', 'paid', 'completed', 'free'], true)) {
            throw new RuntimeException('Ticket SMS cannot be delivered before the booking is completed.');
        }

        $delivery = BookingDelivery::firstOrCreate(
            ['booking_id' => $booking->id, 'kind' => 'event_booking_sms'],
            ['channel' => 'sms', 'status' => 'pending']
        );
        $lifecycle = Event::query()->find((int) $booking->event_id)?->lifecycle_status ?: 'scheduled';
        if (in_array($lifecycle, ['cancelled', 'cancellation_pending'], true)) {
            $delivery->forceFill([
                'status' => $lifecycle === 'cancelled' ? 'cancelled' : 'awaiting_event_review',
                'last_error' => 'Ticket SMS suppressed because the event is not in a deliverable state.',
            ])->save();
            return;
        }
        if ($delivery->status === 'sent') {
            return;
        }

        $delivery->forceFill([
            'channel' => 'sms',
            'status' => 'processing',
            'attempts' => (int) $delivery->attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            $result = $sms->send($booking);
            $delivery->forceFill([
                'status' => 'sent',
                'recipient_hash' => $result['recipient_hash'],
                'provider_reference' => $result['provider_reference'],
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('ticket-sms-delivery-failed:'.$delivery->id, 'Ticket SMS delivery failed and requires review.', [
                'delivery_id' => $delivery->id,
                'booking_id' => $booking->id,
                'attempts' => $delivery->attempts,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ], 'critical');
            throw $exception;
        }
    }
}
