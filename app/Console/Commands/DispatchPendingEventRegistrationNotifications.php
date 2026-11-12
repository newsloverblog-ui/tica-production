<?php

namespace App\Console\Commands;

use App\Models\EventRegistrationNotification;
use App\Services\EventRegistrationNotificationDispatchService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingEventRegistrationNotifications extends Command
{
    protected $signature = 'registrations:dispatch-notifications {--limit=200} {--stale-minutes=10}';
    protected $description = 'Recover pending event-registration notification queue handoffs';

    public function handle(
        EventRegistrationNotificationDispatchService $notifications,
        IncidentAlertService $alerts
    ): int {
        if (!Schema::hasTable('event_registration_notifications')
            || !Schema::hasColumn('event_registration_notifications', 'dispatch_attempts')) {
            $this->error('The event-registration notification dispatch migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $deliveries = EventRegistrationNotification::query()
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            $attempted++;
            if (!$notifications->dispatchNotification((int) $delivery->id)) {
                $failed++;
            }

            $delivery->refresh();
            if ($delivery->status === 'pending'
                && $delivery->created_at->lt(now()->subMinutes($staleMinutes))
                && $delivery->alerted_at === null) {
                $delivery->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'event-registration-notification-dispatch-pending:'.$delivery->id,
                    'A committed event-registration notification did not start on the queue.',
                    [
                        'notification_id' => $delivery->id,
                        'submission_id' => $delivery->submission_id,
                        'event_id' => $delivery->event_id,
                        'kind' => $delivery->kind,
                        'dispatch_attempts' => $delivery->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Registration notification dispatch attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
