<?php

namespace App\Console\Commands;

use App\Services\AutomaticPayoutReleaseService;
use Illuminate\Console\Command;
use RuntimeException;

class ReleaseEligiblePayouts extends Command
{
    protected $signature = 'payouts:release-eligible
        {--apply : Queue eligible withdrawals; without this option the command is a dry run}
        {--limit= : Maximum pending withdrawals to inspect, bounded by the approved policy}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Evaluate next-business-day payout eligibility and optionally queue approved transfers';

    public function handle(AutomaticPayoutReleaseService $service): int
    {
        $limit = $this->option('limit');
        if ($limit !== null && (!ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('The --limit value must be a positive integer.');
            return self::INVALID;
        }

        try {
            $summary = $service->run((bool) $this->option('apply'), $limit === null ? null : (int) $limit);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf(
                'Mode: %s | Checked: %d | Eligible: %d | Ineligible: %d | Queued: %d | Errors: %d',
                $summary['mode'],
                $summary['checked'],
                $summary['eligible'],
                $summary['ineligible'],
                $summary['queued'],
                $summary['errors']
            ));
            foreach ($summary['reason_counts'] as $reason => $count) {
                $this->line(sprintf('  %s: %d', $reason, $count));
            }
        }

        return $summary['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
