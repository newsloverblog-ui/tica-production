<?php

namespace App\Mail;

use App\Services\LifecycleMailService;
use App\Support\LifecycleMailCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventWaitlistAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $eventTitle,
        public string $ticketLabel,
        public string $eventUrl,
        public string $unsubscribeUrl,
        public string $expiresAtLabel
    ) {
    }

    public function build()
    {
        $rendered = app(LifecycleMailService::class)->render(LifecycleMailCatalog::BUYER_WAITLIST_AVAILABLE, [
            'first_name' => $this->recipientName,
            'event_title' => $this->eventTitle,
            'ticket_label' => $this->ticketLabel,
            'checkout_url' => $this->eventUrl,
            'hold_expires_at' => $this->expiresAtLabel,
        ]);

        return $this->subject($rendered['subject'])->html($rendered['html']);
    }
}
