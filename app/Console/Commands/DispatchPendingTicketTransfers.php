<?php

namespace App\Console\Commands;

use App\Models\Event\BookingTicketTransfer;
use App\Services\IncidentAlertService;
use App\Services\TicketLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingTicketTransfers extends Command
{
    protected $signature = 'tickets:dispatch-transfers {--limit=100} {--stale-minutes=10}';
    protected $description = 'Recover pending ticket-transfer invitation queue handoffs';

    public function handle(TicketLifecycleService $tickets, IncidentAlertService $alerts): int
    {
        if (!Schema::hasTable('booking_ticket_transfers')
            || !Schema::hasColumn('booking_ticket_transfers', 'dispatch_token')) {
            $this->error('The ticket transfer dispatch migration is not applied.');
            return self::FAILURE;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $transfers = BookingTicketTransfer::query()
            ->where('status', 'pending')
            ->where('delivery_status', 'pending')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();
        foreach ($transfers as $transfer) {
            $attempted++;
            if (!$tickets->dispatchTransferInvitation($transfer->id)) {
                $failed++;
            }
            $transfer->refresh();
            if ($transfer->delivery_status === 'pending'
                && $transfer->created_at->lt(now()->subMinutes($staleMinutes))
                && $transfer->alerted_at === null) {
                $transfer->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'ticket-transfer-dispatch-pending:'.$transfer->id,
                    'A committed ticket-transfer invitation did not start on the queue.',
                    [
                        'transfer_id' => $transfer->id,
                        'booking_ticket_id' => $transfer->booking_ticket_id,
                        'dispatch_attempts' => $transfer->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Ticket transfer dispatch attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
