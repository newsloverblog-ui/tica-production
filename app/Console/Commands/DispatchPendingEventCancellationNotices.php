<?php

namespace App\Console\Commands;

use App\Models\EventCancellationNotification;
use App\Services\EventCancellationNotificationDispatchService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingEventCancellationNotices extends Command
{
    protected $signature = 'events:dispatch-cancellation-notices {--limit=200} {--stale-minutes=10}';
    protected $description = 'Recover pending event-cancellation notice queue handoffs';

    public function handle(
        EventCancellationNotificationDispatchService $notices,
        IncidentAlertService $alerts
    ): int {
        if (!Schema::hasTable('event_cancellation_notifications')
            || !Schema::hasColumn('event_cancellation_notifications', 'dispatch_attempts')) {
            $this->error('The event-cancellation notification dispatch migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $notifications = EventCancellationNotification::query()
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($notifications as $notification) {
            $attempted++;
            if (!$notices->dispatchNotice((int) $notification->id)) {
                $failed++;
            }

            $notification->refresh();
            if ($notification->status === 'pending'
                && $notification->created_at->lt(now()->subMinutes($staleMinutes))
                && $notification->alerted_at === null) {
                $notification->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'event-cancellation-notice-dispatch-pending:'.$notification->id,
                    'A committed event-cancellation notice did not start on the queue.',
                    [
                        'notification_id' => $notification->id,
                        'cancellation_request_id' => $notification->event_cancellation_request_id,
                        'booking_id' => $notification->booking_id,
                        'dispatch_attempts' => $notification->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Cancellation notice dispatch attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
