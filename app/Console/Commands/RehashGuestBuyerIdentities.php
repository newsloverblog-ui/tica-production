<?php

namespace App\Console\Commands;

use App\Services\GuestBuyerIdentityRehashService;
use Illuminate\Console\Command;
use Throwable;

final class RehashGuestBuyerIdentities extends Command
{
    protected $signature = 'tickets:rehash-guest-buyer-identities
        {--apply : Rehash guest bookings to the configured key version}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Audit or rehash privacy-safe guest ticket buyer identities';

    public function handle(GuestBuyerIdentityRehashService $service): int
    {
        try {
            $report = $service->inspect();
            if (!$this->option('apply')) {
                $this->render($report, 'Dry run only; no rows changed.');

                return ($report['invalid_guest_emails'] > 0 || $report['stale_active_reservations'] > 0)
                    ? self::FAILURE
                    : self::SUCCESS;
            }

            if (!app()->isDownForMaintenance()) {
                $this->error('Enter maintenance mode before rehashing guest buyer identities.');

                return self::FAILURE;
            }

            $report = $service->rehash();
            $this->render($report, 'Guest buyer identities rehashed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function render(array $report, string $message): void
    {
        if ($this->option('json')) {
            $this->line(json_encode(['message' => $message, 'report' => $report], JSON_PRETTY_PRINT));

            return;
        }

        $this->info($message);
        $this->table(['Metric', 'Value'], collect($report)->map(
            fn ($value, $key): array => [$key, $value]
        )->values()->all());
    }
}
