<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use App\Services\DataExportService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingDataExports extends Command
{
    protected $signature = 'exports:dispatch-pending {--limit=100} {--stale-minutes=30}';

    protected $description = 'Recover queued private export generation jobs whose queue handoff did not start';

    public function handle(DataExportService $exports, IncidentAlertService $alerts): int
    {
        if (!Schema::hasTable('data_exports') || !Schema::hasColumn('data_exports', 'dispatch_attempts')) {
            $this->error('The data-export dispatch metadata migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $pending = DataExport::query()
            ->where('status', 'queued')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pending as $export) {
            $attempted++;
            if (!$exports->dispatchQueued($export->id)) {
                $failed++;
            }

            $export->refresh();
            if ($export->status === 'queued'
                && $export->created_at->lt(now()->subMinutes($staleMinutes))
                && $export->dispatch_alerted_at === null) {
                $export->forceFill(['dispatch_alerted_at' => now()])->save();
                $alerts->alert('data-export-dispatch-pending:'.$export->id,
                    'Private data export generation did not start within the expected window.', [
                        'export_id' => $export->public_id,
                        'export_type' => $export->export_type,
                        'requested_by_type' => $export->requested_by_type,
                        'dispatch_attempts' => $export->dispatch_attempts,
                    ], 'critical');
                $alerted++;
            }
        }

        $this->line(sprintf('Export dispatch attempted: %d | failed: %d | alerted: %d', $attempted, $failed, $alerted));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
