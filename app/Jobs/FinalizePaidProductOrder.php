<?php

namespace App\Jobs;

use App\Http\Controllers\FrontEnd\Shop\OrderController;
use App\Models\ProductOrderDelivery;
use App\Models\ShopManagement\ProductOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class FinalizePaidProductOrder implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $productOrderId)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return 'paid-product-order-'.$this->productOrderId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(OrderController $orders): void
    {
        $order = ProductOrder::findOrFail($this->productOrderId);
        if (strtolower((string) $order->payment_status) !== 'completed') {
            throw new RuntimeException('Product-order confirmation cannot be delivered before payment is complete.');
        }

        $delivery = ProductOrderDelivery::firstOrCreate(
            ['product_order_id' => $order->id, 'kind' => 'product_order_confirmation'],
            ['status' => 'pending']
        );
        if ($delivery->status === 'sent') {
            return;
        }

        $delivery->forceFill([
            'status' => 'processing',
            'attempts' => (int) $delivery->attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            if (!$order->invoice_number) {
                $invoice = $orders->generateInvoice($order);
                $order->forceFill(['invoice_number' => $invoice])->save();
            }

            $orders->sendMail($order->fresh(), true);
            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
