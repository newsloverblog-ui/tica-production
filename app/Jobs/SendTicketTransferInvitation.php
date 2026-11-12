<?php

namespace App\Jobs;

use App\Mail\TicketTransferInvitationMail;
use App\Models\Event\BookingTicketTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendTicketTransferInvitation implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $transferId, public string $plainToken)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'ticket-transfer-invitation:'.$this->transferId.':'.hash('sha256', $this->plainToken);
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('ticket-transfer-invitation:'.$this->transferId))->expireAfter(900)];
    }

    public function handle(): void
    {
        $transfer = BookingTicketTransfer::query()
            ->with('ticket.booking.evnt.information')
            ->findOrFail($this->transferId);

        if ($transfer->delivery_status === 'sent') {
            if ($transfer->dispatch_token !== null) {
                $transfer->forceFill(['dispatch_token' => null])->save();
            }
            return;
        }

        if ($transfer->status !== 'pending'
            || $transfer->expires_at->isPast()
            || !hash_equals($transfer->token_hash, hash('sha256', $this->plainToken))) {
            $transfer->forceFill([
                'delivery_status' => 'suppressed',
                'processing_at' => null,
                'last_error' => null,
                'dispatch_token' => null,
            ])->save();

            return;
        }

        $event = $transfer->ticket?->booking?->evnt;
        if (!$event
            || (int) $event->status !== 1
            || ($event->lifecycle_status ?: 'scheduled') !== 'scheduled'
            || $event->cancelled_at) {
            $transfer->forceFill([
                'delivery_status' => 'suppressed',
                'processing_at' => null,
                'last_error' => null,
                'dispatch_token' => null,
            ])->save();

            return;
        }

        $transfer->forceFill([
            'delivery_status' => 'processing',
            'delivery_attempts' => (int) $transfer->delivery_attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            if (!filter_var($transfer->recipient_email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The ticket transfer does not have a deliverable recipient email address.');
            }

            $eventTitle = (string) ($event->information?->title ?: 'your event');
            Mail::to($transfer->recipient_email)->send(
                new TicketTransferInvitationMail($transfer, $this->plainToken, $eventTitle)
            );

            $transfer->forceFill([
                'delivery_status' => 'sent',
                'sent_at' => now(),
                'processing_at' => null,
                'last_error' => null,
                'dispatch_token' => null,
                'dispatch_last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $transfer->forceFill([
                'delivery_status' => 'failed',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }
    }
}
