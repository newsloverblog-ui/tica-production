<?php

namespace App\Console\Commands;

use App\Services\GatewayLedgerBackfillService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BackfillGatewayLedgerIdentities extends Command
{
    protected $signature = 'ledger:backfill-gateway-identities
        {--apply : Persist verified mappings; the default is a dry run}
        {--gateway=* : Restrict the audit to one or more hardened gateways}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Safely map historical completed payment intents to sale ledger gateway identities';

    public function handle(GatewayLedgerBackfillService $service): int
    {
        try {
            $result = $service->run(
                (bool) $this->option('apply'),
                (array) $this->option('gateway')
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::INVALID;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Mode', 'Scanned', 'Would update', 'Updated', 'Already linked', 'Missing', 'Conflicts', 'Errors'], [[
                $result['mode'], $result['scanned'], $result['would_update'], $result['updated'],
                $result['already_linked'], $result['missing'], $result['conflicts'], $result['errors'],
            ]]);
            if ($result['issues'] !== []) {
                $this->table(
                    ['Intent', 'Gateway', 'Reference', 'Booking', 'Status', 'Reason', 'Transaction'],
                    array_map(fn ($issue) => [
                        $issue['payment_intent_id'], $issue['gateway'], $issue['reference'],
                        $issue['booking_id'], $issue['status'], $issue['reason'], $issue['transaction_id'],
                    ], $result['issues'])
                );
            }
            if ($result['issues_truncated'] > 0) {
                $this->warn($result['issues_truncated'] . ' additional issues were omitted from console output.');
            }
            if (!$this->option('apply') && $result['would_update'] > 0) {
                $this->info('Dry run only. Review all issues, then rerun with --apply.');
            }
        }

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
