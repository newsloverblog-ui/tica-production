<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventRegistrationAllocationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $attendeeName,
        public string $sponsorName,
        public string $eventTitle,
        public string $registrationUrl,
        public string $expiresAtLabel,
    ) {}

    public function build()
    {
        return $this->subject('Complete your event registration: '.$this->eventTitle)
            ->markdown('emails.event-registration-allocation-invitation');
    }
}
