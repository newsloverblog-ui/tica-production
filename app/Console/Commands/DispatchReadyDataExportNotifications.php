<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use App\Services\DataExportReadyNotificationService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchReadyDataExportNotifications extends Command
{
    protected $signature = 'exports:notify-ready {--limit=200} {--stale-minutes=10}';
    protected $description = 'Create durable email notices for completed private exports';

    public function handle(
        DataExportReadyNotificationService $notifications,
        IncidentAlertService $alerts
    ): int {
        if (!Schema::hasTable('data_exports')
            || !Schema::hasColumn('data_exports', 'ready_notification_delivery_id')) {
            $this->error('The data-export ready-notification migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $exports = DataExport::query()
            ->where('status', 'ready')
            ->whereNull('ready_notification_delivery_id')
            ->whereNotNull('path')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($exports as $export) {
            $attempted++;
            if (!$notifications->dispatchNotification((int) $export->id)) {
                $failed++;
            }
            $export->refresh();
            if (!$export->ready_notification_delivery_id
                && $export->completed_at?->lt(now()->subMinutes($staleMinutes))
                && $export->ready_notification_alerted_at === null) {
                $export->forceFill(['ready_notification_alerted_at' => now()])->save();
                $alerts->alert(
                    'data-export-ready-notification-pending:'.$export->id,
                    'A completed private export has not notified its requester.',
                    [
                        'export_id' => $export->id,
                        'export_type' => $export->export_type,
                        'notification_attempts' => $export->ready_notification_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Export-ready notifications attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
