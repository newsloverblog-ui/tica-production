<?php

namespace App\Console\Commands;

use App\Models\EventWaitlistEntry;
use App\Services\EventWaitlistService;
use App\Services\IncidentAlertService;
use App\Services\WaitlistNotificationDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchEventWaitlist extends Command
{
    protected $signature = 'events:dispatch-waitlist {--ticket=} {--limit=200} {--stale-minutes=10}';

    protected $description = 'Notify waiting attendees when limited event tickets become available';

    public function handle(
        EventWaitlistService $waitlist,
        WaitlistNotificationDispatchService $notifications,
        IncidentAlertService $alerts
    ): int
    {
        if (!Schema::hasTable('event_waitlist_entries')
            || !Schema::hasColumn('event_waitlist_entries', 'dispatch_attempts')) {
            $this->error('The waitlist notification dispatch migration is not applied.');
            return self::FAILURE;
        }

        $ticketId = $this->option('ticket');
        $queued = $waitlist->notifyAvailable($ticketId ? (int) $ticketId : null);
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $entries = EventWaitlistEntry::query()
            ->where('status', 'notification_pending')
            ->whereNull('processing_at')
            ->where('notification_expires_at', '>', now())
            ->when($ticketId, fn ($query) => $query->where('ticket_id', (int) $ticketId))
            ->where(function ($query): void {
                $query->whereNull('dispatch_last_attempt_at')
                    ->orWhere('dispatch_last_attempt_at', '<=', now()->subMinute());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($entries as $entry) {
            $attempted++;
            if (!$notifications->dispatchNotification((int) $entry->id)) {
                $failed++;
            }

            $entry->refresh();
            if ($entry->status === 'notification_pending'
                && $entry->notification_pending_at?->lt(now()->subMinutes($staleMinutes))
                && $entry->alerted_at === null) {
                $entry->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'event-waitlist-dispatch-pending:'.$entry->id,
                    'A committed waitlist availability notice did not start on the queue.',
                    [
                        'waitlist_entry_id' => $entry->id,
                        'event_id' => $entry->event_id,
                        'ticket_id' => $entry->ticket_id,
                        'dispatch_attempts' => $entry->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Waitlist notifications queued: %d | recovery attempted: %d | failed: %d | alerted: %d',
            $queued,
            $attempted,
            $failed,
            $alerted
        ));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
