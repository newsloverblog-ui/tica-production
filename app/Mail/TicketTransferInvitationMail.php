<?php

namespace App\Mail;

use App\Models\Event\BookingTicketTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TicketTransferInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public string $eventTitle;
    public string $invitationUrl;
    public string $expiresAt;

    public function __construct(BookingTicketTransfer $transfer, string $plainToken, string $eventTitle)
    {
        $this->recipientName = $transfer->recipient_name;
        $this->eventTitle = $eventTitle;
        $this->expiresAt = $transfer->expires_at->translatedFormat('D, M j, Y g:i a T');
        $this->invitationUrl = URL::temporarySignedRoute(
            'ticket.transfer.invitation',
            $transfer->expires_at,
            ['transfer' => $transfer->id, 'token' => $plainToken]
        );
    }

    public function build()
    {
        return $this->subject('A Tica ticket has been transferred to you')
            ->markdown('emails.ticket-transfer-invitation');
    }
}
