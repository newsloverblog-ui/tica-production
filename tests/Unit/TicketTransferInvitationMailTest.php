<?php

namespace Tests\Unit;

use App\Mail\TicketTransferInvitationMail;
use App\Models\Event\BookingTicketTransfer;
use Illuminate\Http\Request;
use Tests\TestCase;

class TicketTransferInvitationMailTest extends TestCase
{
    public function test_invitation_is_expiring_and_tamper_evident(): void
    {
        $transfer = new BookingTicketTransfer([
            'recipient_name' => 'Chidi Okafor',
            'recipient_email' => 'chidi@example.com',
            'expires_at' => now()->addHours(48),
        ]);
        $transfer->id = 42;

        $mail = new TicketTransferInvitationMail($transfer, 'one-time-secret', 'Live in Lagos');

        $this->assertTrue(Request::create($mail->invitationUrl)->hasValidSignature());
        $this->assertFalse(Request::create(str_replace('one-time-secret', 'tampered', $mail->invitationUrl))->hasValidSignature());
    }
}
