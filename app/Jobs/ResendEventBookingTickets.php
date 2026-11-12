<?php

namespace App\Jobs;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\BookingTicketDeliveryRequest;
use App\Services\BookingTicketDeliveryService;
use App\Services\FunnelAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ResendEventBookingTickets implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryRequestId)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return 'booking-ticket-resend-'.$this->deliveryRequestId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(BookingController $bookings, BookingTicketDeliveryService $deliveryService): void
    {
        $request = BookingTicketDeliveryRequest::query()->with('booking')->findOrFail($this->deliveryRequestId);
        if ($request->status === 'sent') {
            return;
        }

        $booking = $request->booking;
        try {
            $deliveryService->assertDeliverable($booking);
        } catch (Throwable $exception) {
            $request->forceFill([
                'status' => 'cancelled',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return;
        }

        $request->forceFill([
            'status' => 'processing',
            'attempts' => (int) $request->attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            // Rebuild the private invoice so a reissued/transferred ticket never
            // sends an obsolete QR from an earlier document.
            $invoice = $bookings->generateInvoice($booking, $booking->event_id, true);
            $booking->forceFill(['invoice' => $invoice])->save();
            $bookings->sendMail($booking->fresh(), true);

            $request->forceFill([
                'status' => 'sent',
                'processing_at' => null,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();

            app(FunnelAnalyticsService::class)->recordServerSafely('ticket_delivered', [
                'event_id' => $booking->event_id,
                'booking_id' => $booking->id,
                'organizer_id' => $booking->organizer_id,
                'customer_id' => $booking->customer_id,
                'kind' => 'event_booking_resend',
            ], 'ticket_resend_delivered:'.$request->id);
        } catch (Throwable $exception) {
            $request->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
