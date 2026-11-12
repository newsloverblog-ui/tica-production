<?php

namespace App\Console\Commands;

use App\Models\EventRegistrationAllocation;
use App\Models\EventRegistrationAllocationClaim;
use App\Services\EventRegistrationAllocationService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingRegistrationAllocations extends Command
{
    protected $signature = 'registrations:dispatch-allocations {--limit=100} {--stale-minutes=10}';
    protected $description = 'Recover pending sponsor and attendee allocation invitation queue handoffs';

    public function handle(
        EventRegistrationAllocationService $allocations,
        IncidentAlertService $alerts
    ): int {
        foreach (['event_registration_allocations', 'event_registration_allocation_claims'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'dispatch_token')) {
                $this->error('The registration allocation dispatch migration is not applied.');
                return self::FAILURE;
            }
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $sponsorAllocations = EventRegistrationAllocation::query()
            ->where('status', 'active')
            ->where('delivery_status', 'pending')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();
        foreach ($sponsorAllocations as $allocation) {
            $attempted++;
            if (!$allocations->dispatchSponsorAccess($allocation->id)) {
                $failed++;
            }
            $allocation->refresh();
            if ($allocation->delivery_status === 'pending'
                && $allocation->created_at->lt(now()->subMinutes($staleMinutes))
                && $allocation->alerted_at === null) {
                $allocation->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'registration-allocation-dispatch-pending:'.$allocation->id,
                    'A committed sponsor allocation invitation did not start on the queue.',
                    [
                        'record_type' => 'event_registration_allocations',
                        'record_id' => $allocation->id,
                        'event_id' => $allocation->event_id,
                        'dispatch_attempts' => $allocation->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $remaining = max(0, $limit - $sponsorAllocations->count());
        $claims = $remaining > 0
            ? EventRegistrationAllocationClaim::query()
                ->where('status', 'pending')
                ->where('delivery_status', 'pending')
                ->where('expires_at', '>', now())
                ->orderBy('id')
                ->limit($remaining)
                ->get()
            : collect();
        foreach ($claims as $claim) {
            $attempted++;
            if (!$allocations->dispatchClaimInvitation($claim->id)) {
                $failed++;
            }
            $claim->refresh();
            if ($claim->delivery_status === 'pending'
                && $claim->created_at->lt(now()->subMinutes($staleMinutes))
                && $claim->alerted_at === null) {
                $claim->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'registration-allocation-claim-dispatch-pending:'.$claim->id,
                    'A committed attendee allocation invitation did not start on the queue.',
                    [
                        'record_type' => 'event_registration_allocation_claims',
                        'record_id' => $claim->id,
                        'event_id' => $claim->event_id,
                        'allocation_id' => $claim->allocation_id,
                        'dispatch_attempts' => $claim->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Registration allocation dispatch attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
