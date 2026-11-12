<?php

namespace App\Console\Commands;

use App\Models\EventGuestInvitation;
use App\Services\EventGuestListService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingGuestInvitations extends Command
{
    protected $signature = 'guests:dispatch-invitations {--limit=100} {--stale-minutes=10}';
    protected $description = 'Recover pending guest invitation queue handoffs';

    public function handle(EventGuestListService $guests, IncidentAlertService $alerts): int
    {
        if (!Schema::hasTable('event_guest_invitations')
            || !Schema::hasColumn('event_guest_invitations', 'dispatch_token')) {
            $this->error('The guest invitation dispatch migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;
        $expired = EventGuestInvitation::query()
            ->where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'delivery_status' => 'expired',
                'dispatch_token' => null,
                'processing_at' => null,
                'updated_at' => now(),
            ]);

        $invitations = EventGuestInvitation::query()
            ->where('status', 'pending')
            ->where('delivery_status', 'pending')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($invitations as $invitation) {
            $attempted++;
            if (!$guests->dispatchInvitation((int) $invitation->id)) {
                $failed++;
            }
            $invitation->refresh();
            if ($invitation->delivery_status === 'pending'
                && $invitation->created_at->lt(now()->subMinutes($staleMinutes))
                && $invitation->alerted_at === null) {
                $invitation->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'guest-invitation-dispatch-pending:'.$invitation->id,
                    'A committed guest invitation did not start on the queue.',
                    [
                        'invitation_id' => $invitation->id,
                        'event_id' => $invitation->event_id,
                        'dispatch_attempts' => $invitation->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Guest invitation dispatch attempted: %d | failed: %d | alerted: %d | expired: %d',
            $attempted,
            $failed,
            $alerted,
            $expired
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
