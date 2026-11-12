<?php

namespace App\Jobs;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\BookingDelivery;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Services\FunnelAnalyticsService;
use App\Services\EventBookingDocumentStorage;
use App\Services\EventCancellationService;
use App\Services\EventWaitlistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class FinalizePaidEventBooking implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
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
        return 'paid-booking-' . $this->bookingId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(BookingController $bookings): void
    {
        $booking = Booking::findOrFail($this->bookingId);
        if (!in_array(strtolower((string) $booking->paymentStatus), ['1', 'paid', 'completed', 'free'], true)) {
            throw new RuntimeException('Booking confirmation cannot be delivered before the booking is completed.');
        }
        app(EventWaitlistService::class)->markConvertedForBooking($booking);

        $delivery = BookingDelivery::firstOrCreate(
            ['booking_id' => $booking->id, 'kind' => 'event_booking_confirmation'],
            ['status' => 'pending']
        );
        $lifecycle = Schema::hasTable('events') && Schema::hasColumn('events', 'lifecycle_status')
            ? (Event::find($booking->event_id)?->lifecycle_status ?: 'scheduled')
            : 'scheduled';
        if ($lifecycle === 'cancelled') {
            app(EventCancellationService::class)->queueLateCompletedBooking($booking->id);
            $delivery->forceFill([
                'status' => 'cancelled',
                'last_error' => 'Normal confirmation suppressed because the event was cancelled.',
            ])->save();
            return;
        }
        if ($lifecycle === 'cancellation_pending') {
            $delivery->forceFill([
                'status' => 'awaiting_event_review',
                'last_error' => 'Confirmation paused while event cancellation is under review.',
            ])->save();
            return;
        }
        if ($delivery->status === 'sent') {
            app(FunnelAnalyticsService::class)->recordServerSafely('ticket_delivered', [
                'event_id' => $booking->event_id,
                'booking_id' => $booking->id,
                'organizer_id' => $booking->organizer_id,
                'customer_id' => $booking->customer_id,
                'kind' => $delivery->kind,
            ], 'ticket_delivered:' . $delivery->id);
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
            $invoiceAvailable = false;
            if ($booking->invoice) {
                try {
                    app(EventBookingDocumentStorage::class)->invoiceAttachmentPath($booking);
                    $invoiceAvailable = true;
                } catch (RuntimeException $exception) {
                    $invoiceAvailable = false;
                }
            }
            if (!$invoiceAvailable) {
                $invoice = $bookings->generateInvoice($booking, $booking->event_id);
                $booking->forceFill(['invoice' => $invoice])->save();
            }

            $bookings->sendMail($booking->fresh(), true);
            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
            app(FunnelAnalyticsService::class)->recordServerSafely('ticket_delivered', [
                'event_id' => $booking->event_id,
                'booking_id' => $booking->id,
                'organizer_id' => $booking->organizer_id,
                'customer_id' => $booking->customer_id,
                'kind' => $delivery->kind,
            ], 'ticket_delivered:' . $delivery->id);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
