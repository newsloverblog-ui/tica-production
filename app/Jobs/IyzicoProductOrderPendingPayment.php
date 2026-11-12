<?php

namespace App\Jobs;

use App\Models\ShopManagement\ProductOrder;
use App\Services\Iyzipay;
use App\Services\PaidProductOrderFulfillmentService;
use App\Services\ProductOrderAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IyzicoProductOrderPendingPayment implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;
    public int $uniqueFor = 600;

    public function __construct(public int $order_id)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return 'iyzico-product-' . $this->order_id;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(120)];
    }

    public function handle(): void
    {
        $productOrder = ProductOrder::find($this->order_id);
        if (!$productOrder
            || !in_array(strtolower((string) $productOrder->payment_status), ['pending', 'completed'], true)
            || strcasecmp((string) $productOrder->method, 'Iyzico') !== 0
            || !$productOrder->conversation_id) {
            return;
        }

        if (strtolower((string) $productOrder->payment_status) === 'completed') {
            app(ProductOrderAccountingService::class)->recordCompleted(
                $productOrder,
                'iyzico',
                (string) $productOrder->conversation_id
            );
            app(PaidProductOrderFulfillmentService::class)->dispatch($this->order_id);

            return;
        }

        $options = Iyzipay::options();
        $request = new \Iyzipay\Request\ReportingPaymentDetailRequest();
        $request->setPaymentConversationId($productOrder->conversation_id);
        $paymentResponse = \Iyzipay\Model\ReportingPaymentDetail::create($request, $options);
        if (!$this->isPaid($paymentResponse)) {
            return;
        }

        $processed = false;
        DB::transaction(function () use (&$processed): void {
            $order = ProductOrder::whereKey($this->order_id)->lockForUpdate()->first();
            if (!$order) {
                return;
            }

            $reference = (string) $order->conversation_id;
            $status = strtolower((string) $order->payment_status);
            if ($status === 'pending') {
                $order->forceFill(['payment_status' => 'completed'])->save();
            } elseif ($status !== 'completed') {
                return;
            }

            app(ProductOrderAccountingService::class)->recordCompleted($order, 'iyzico', $reference);
            $processed = true;
        }, 3);

        if ($processed) {
            app(PaidProductOrderFulfillmentService::class)->dispatch($this->order_id);
        }
    }

    private function isPaid(object $response): bool
    {
        foreach ((array) $response as $value) {
            $data = is_string($value) ? json_decode($value, true) : null;
            if (is_array($data) && ($data['status'] ?? null) === 'success'
                && isset($data['payments'][0]) && (int) ($data['payments'][0]['paymentStatus'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }
}
