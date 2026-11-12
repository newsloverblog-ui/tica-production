<?php

namespace App\Mail;

use App\Services\LifecycleMailService;
use App\Support\LifecycleMailCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventCancellationNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public string $eventTitle,
        public string $reason,
        public ?string $customerMessage,
        public string $refundMessage,
        public string $bookingReference
    ) {
    }

    public function build()
    {
        $rendered = app(LifecycleMailService::class)->render(LifecycleMailCatalog::BUYER_EVENT_CANCELLED, [
            'first_name' => $this->customerName,
            'event_title' => $this->eventTitle,
            'reason' => $this->customerMessage ?: $this->reason,
            'order_reference' => $this->bookingReference,
            'refund_timing' => $this->refundMessage,
        ]);

        return $this->subject($rendered['subject'])->html($rendered['html']);
    }
}
