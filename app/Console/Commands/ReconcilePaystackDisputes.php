<?php

namespace App\Console\Commands;

use App\Models\PaystackDispute;
use App\Services\PaystackDisputeService;
use App\Services\PaystackGatewayClient;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use RuntimeException;

class ReconcilePaystackDisputes extends Command implements Isolatable
{
    protected $isolated = true;
    protected $isolatedExitCode = self::FAILURE;
    protected $signature = 'payments:reconcile-paystack-disputes';
    protected $description = 'Recover and reconcile Paystack chargebacks';

    public function handle(PaystackDisputeService $disputes, IncidentAlertService $alerts): int
    {
        try {
            if (!PaystackDispute::available()) {
                throw new RuntimeException('The Paystack disputes table is not available.');
            }
            $client = app(PaystackGatewayClient::class);
            $processed = 0;
            for ($page = 1; $page <= 1000; $page++) {
                $payload = $client->listDisputes($page);
                $pageCount = filter_var($payload['meta']['pageCount'] ?? null, FILTER_VALIDATE_INT);
                if ($pageCount === false || $pageCount < 0 || $pageCount > 1000
                    || ((int) ($payload['meta']['page'] ?? 0) !== $page && !($page === 1 && $pageCount === 0 && $payload['data'] === []))) {
                    throw new RuntimeException('Dispute pagination was incomplete.');
                }
                foreach ($payload['data'] as $row) {
                    if (!is_array($row) || !$disputes->record($row)) {
                        throw new RuntimeException('A dispute record did not have a valid identity.');
                    }
                    $processed++;
                }
                if ($page >= $pageCount) {
                    $this->info("Processed {$processed} Paystack disputes.");
                    return self::SUCCESS;
                }
            }
        } catch (\Throwable) {
            $alerts->alert('paystack-dispute-reconciliation', 'Paystack dispute reconciliation did not complete.', []);
            $this->error('Paystack dispute reconciliation did not complete.');
            return self::FAILURE;
        }
        return self::FAILURE;
    }
}
