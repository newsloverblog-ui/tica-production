<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyAuditLogRetention extends Command
{
    protected $signature = 'audit:apply-retention {--apply : Permanently delete audit records older than policy} {--json : Emit machine-readable output}';
    protected $description = 'Preview or apply the approved audit-log retention policy';

    public function handle(): int
    {
        $days = max(365, (int) config('audit.retention_days', 2555));
        $cutoff = now()->subDays($days);
        $query = DB::table('audit_logs')->where('created_at', '<', $cutoff);
        $eligible = (clone $query)->count();
        $apply = (bool) $this->option('apply');

        if ($apply && !(bool) config('audit.retention_enabled', false)) {
            $this->error('Audit-log retention is not approved. Set AUDIT_LOG_RETENTION_ENABLED=true only after policy approval.');
            return self::FAILURE;
        }

        $deleted = 0;
        if ($apply && $eligible > 0) {
            do {
                $ids = DB::table('audit_logs')->where('created_at', '<', $cutoff)
                    ->orderBy('id')->limit(1000)->pluck('id');
                if ($ids->isEmpty()) {
                    break;
                }
                $deleted += DB::table('audit_logs')->whereIn('id', $ids)->delete();
            } while ($ids->count() === 1000);

            AuditLogger::record('audit.retention_applied', null, [], [], [
                'cutoff' => $cutoff->toIso8601String(),
                'retention_days' => $days,
                'deleted_count' => $deleted,
            ]);
        }

        $result = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'eligible' => $eligible,
            'deleted' => $deleted,
        ];
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(sprintf(
                '%s: %d eligible, %d deleted (cutoff %s).',
                $apply ? 'Applied' : 'Dry run',
                $eligible,
                $deleted,
                $cutoff->toDateString()
            ));
        }

        return self::SUCCESS;
    }
}
