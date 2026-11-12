<?php

namespace App\Console\Commands;

use App\Services\OfflineLedgerBackfillService;
use Illuminate\Console\Command;

class BackfillOfflineLedgerIdentities extends Command
{
    protected $signature = 'ledger:backfill-offline-identities
        {--apply : Persist only exact verified mappings; the default is a dry run}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Audit completed offline bookings and safely attach stable sale-ledger identities';

    public function handle(OfflineLedgerBackfillService $service): int
    {
        $result = $service->run((bool) $this->option('apply'));

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Mode', 'Scanned', 'Would update', 'Updated', 'Already linked', 'Missing', 'Conflicts', 'Errors'],
                [[
                    $result['mode'], $result['scanned'], $result['would_update'], $result['updated'],
                    $result['already_linked'], $result['missing'], $result['conflicts'], $result['errors'],
                ]]
            );
            if ($result['issues'] !== []) {
                $this->table(
                    ['Booking ID', 'Booking reference', 'Status', 'Reason', 'Transaction'],
                    array_map(static fn (array $issue): array => [
                        $issue['booking_id'], $issue['booking_reference'], $issue['status'],
                        $issue['reason'], $issue['transaction_id'],
                    ], $result['issues'])
                );
            }
            if ($result['issues_truncated'] > 0) {
                $this->warn($result['issues_truncated'].' additional issues were omitted from console output.');
            }
            if (!$this->option('apply') && $result['would_update'] > 0) {
                $this->info('Dry run only. Review every missing/conflicting sale before rerunning with --apply.');
            }
        }

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
