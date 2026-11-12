<?php

namespace App\Console\Commands;

use App\Services\PaystackSettlementReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Console\Command;

class ReconcilePaystackSettlements extends Command implements Isolatable
{
    protected $isolated = true;

    protected $isolatedExitCode = self::FAILURE;

    protected $signature = 'payments:reconcile-paystack-settlements
        {--from= : First settlement date in YYYY-MM-DD format}
        {--to= : Last settlement date in YYYY-MM-DD format}';
    protected $description = 'Reconcile Paystack bank settlements and their transactions with local payments';

    public function isolationLockExpiresAt(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(PaystackSettlementReconciliationService $service): int
    {
        try {
            $from = $this->option('from')
                ? $this->parseDate($this->option('from'))
                : CarbonImmutable::today()->subDays(7);
            $to = $this->option('to')
                ? $this->parseDate($this->option('to'))
                : CarbonImmutable::today();
            if (!$from || !$to || $from->greaterThan($to)) {
                throw new \InvalidArgumentException();
            }
        } catch (\Throwable $exception) {
            $this->error('Use valid YYYY-MM-DD dates and ensure --from is not after --to.');
            return self::INVALID;
        }

        try {
            $settlements = $service->reconcile($from, $to);
        } catch (\Throwable $exception) {
            $this->error('Paystack settlement reconciliation failed before the requested provider range completed.');
            return self::FAILURE;
        }
        $failed = collect($settlements)->where('reconciliation_status', 'failed')->count();
        $mismatched = collect($settlements)->where('reconciliation_status', 'mismatched')->count();
        $this->line(sprintf(
            'Reviewed %d Paystack settlements: %d mismatched, %d failed.',
            count($settlements), $mismatched, $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function parseDate(string $value): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid date.');
        }
        return $date;
    }
}
