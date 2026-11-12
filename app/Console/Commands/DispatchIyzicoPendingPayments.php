<?php

namespace App\Console\Commands;

use App\Jobs\IyzicoEventPendingPayment;
use App\Jobs\IyzicoProductOrderPendingPayment;
use App\Models\Event\Booking;
use App\Models\ShopManagement\ProductOrder;
use Illuminate\Console\Command;

class DispatchIyzicoPendingPayments extends Command
{
    protected $signature = 'payments:dispatch-iyzico-pending {--limit=500 : Maximum records of each type to dispatch} {--json : Emit machine-readable JSON}';
    protected $description = 'Queue bounded verification for legacy pending Iyzico event and product payments';

    public function handle(): int
    {
        $limit = min(5000, max(1, (int) $this->option('limit')));
        $eventCount = 0;
        $productCount = 0;

        Booking::query()
            ->where('paymentStatus', 'pending')
            ->where('paymentMethod', 'Iyzico')
            ->whereNotNull('conversation_id')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function ($id) use (&$eventCount): void {
                IyzicoEventPendingPayment::dispatch((int) $id);
                $eventCount++;
            });

        ProductOrder::query()
            ->where('payment_status', 'pending')
            ->where('method', 'Iyzico')
            ->whereNotNull('conversation_id')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function ($id) use (&$productCount): void {
                IyzicoProductOrderPendingPayment::dispatch((int) $id);
                $productCount++;
            });

        $result = ['event_bookings' => $eventCount, 'product_orders' => $productCount, 'limit' => $limit];
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Iyzico verification queued: {$eventCount} event booking(s), {$productCount} product order(s).");
        }

        return self::SUCCESS;
    }
}
