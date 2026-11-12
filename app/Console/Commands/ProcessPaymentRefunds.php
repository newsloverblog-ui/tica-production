<?php

namespace App\Console\Commands;

use App\Models\PaymentRefund;
use App\Models\ProductGatewayRefund;
use App\Services\PaymentRefundService;
use App\Services\ProductGatewayRefundService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Schema;

class ProcessPaymentRefunds extends Command implements Isolatable
{
    protected $isolated = true;

    protected $isolatedExitCode = self::FAILURE;

    protected $signature = 'payments:process-refunds {--limit=20}';

    protected $description = 'Submit approved refund records to their payment gateway';

    public function isolationLockExpiresAt(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(PaymentRefundService $service, ProductGatewayRefundService $productService): int
    {
        $refunds = PaymentRefund::where('status', 'requested')
            ->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))
            ->get();

        $submitted = 0;
        $attention = 0;
        foreach ($refunds as $refund) {
            $result = $service->submit($refund);
            if (in_array($result->status, ['pending', 'processing', 'processed'], true)) {
                $submitted++;
            } else {
                $attention++;
            }
        }

        $productRefunds = Schema::hasTable('product_gateway_refunds')
            ? ProductGatewayRefund::where('status', 'requested')
            ->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))
            ->get()
            : collect();
        foreach ($productRefunds as $refund) {
            $result = $productService->submit($refund);
            if (in_array($result->status, ['pending', 'processing', 'processed'], true)) {
                $submitted++;
            } else {
                $attention++;
            }
        }

        $this->info(sprintf('Submitted %d refund(s); %d require review.', $submitted, $attention));
        return $attention === 0 ? self::SUCCESS : self::FAILURE;
    }
}
