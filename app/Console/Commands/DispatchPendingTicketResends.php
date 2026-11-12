<?php

namespace App\Console\Commands;

use App\Models\BookingTicketDeliveryRequest;
use App\Services\BookingTicketDeliveryService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingTicketResends extends Command
{
    protected $signature = 'tickets:dispatch-resends {--limit=100} {--stale-minutes=10}';
    protected $description = 'Recover pending ticket-resend queue handoffs';

    public function handle(BookingTicketDeliveryService $tickets, IncidentAlertService $alerts): int
    {
        if (!Schema::hasTable('booking_ticket_delivery_requests')
            || !Schema::hasColumn('booking_ticket_delivery_requests', 'dispatch_attempts')) {
            $this->error('The ticket-resend dispatch migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $requests = BookingTicketDeliveryRequest::query()
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($requests as $request) {
            $attempted++;
            if (!$tickets->dispatchRequest((int) $request->id)) {
                $failed++;
            }

            $request->refresh();
            if ($request->status === 'pending'
                && $request->created_at->lt(now()->subMinutes($staleMinutes))
                && $request->alerted_at === null) {
                $request->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'ticket-resend-dispatch-pending:'.$request->id,
                    'A committed ticket-resend request did not start on the queue.',
                    [
                        'delivery_request_id' => $request->public_id,
                        'booking_id' => $request->booking_id,
                        'dispatch_attempts' => $request->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Ticket resend dispatch attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
