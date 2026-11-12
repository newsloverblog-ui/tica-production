<?php

namespace App\Services;

use App\Jobs\FinalizePaidProductOrder;
use App\Models\ProductOrderDelivery;
use Throwable;

class PaidProductOrderFulfillmentService
{
    public function dispatch(int $productOrderId): ProductOrderDelivery
    {
        $delivery = ProductOrderDelivery::firstOrCreate(
            ['product_order_id' => $productOrderId, 'kind' => 'product_order_confirmation'],
            ['status' => 'pending']
        );

        if ($delivery->status !== 'sent') {
            $this->dispatchDelivery($delivery);
        }

        return $delivery;
    }

    protected function dispatchJob(int $productOrderId): void
    {
        FinalizePaidProductOrder::dispatch($productOrderId)->afterCommit();
    }

    public function dispatchDelivery(ProductOrderDelivery $delivery): bool
    {
        if ($delivery->status !== 'pending' || $delivery->processing_at !== null) {
            return false;
        }

        $delivery->forceFill([
            'dispatch_attempts' => (int) $delivery->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->dispatchJob((int) $delivery->product_order_id);
            return true;
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'dispatch_last_error' => mb_substr(
                    'Product-order queue scheduling failed: '.$exception->getMessage(),
                    0,
                    2000
                ),
            ])->save();

            throw $exception;
        }
    }
}
