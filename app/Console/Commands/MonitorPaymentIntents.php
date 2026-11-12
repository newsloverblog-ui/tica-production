<?php

namespace App\Console\Commands;

use App\Models\PaymentIntent;
use App\Models\ProductPaymentIntent;
use App\Services\IncidentAlertService;
use App\Services\ProductOrderInventoryService;
use App\Services\ProductPaystackPaymentService;
use App\Services\TicketReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MonitorPaymentIntents extends Command
{
    protected $signature = 'payments:monitor-intents
        {--stale-minutes=15 : Age in minutes before a processing intent is stale}
        {--batch-size=100 : Maximum stale intent samples included per family (1-500)}';

    protected $description = 'Expire abandoned payment intents and report intents stuck in processing';

    public function handle(IncidentAlertService $alerts): int
    {
        $releasedReservations = (new TicketReservationService())->releaseExpired();

        $expired = PaymentIntent::whereIn('status', ['initializing', 'initialized', 'failed'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        [$expiredProductIntents, $productVerificationFailures] = $this->expireProductIntents($alerts);

        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $batchSize = max(1, min(500, (int) $this->option('batch-size')));
        $staleQuery = PaymentIntent::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($staleMinutes));
        $staleCount = (clone $staleQuery)->count();
        $stale = $staleQuery
            ->orderBy('id')
            ->limit($batchSize)
            ->get(['id', 'gateway', 'reference', 'event_id', 'amount', 'currency', 'updated_at']);

        $staleProduct = collect();
        $staleProductCount = 0;
        if (Schema::hasTable('product_payment_intents')) {
            $staleProductQuery = ProductPaymentIntent::whereIn('status', ['processing', 'verification_required'])
                ->where('updated_at', '<', now()->subMinutes($staleMinutes));
            $staleProductCount = (clone $staleProductQuery)->count();
            $staleProduct = $staleProductQuery
                ->orderBy('id')
                ->limit($batchSize)
                ->get(['id', 'gateway', 'reference', 'product_order_id', 'amount', 'currency', 'status', 'updated_at']);
        }

        if ($staleCount > 0) {
            $alerts->alert('payment-intents-stuck', 'Payment intents are stuck in processing.', [
                'count' => $staleCount,
                'sampled_count' => $stale->count(),
                'intents' => $stale->toArray(),
            ], 'critical');
        }
        if ($staleProductCount > 0) {
            $alerts->alert('product-payment-intents-stuck', 'Product payment intents require finance attention.', [
                'count' => $staleProductCount,
                'sampled_count' => $staleProduct->count(),
                'intents' => $staleProduct->toArray(),
            ], 'critical');
        }

        $this->info(sprintf(
            'Released %d expired ticket reservation(s); expired %d abandoned event intent(s) and %d product intent(s); found %d stale event and %d stale product intent(s); %d product verification failure(s).',
            $releasedReservations,
            $expired,
            $expiredProductIntents,
            $staleCount,
            $staleProductCount,
            $productVerificationFailures
        ));

        return $staleCount === 0 && $staleProductCount === 0 && $productVerificationFailures === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function expireProductIntents(IncidentAlertService $alerts): array
    {
        if (!Schema::hasTable('product_payment_intents')) {
            return [0, 0];
        }

        $expired = 0;
        $failures = 0;
        ProductPaymentIntent::query()
            ->whereIn('status', ['created', 'initialized'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (ProductPaymentIntent $intent) use (&$expired, &$failures, $alerts): void {
                try {
                    $outcome = app(ProductPaystackPaymentService::class)->verify($intent);
                    if (($outcome['status'] ?? null) === 'completed') {
                        return;
                    }
                    if (($outcome['status'] ?? null) === 'pending') {
                        $intent->refresh()->update(['status' => 'expired']);
                        app(ProductOrderInventoryService::class)->release(
                            $intent->order()->firstOrFail(),
                            'payment_intent_expired'
                        );
                        $expired++;
                    }
                } catch (\Throwable $exception) {
                    $failures++;
                    $alerts->alert(
                        'product-payment-expiry-verification:' . $intent->id,
                        'An expired product payment could not be verified before releasing stock.',
                        ['reference' => $intent->reference, 'order_id' => $intent->product_order_id]
                    );
                }
            });

        return [$expired, $failures];
    }
}

