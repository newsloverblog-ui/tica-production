<?php

namespace App\Services;

use App\Jobs\FinalizePaidEventBooking;
use App\Models\BookingDelivery;
use RuntimeException;
use Throwable;

class PaidBookingFulfillmentService
{
    public function dispatch(int $bookingId): BookingDelivery
    {
        $delivery = BookingDelivery::firstOrCreate(
            ['booking_id' => $bookingId, 'kind' => 'event_booking_confirmation'],
            ['status' => 'pending']
        );

        $failures = [];
        if ($delivery->status !== 'sent') {
            try {
                $this->dispatchDelivery($delivery);
            } catch (Throwable $exception) {
                $failures[] = 'email: '.$exception->getMessage();
            }
        }

        try {
            $this->dispatchSms($bookingId);
        } catch (Throwable $exception) {
            $failures[] = 'sms: '.$exception->getMessage();
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'Booking fulfillment scheduling was incomplete ('.implode('; ', $failures).').'
            );
        }

        return $delivery;
    }

    protected function dispatchEmail(int $bookingId): void
    {
        FinalizePaidEventBooking::dispatch($bookingId)->afterCommit();
    }

    public function dispatchDelivery(BookingDelivery $delivery): bool
    {
        if ($delivery->kind !== 'event_booking_confirmation'
            || $delivery->status !== 'pending'
            || $delivery->processing_at !== null) {
            return false;
        }

        $delivery->forceFill([
            'dispatch_attempts' => (int) $delivery->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->dispatchEmail((int) $delivery->booking_id);
            return true;
        } catch (Throwable $exception) {
            $this->recordSchedulingFailure($delivery, 'Email', $exception);

            throw $exception;
        }
    }

    protected function dispatchSms(int $bookingId): void
    {
        app(TicketSmsService::class)->dispatch($bookingId);
    }

    private function recordSchedulingFailure(
        BookingDelivery $delivery,
        string $channel,
        Throwable $exception
    ): void {
        $delivery->forceFill([
            'dispatch_last_error' => mb_substr(
                $channel.' queue scheduling failed: '.$exception->getMessage(),
                0,
                2000
            ),
        ])->save();
    }
}
