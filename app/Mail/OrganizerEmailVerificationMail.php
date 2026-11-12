<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganizerEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $mailSubject, public string $mailBody)
    {
    }

    public function build()
    {
        return $this->subject($this->mailSubject)->html($this->mailBody);
    }
}
