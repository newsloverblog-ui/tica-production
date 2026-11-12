<?php

namespace App\Services;

use App\Jobs\SendRejectedEventBookingNotification;
use App\Jobs\SendRejectedProductOrderNotification;
use App\Models\BookingDelivery;
use App\Models\ProductOrderDelivery;
use InvalidArgumentException;
use Throwable;

class ManualPaymentDecisionNotificationService
{
    public function dispatchBookingRejection(int $bookingId): BookingDelivery
    {
        $delivery = BookingDelivery::firstOrCreate(
            ['booking_id' => $bookingId, 'kind' => 'event_booking_rejection'],
            ['status' => 'pending']
        );
        $this->dispatchBookingDelivery($delivery);

        return $delivery;
    }

    public function dispatchProductOrderRejection(int $orderId): ProductOrderDelivery
    {
        $delivery = ProductOrderDelivery::firstOrCreate(
            ['product_order_id' => $orderId, 'kind' => 'product_order_rejection'],
            ['status' => 'pending']
        );
        $this->dispatchProductOrderDelivery($delivery);

        return $delivery;
    }

    public function dispatchBookingDelivery(BookingDelivery $delivery): bool
    {
        if ($delivery->kind !== 'event_booking_rejection') {
            throw new InvalidArgumentException('This is not a booking rejection delivery.');
        }

        return $this->dispatch($delivery, fn () => $this->pushBookingJob((int) $delivery->booking_id));
    }

    public function dispatchProductOrderDelivery(ProductOrderDelivery $delivery): bool
    {
        if ($delivery->kind !== 'product_order_rejection') {
            throw new InvalidArgumentException('This is not a product-order rejection delivery.');
        }

        return $this->dispatch(
            $delivery,
            fn () => $this->pushProductOrderJob((int) $delivery->product_order_id)
        );
    }

    protected function pushBookingJob(int $bookingId): void
    {
        SendRejectedEventBookingNotification::dispatch($bookingId)->afterCommit();
    }

    protected function pushProductOrderJob(int $orderId): void
    {
        SendRejectedProductOrderNotification::dispatch($orderId)->afterCommit();
    }

    private function dispatch(BookingDelivery|ProductOrderDelivery $delivery, callable $push): bool
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
            $push();
            return true;
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'dispatch_last_error' => mb_substr(
                    'Manual payment decision queue scheduling failed: '.$exception->getMessage(),
                    0,
                    2000
                ),
            ])->save();
            throw $exception;
        }
    }
}
