<?php

namespace App\Console\Commands;

use App\Models\PaymentRefund;
use App\Models\ProductGatewayRefund;
use App\Services\PaymentRefundService;
use App\Services\ProductGatewayRefundService;
use App\Services\FlutterwaveGatewayClient;
use App\Services\PaystackGatewayClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReconcilePaymentRefunds extends Command implements Isolatable
{
    protected $isolated = true;

    protected $isolatedExitCode = self::FAILURE;

    protected $signature = 'payments:reconcile-refunds {--limit=50}';

    protected $description = 'Refresh non-final gateway refunds when webhooks are delayed or unavailable';

    public function isolationLockExpiresAt(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(PaymentRefundService $service, ProductGatewayRefundService $productService): int
    {
        $refunds = PaymentRefund::whereIn('status', [
            'submitting', 'pending', 'processing', 'needs_attention', 'verification_required',
        ])->where(function ($query): void {
            $query->whereNotNull('gateway_refund_id')
                ->orWhere(function ($query): void {
                    $query->where('gateway', 'paystack')
                        ->whereIn('status', ['submitting', 'verification_required']);
                });
        })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))
            ->get();

        $updated = 0;
        $failed = 0;
        foreach ($refunds as $refund) {
            try {
                if ($refund->gateway === 'paystack' && !$refund->gateway_refund_id) {
                    $service->recoverUnknownPaystackSubmission($refund);
                    $updated++;
                    continue;
                }
                $client = match ($refund->gateway) {
                    'paystack' => app(PaystackGatewayClient::class),
                    'flutterwave' => app(FlutterwaveGatewayClient::class),
                    default => throw new \RuntimeException('Unsupported refund gateway.'),
                };
                $payload = $client->fetchRefund($refund->gateway_refund_id);
                $data = $payload['data'] ?? [];
                if ($refund->gateway === 'paystack') {
                    $service->recordGatewayStatus($refund, (string) ($data['status'] ?? 'pending'), $data);
                } else {
                    $service->recordGatewayStatus($refund, (string) ($data['status'] ?? 'processing'), $data);
                }
                $updated++;
            } catch (Throwable $exception) {
                $failed++;
                $refund->forceFill([
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                ])->save();
                Log::error('Refund reconciliation failed', [
                    'refund_id' => $refund->public_id,
                    'exception_type' => $exception::class,
                    'exception_code' => (string) $exception->getCode(),
                ]);
            }
        }

        $productRefunds = Schema::hasTable('product_gateway_refunds')
            ? ProductGatewayRefund::whereIn('status', [
            'submitting', 'pending', 'processing', 'needs_attention', 'verification_required',
        ])->where(function ($query): void {
            $query->whereNotNull('gateway_refund_id')
                ->orWhereIn('status', ['submitting', 'verification_required']);
        })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))
            ->get()
            : collect();
        foreach ($productRefunds as $refund) {
            try {
                if (!$refund->gateway_refund_id) {
                    $productService->recoverUnknownSubmission($refund);
                    $updated++;
                    continue;
                }
                $payload = app(PaystackGatewayClient::class)->fetchRefund($refund->gateway_refund_id);
                $data = (array) ($payload['data'] ?? []);
                $productService->recordGatewayStatus($refund, (string) ($data['status'] ?? 'pending'), $data);
                $updated++;
            } catch (Throwable $exception) {
                $failed++;
                $refund->forceFill([
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                ])->save();
                Log::error('Product refund reconciliation failed', [
                    'refund_id' => $refund->public_id,
                    'exception_type' => $exception::class,
                    'exception_code' => (string) $exception->getCode(),
                ]);
            }
        }

        $this->info(sprintf('Reconciled %d refund(s); %d lookup(s) failed.', $updated, $failed));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
