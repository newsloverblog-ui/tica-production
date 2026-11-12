<?php

namespace App\Jobs;

use App\Mail\EventCancellationNoticeMail;
use App\Models\EventCancellationNotification;
use App\Models\EventCancellationCase;
use App\Models\PaymentRefund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendEventCancellationNotice implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $notificationId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'event-cancellation-notice:' . $this->notificationId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(): void
    {
        $notification = EventCancellationNotification::with([
            'cancellationRequest.event.information',
            'booking',
        ])->findOrFail($this->notificationId);
        if ($notification->status === 'sent') {
            return;
        }
        if ($notification->cancellationRequest->status !== 'approved') {
            return;
        }

        $notification->forceFill([
            'status' => 'processing',
            'attempts' => (int) $notification->attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            $booking = $notification->booking;
            if (!$booking || !filter_var($booking->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The cancelled booking does not have a deliverable email address.');
            }
            $request = $notification->cancellationRequest;
            $event = $request->event;
            $refund = PaymentRefund::where('event_cancellation_request_id', $request->id)
                ->where('booking_id', $booking->id)
                ->first();
            $case = EventCancellationCase::where('event_cancellation_request_id', $request->id)
                ->where('booking_id', $booking->id)
                ->first();
            $refundMessage = $case?->status === 'external_refund_recorded'
                ? 'Finance has verified that your refund of '.strtoupper((string) ($refund?->currency ?: 'NGN')).' '
                    .number_format((float) $case->amount, 2).' was completed outside the ticket gateway.'
                : ($case?->status === 'resolved_no_refund'
                    ? 'Finance has completed its review and determined that no refund is due for this booking. Contact support if you need the decision reviewed.'
                    : ($refund
                ? 'A refund of ' . strtoupper($refund->currency) . ' ' . number_format((float) $refund->amount, 2)
                    . ' has been queued to your original payment method. Processing time depends on your bank.'
                : (((float) $booking->price + (float) $booking->tax - (float) $booking->refunded_amount) > 0
                    ? 'Your payment requires a manual finance review. Our team will contact you with the refund outcome.'
                    : 'No payment is outstanding for this booking.')));

            Mail::to($booking->email)->send(new EventCancellationNoticeMail(
                trim($booking->fname . ' ' . $booking->lname) ?: 'Guest',
                (string) ($event->information?->title ?: 'Your event'),
                $request->reason,
                $request->customer_message,
                $refundMessage,
                (string) $booking->booking_id
            ));
            $notification->forceFill([
                'status' => 'sent',
                'processing_at' => null,
                'sent_at' => now(),
                'last_error' => null,
                'alerted_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            $notification->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
