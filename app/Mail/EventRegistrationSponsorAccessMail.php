<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventRegistrationSponsorAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $sponsorName,
        public string $eventTitle,
        public int $capacity,
        public string $accessUrl,
        public string $expiresAtLabel,
    ) {}

    public function build()
    {
        return $this->subject('Manage your attendee allocation: '.$this->eventTitle)
            ->markdown('emails.event-registration-sponsor-access');
    }
}
