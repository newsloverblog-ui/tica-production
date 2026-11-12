<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventRegistrationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $registrantName,
        public string $eventTitle,
        public string $registrationReference,
        public string $statusLabel,
        public string $statusMessage,
        public ?string $reviewNote = null,
        public array $answerSummary = [],
        public int $omittedAnswerCount = 0,
    ) {
    }

    public function build()
    {
        return $this->subject($this->statusLabel.': '.$this->eventTitle)
            ->markdown('emails.event-registration-status');
    }
}
