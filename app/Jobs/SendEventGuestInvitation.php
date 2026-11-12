<?php

namespace App\Jobs;

use App\Mail\EventGuestInvitationMail;
use App\Models\EventGuestInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class SendEventGuestInvitation implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $invitationId, public string $token)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'event-guest-invitation:' . $this->invitationId . ':' . hash('sha256', $this->token);
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('event-guest-invitation:' . $this->invitationId))->expireAfter(900)];
    }

    public function handle(): void
    {
        $invitation = EventGuestInvitation::with('event.information')->findOrFail($this->invitationId);
        if ($invitation->status !== 'pending' || !hash_equals($invitation->token_hash, hash('sha256', $this->token))) {
            return;
        }
        if (!$invitation->event
            || (int) $invitation->event->status !== 1
            || ($invitation->event->lifecycle_status ?: 'scheduled') !== 'scheduled'
            || $invitation->event->cancelled_at
        ) {
            $invitation->forceFill([
                'status' => 'cancelled',
                'delivery_status' => 'suppressed',
                'dispatch_token' => null,
                'cancelled_at' => now(),
                'processing_at' => null,
                'last_error' => null,
            ])->save();

            return;
        }
        if ($invitation->expires_at->isPast()) {
            $invitation->forceFill([
                'status' => 'expired',
                'delivery_status' => 'expired',
                'dispatch_token' => null,
            ])->save();
            return;
        }

        $invitation->forceFill([
            'delivery_status' => 'processing',
            'processing_at' => now(),
            'delivery_attempts' => (int) $invitation->delivery_attempts + 1,
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            if (!filter_var($invitation->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The guest invitation does not have a deliverable email address.');
            }
            $eventTitle = (string) ($invitation->event?->information?->title ?: 'Your event');
            $url = URL::temporarySignedRoute('event.guest-rsvp.show', $invitation->expires_at, [
                'invitation' => $invitation,
                'token' => $this->token,
            ]);
            Mail::to($invitation->email)->send(new EventGuestInvitationMail(
                $invitation->name,
                $eventTitle,
                (int) $invitation->party_size,
                $url,
                $invitation->expires_at->timezone(config('app.timezone'))->format('j M Y, g:i A T')
            ));
            $invitation->forceFill([
                'delivery_status' => 'sent',
                'dispatch_token' => null,
                'invited_at' => now(),
                'processing_at' => null,
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $invitation->forceFill([
                'delivery_status' => 'failed',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
