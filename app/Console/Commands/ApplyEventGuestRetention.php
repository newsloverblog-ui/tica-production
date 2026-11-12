<?php

namespace App\Console\Commands;

use App\Services\EventGuestRetentionService;
use Illuminate\Console\Command;

class ApplyEventGuestRetention extends Command
{
    protected $signature = 'guests:apply-retention
        {--apply : Irreversibly anonymize eligible guest data}
        {--days= : Override the configured retention period (minimum 30 days)}
        {--json : Emit machine-readable JSON}';
    protected $description = 'Preview or apply privacy-safe event guest retention';

    public function handle(EventGuestRetentionService $retention): int
    {
        $days = max(30, (int) ($this->option('days') ?: config('tickets.guest_retention_days', 730)));
        $eligible = (clone $retention->eligibleQuery($days))->count();
        $applied = $this->option('apply') ? $retention->anonymizeEligible($days) : 0;
        $result = ['mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'retention_days' => $days, 'eligible' => $eligible, 'anonymized' => $applied,
            'cutoff' => now()->subDays($days)->toIso8601String()];
        if ($this->option('json')) $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES));
        else $this->info(sprintf('%s: %d eligible; %d anonymized (retention: %d days).',
            $this->option('apply') ? 'Applied' : 'Dry run', $eligible, $applied, $days));
        return self::SUCCESS;
    }
}
