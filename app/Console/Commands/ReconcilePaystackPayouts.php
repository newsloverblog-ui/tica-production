<?php

namespace App\Console\Commands;

use App\Services\PayoutTransferService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

class ReconcilePaystackPayouts extends Command implements Isolatable
{
    protected $isolated = true;

    protected $isolatedExitCode = self::FAILURE;

    protected $signature = 'payouts:reconcile-paystack
        {--limit=100 : Maximum unresolved transfers to verify (1-500)}
        {--json : Emit machine-readable JSON}';
    protected $description = 'Verify unresolved Paystack organizer payout transfers';

    public function handle(PayoutTransferService $service): int
    {
        $rawLimit = (string) $this->option('limit');
        if (!ctype_digit($rawLimit) || (int) $rawLimit < 1 || (int) $rawLimit > 500) {
            $this->error('The --limit value must be an integer between 1 and 500.');
            return self::INVALID;
        }

        if (!(bool) config('payouts.paystack_transfers_enabled', false)) {
            $this->line('Paystack payout transfers are disabled; nothing to reconcile.');
            return self::SUCCESS;
        }

        $summary = $service->reconcileDue(null, (int) $rawLimit);
        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        } else {
            $this->line(sprintf(
                'Checked: %d | Updated: %d | Errors: %d',
                $summary['checked'], $summary['updated'], $summary['errors']
            ));
        }
        return $summary['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    public function isolationLockExpiresAt(): \DateTimeInterface
    {
        return now()->addHours(2);
    }
}
