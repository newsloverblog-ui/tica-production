<?php

namespace App\Console\Commands;

use App\Models\BookingDelivery;
use App\Models\ProductOrderDelivery;
use App\Services\IncidentAlertService;
use App\Services\PaidBookingFulfillmentService;
use App\Services\PaidProductOrderFulfillmentService;
use App\Services\ManualPaymentDecisionNotificationService;
use App\Services\TicketSmsService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DispatchPendingFulfillments extends Command
{
    protected $signature = 'payments:dispatch-fulfillments {--limit=200} {--stale-minutes=10}';

    protected $description = 'Recover pending paid-booking and product-order queue handoffs';

    public function handle(
        PaidBookingFulfillmentService $bookings,
        PaidProductOrderFulfillmentService $orders,
        ManualPaymentDecisionNotificationService $decisions,
        TicketSmsService $sms,
        IncidentAlertService $alerts
    ): int {
        if (!$this->schemaReady()) {
            $this->error('The fulfillment dispatch migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $eventDeliveries = BookingDelivery::query()
            ->where('kind', 'event_booking_confirmation')
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->where(function ($query): void {
                $query->whereNull('dispatch_last_attempt_at')
                    ->orWhere('dispatch_last_attempt_at', '<=', now()->subMinute());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($eventDeliveries as $delivery) {
            $attempted++;
            try {
                $bookings->dispatchDelivery($delivery);
            } catch (Throwable) {
                $failed++;
            }
            $alerted += $this->alertIfStale($delivery->fresh(), $staleMinutes, $alerts);
        }

        $remaining = max(0, $limit - $eventDeliveries->count());
        $bookingRejections = BookingDelivery::query()
            ->where('kind', 'event_booking_rejection')
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->where(function ($query): void {
                $query->whereNull('dispatch_last_attempt_at')
                    ->orWhere('dispatch_last_attempt_at', '<=', now()->subMinute());
            })
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        foreach ($bookingRejections as $delivery) {
            $attempted++;
            try {
                $decisions->dispatchBookingDelivery($delivery);
            } catch (Throwable) {
                $failed++;
            }
            $alerted += $this->alertIfStale($delivery->fresh(), $staleMinutes, $alerts);
        }

        $remaining = max(0, $remaining - $bookingRejections->count());
        $smsDeliveries = $sms->enabled()
            ? BookingDelivery::query()
                ->where('kind', 'event_booking_sms')
                ->where('status', 'pending')
                ->whereNull('processing_at')
                ->where(function ($query): void {
                    $query->whereNull('dispatch_last_attempt_at')
                        ->orWhere('dispatch_last_attempt_at', '<=', now()->subMinute());
                })
                ->orderBy('id')
                ->limit($remaining)
                ->get()
            : collect();

        foreach ($smsDeliveries as $delivery) {
            $attempted++;
            try {
                $sms->dispatchDelivery($delivery);
            } catch (Throwable) {
                $failed++;
            }
            $alerted += $this->alertIfStale($delivery->fresh(), $staleMinutes, $alerts);
        }

        $remaining = max(0, $remaining - $smsDeliveries->count());
        $productDeliveries = ProductOrderDelivery::query()
            ->where('kind', 'product_order_confirmation')
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->where(function ($query): void {
                $query->whereNull('dispatch_last_attempt_at')
                    ->orWhere('dispatch_last_attempt_at', '<=', now()->subMinute());
            })
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        foreach ($productDeliveries as $delivery) {
            $attempted++;
            try {
                $orders->dispatchDelivery($delivery);
            } catch (Throwable) {
                $failed++;
            }
            $alerted += $this->alertIfStale($delivery->fresh(), $staleMinutes, $alerts);
        }

        $remaining = max(0, $remaining - $productDeliveries->count());
        $productRejections = ProductOrderDelivery::query()
            ->where('kind', 'product_order_rejection')
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->where(function ($query): void {
                $query->whereNull('dispatch_last_attempt_at')
                    ->orWhere('dispatch_last_attempt_at', '<=', now()->subMinute());
            })
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        foreach ($productRejections as $delivery) {
            $attempted++;
            try {
                $decisions->dispatchProductOrderDelivery($delivery);
            } catch (Throwable) {
                $failed++;
            }
            $alerted += $this->alertIfStale($delivery->fresh(), $staleMinutes, $alerts);
        }

        $this->line(sprintf(
            'Fulfillment dispatch attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function alertIfStale(
        Model $delivery,
        int $staleMinutes,
        IncidentAlertService $alerts
    ): int {
        if ($delivery->status !== 'pending'
            || $delivery->processing_at !== null
            || $delivery->created_at->gte(now()->subMinutes($staleMinutes))
            || $delivery->alerted_at !== null) {
            return 0;
        }

        $delivery->forceFill(['alerted_at' => now()])->save();
        $kind = (string) $delivery->kind;

        $alerts->alert(
            'fulfillment-dispatch-pending:'.$kind.':'.$delivery->getKey(),
            'A committed payment fulfillment or decision notice did not start on the queue.',
            [
                'record_type' => $kind,
                'record_id' => $delivery->getKey(),
                'dispatch_attempts' => $delivery->dispatch_attempts,
            ],
            'critical'
        );

        return 1;
    }

    private function schemaReady(): bool
    {
        foreach (['booking_deliveries', 'product_order_deliveries'] as $table) {
            if (!Schema::hasTable($table)
                || !Schema::hasColumns($table, [
                    'dispatch_attempts',
                    'dispatch_last_attempt_at',
                    'dispatch_last_error',
                ])) {
                return false;
            }
        }

        return true;
    }
}
