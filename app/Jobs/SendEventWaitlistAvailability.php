<?php

namespace App\Jobs;

use App\Mail\EventWaitlistAvailableMail;
use App\Models\Event\TicketContent;
use App\Models\EventWaitlistEntry;
use App\Services\EventWaitlistService;
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
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendEventWaitlistAvailability implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $entryId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'event-waitlist-availability:' . $this->entryId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(EventWaitlistService $waitlist): void
    {
        $entry = EventWaitlistEntry::with(['event.information', 'ticket'])->findOrFail($this->entryId);
        if (!in_array($entry->status, ['notification_pending', 'failed'], true)) {
            return;
        }
        if (!$entry->notification_expires_at || $entry->notification_expires_at->isPast()) {
            $entry->forceFill(['status' => 'expired'])->save();
            return;
        }
        if (!$entry->event || !$entry->event->isBookable() || !$entry->ticket
            || $waitlist->availableQuantity($entry->ticket, $entry->variation_name) < 1) {
            $entry->forceFill([
                'status' => 'active',
                'notification_pending_at' => null,
                'notification_expires_at' => null,
                'processing_at' => null,
            ])->save();
            return;
        }

        $entry->forceFill([
            'status' => 'notification_pending',
            'processing_at' => now(),
            'attempts' => (int) $entry->attempts + 1,
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            if (!filter_var($entry->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The waitlist entry does not have a deliverable email address.');
            }
            $eventTitle = (string) ($entry->event->information?->title ?: 'Your event');
            $ticketTitle = (string) (TicketContent::where('ticket_id', $entry->ticket_id)->value('title') ?: 'Event ticket');
            $ticketLabel = $entry->variation_name
                ? $ticketTitle . ' — ' . $entry->variation_name
                : $ticketTitle;
            $eventSlug = (string) ($entry->event->information?->slug ?: Str::slug($eventTitle));
            $unsubscribeUrl = URL::signedRoute('event.waitlist.unsubscribe.confirm', ['waitlist' => $entry]);

            Mail::to($entry->email)->send(new EventWaitlistAvailableMail(
                $entry->name,
                $eventTitle,
                $ticketLabel,
                route('event.details', [$eventSlug, $entry->event_id]),
                $unsubscribeUrl,
                $entry->notification_expires_at->timezone(config('app.timezone'))->format('j M Y, g:i A T')
            ));
            $entry->forceFill([
                'status' => 'notified',
                'notified_at' => now(),
                'processing_at' => null,
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $entry->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
