<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventGuestInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $guestName,
        public string $eventTitle,
        public int $partySize,
        public string $rsvpUrl,
        public string $expiresAtLabel
    ) {
    }

    public function build()
    {
        return $this->subject('RSVP invitation: ' . $this->eventTitle)
            ->markdown('emails.event-guest-invitation');
    }
}
