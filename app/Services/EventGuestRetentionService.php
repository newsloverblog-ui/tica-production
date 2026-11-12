<?php

namespace App\Services;

use App\Models\EventGuestInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EventGuestRetentionService
{
    public function eligibleQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays(max(30, $retentionDays));

        return EventGuestInvitation::query()
            ->whereNull('anonymized_at')
            ->where('created_at', '<', $cutoff)
            ->whereIn('delivery_status', ['sent', 'suppressed', 'expired'])
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereIn('status', ['declined', 'cancelled', 'expired'])
                    ->orWhere(function (Builder $pending) use ($cutoff): void {
                        $pending->where('status', 'pending')->where('expires_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $accepted) use ($cutoff): void {
                        $accepted->where('status', 'accepted')
                            ->whereHas('event', function (Builder $event) use ($cutoff): void {
                                $event->where(function (Builder $ended) use ($cutoff): void {
                                    $ended->where('end_date_time', '<', $cutoff)
                                        ->orWhere(function (Builder $fallback) use ($cutoff): void {
                                            $fallback->whereNull('end_date_time')
                                                ->whereDate('end_date', '<', $cutoff->toDateString());
                                        });
                                });
                            });
                    });
            });
    }

    public function anonymizeEligible(int $retentionDays, int $chunkSize = 100): int
    {
        $anonymized = 0;
        $this->eligibleQuery($retentionDays)->orderBy('id')
            ->chunkById(max(1, min(500, $chunkSize)), function ($invitations) use (&$anonymized, $retentionDays): void {
                foreach ($invitations as $invitation) {
                    $anonymized += $this->anonymizeOne((int) $invitation->id, $retentionDays) ? 1 : 0;
                }
            });
        return $anonymized;
    }

    private function anonymizeOne(int $invitationId, int $retentionDays): bool
    {
        return DB::transaction(function () use ($invitationId, $retentionDays): bool {
            $invitation = $this->eligibleQuery($retentionDays)->whereKey($invitationId)->lockForUpdate()->first();
            if (!$invitation) return false;

            $invitation->forceFill([
                'name' => 'Anonymized guest',
                'email' => 'anonymized+'.strtolower($invitation->public_id).'@invalid.tica.local',
                'email_hash' => hash_hmac('sha256', 'anonymized:'.$invitation->public_id, (string) config('app.key')),
                'token_hash' => hash('sha256', 'retired:'.$invitation->public_id.':'.config('app.key')),
                'dispatch_token' => null,
                'source_attested_ip_hash' => null,
                'rsvp_consent_at' => null,
                'marketing_consent_at' => null,
                'last_error' => null,
                'anonymized_at' => now(),
                'anonymization_reason' => 'retention_policy',
            ])->save();

            AuditLogger::record('event.guest_invitation_anonymized', $invitation, ['anonymized' => false], [
                'anonymized' => true, 'status' => $invitation->status,
            ], ['event_id' => $invitation->event_id, 'retention_days' => max(30, $retentionDays)]);
            return true;
        }, 3);
    }
}
