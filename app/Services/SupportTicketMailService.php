<?php

namespace App\Services;

use App\Models\SupportTicket;

class SupportTicketMailService
{
    public function created(SupportTicket $ticket): void
    {
        $this->queue('support_ticket_created', $ticket, [
            'ticket_subject' => $ticket->subject,
        ], 'created');
    }

    public function replied(SupportTicket $ticket, string $reply): void
    {
        $this->queue('support_ticket_reply', $ticket, [
            'ticket_subject' => $ticket->subject,
            'reply_excerpt' => mb_substr(trim(strip_tags($reply)), 0, 240),
        ], 'reply:'.(string) $ticket->last_message);
    }

    public function closed(SupportTicket $ticket): void
    {
        $this->queue('support_ticket_closed', $ticket, [
            'ticket_subject' => $ticket->subject,
        ], 'closed');
    }

    private function queue(string $type, SupportTicket $ticket, array $data, string $dedupe): void
    {
        $actor = $ticket->user_type === 'organizer' ? $ticket->organizer : $ticket->customer;
        $name = $ticket->user_type === 'organizer'
            ? ($actor?->username ?: 'there')
            : (trim((string) $actor?->fname.' '.(string) $actor?->lname) ?: 'there');
        $url = $ticket->user_type === 'organizer'
            ? url('/organizer/support-ticket/message/'.$ticket->id)
            : url('/customer/support-ticket/message/'.$ticket->id);
        app(MailTemplateService::class)->queueSafely($type, $ticket->email ?: $actor?->email, $data + [
            'account_name' => $name,
            'ticket_id' => $ticket->id,
            'ticket_link' => $url,
        ], $ticket, $dedupe);
    }
}
