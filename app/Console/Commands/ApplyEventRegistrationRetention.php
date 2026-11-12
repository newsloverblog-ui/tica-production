<?php

namespace App\Console\Commands;

use App\Services\EventRegistrationRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ApplyEventRegistrationRetention extends Command
{
    protected $signature = 'registrations:apply-retention
        {--apply : Irreversibly anonymize eligible registration data}
        {--days= : Override the configured retention period (minimum 30 days)}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Preview or apply privacy-safe corporate registration retention';

    public function handle(EventRegistrationRetentionService $retention): int
    {
        $days = max(30, (int) ($this->option('days') ?: config('registration.retention_days', 730)));
        $eligible = (clone $retention->eligibleQuery($days))->count();
        $eligibleAllocations = Schema::hasTable('event_registration_allocations')
            ? (clone $retention->eligibleAllocationQuery($days))->count() : 0;
        $applied = $this->option('apply') ? $retention->anonymizeEligible($days) : 0;
        $appliedAllocations = $this->option('apply') ? $retention->anonymizeEligibleAllocations($days) : 0;
        $result = [
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'retention_days' => $days,
            'eligible' => $eligible,
            'anonymized' => $applied,
            'eligible_allocations' => $eligibleAllocations,
            'anonymized_allocations' => $appliedAllocations,
            'cutoff' => now()->subDays($days)->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(sprintf(
                '%s: %d registrations eligible; %d anonymized; %d sponsor allocations eligible; %d anonymized (retention: %d days).',
                $this->option('apply') ? 'Applied' : 'Dry run',
                $eligible,
                $applied,
                $eligibleAllocations,
                $appliedAllocations,
                $days,
            ));
        }

        return self::SUCCESS;
    }
}
