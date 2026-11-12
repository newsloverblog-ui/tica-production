<?php

namespace App\Console\Commands;

use App\Services\PaystackReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

class ReconcilePaystackPayments extends Command implements Isolatable
{
    protected $isolated = true;

    protected $isolatedExitCode = self::FAILURE;

    protected $signature = 'payments:reconcile-paystack {--date= : Date to reconcile in YYYY-MM-DD format}';
    protected $description = 'Compare successful Paystack charges with local payment intents and bookings';

    public function handle(PaystackReconciliationService $service): int
    {
        try {
            $date = $this->option('date')
                ? $this->parseDate($this->option('date'))
                : CarbonImmutable::yesterday();
        } catch (\Throwable $exception) {
            $this->error('The --date value must use YYYY-MM-DD.');
            return self::INVALID;
        }

        $run = $service->reconcile($date);
        $this->line(sprintf(
            'Paystack %s: %d matched, %d mismatched (%s).',
            $date->toDateString(),
            $run->matched_count,
            $run->mismatch_count,
            $run->public_id
        ));

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }

    public function isolationLockExpiresAt(): \DateTimeInterface
    {
        return now()->addHours(2);
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
