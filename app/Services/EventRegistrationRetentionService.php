<?php

namespace App\Services;

use App\Models\EventRegistrationSubmission;
use App\Models\EventRegistrationAllocation;
use App\Models\EventRegistrationAllocationClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EventRegistrationRetentionService
{
    public function eligibleQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays(max(30, $retentionDays));

        return EventRegistrationSubmission::query()
            ->whereNull('anonymized_at')
            ->where('submitted_at', '<', $cutoff)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereIn('status', ['rejected', 'cancelled'])
                    ->orWhere(function (Builder $query) use ($cutoff): void {
                        $query->whereIn('status', ['approved', 'ticketed'])
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
            })
            ->whereDoesntHave('notifications', function (Builder $notification): void {
                $notification->whereIn('status', ['pending', 'processing', 'failed']);
            });
    }

    public function anonymizeEligible(int $retentionDays, int $chunkSize = 100): int
    {
        $anonymized = 0;
        $this->eligibleQuery($retentionDays)
            ->orderBy('id')
            ->chunkById(max(1, min(500, $chunkSize)), function ($submissions) use (&$anonymized, $retentionDays): void {
                foreach ($submissions as $submission) {
                    $anonymized += $this->anonymizeOne((int) $submission->id, $retentionDays) ? 1 : 0;
                }
            });

        return $anonymized;
    }

    public function eligibleAllocationQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays(max(30, $retentionDays));

        return EventRegistrationAllocation::query()
            ->whereNull('anonymized_at')
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('delivery_status', ['pending', 'processing', 'failed'])
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereIn('status', ['cancelled', 'expired'])
                    ->orWhereHas('event', function (Builder $event) use ($cutoff): void {
                        $event->where(function (Builder $ended) use ($cutoff): void {
                            $ended->where('end_date_time', '<', $cutoff)
                                ->orWhere(function (Builder $fallback) use ($cutoff): void {
                                    $fallback->whereNull('end_date_time')->whereDate('end_date', '<', $cutoff->toDateString());
                                });
                        });
                    });
            })
            ->whereDoesntHave('claims', function (Builder $claim): void {
                $claim->whereNull('anonymized_at')->where(function (Builder $unsafe): void {
                    $unsafe->where('status', 'pending')
                        ->orWhereIn('delivery_status', ['pending', 'processing', 'failed'])
                        ->orWhere(function (Builder $completed): void {
                            $completed->where('status', 'completed')->where(function (Builder $submission): void {
                                $submission->whereDoesntHave('submission')
                                    ->orWhereHas('submission', fn (Builder $query) => $query->whereNull('anonymized_at'));
                            });
                        });
                });
            });
    }

    public function anonymizeEligibleAllocations(int $retentionDays, int $chunkSize = 100): int
    {
        if (!Schema::hasTable('event_registration_allocations') || !Schema::hasTable('event_registration_allocation_claims')) {
            return 0;
        }
        $anonymized = 0;
        $this->eligibleAllocationQuery($retentionDays)->orderBy('id')
            ->chunkById(max(1, min(500, $chunkSize)), function ($allocations) use (&$anonymized, $retentionDays): void {
                foreach ($allocations as $allocation) {
                    $anonymized += $this->anonymizeAllocation((int) $allocation->id, $retentionDays) ? 1 : 0;
                }
            });
        return $anonymized;
    }

    private function anonymizeOne(int $submissionId, int $retentionDays): bool
    {
        return DB::transaction(function () use ($submissionId, $retentionDays): bool {
            $eligible = $this->eligibleQuery($retentionDays)
                ->whereKey($submissionId)
                ->lockForUpdate()
                ->first();
            if (!$eligible) {
                return false;
            }

            $answerCount = $eligible->answers()->count();
            $attendeeCount = $eligible->attendees()->count();
            $fileCount = 0;
            if (Schema::hasTable('event_registration_files')) {
                foreach ($eligible->files()->lockForUpdate()->get() as $file) {
                    $storage = Storage::disk($file->disk);
                    if ($storage->exists($file->path) && (!$storage->delete($file->path) || $storage->exists($file->path))) {
                        throw new RuntimeException('A private registration upload could not be deleted.');
                    }
                    $file->delete();
                    $fileCount++;
                }
            }
            $eligible->answers()->delete();
            foreach ($eligible->attendees()->lockForUpdate()->get() as $attendee) {
                $attendee->forceFill([
                    'name' => 'Anonymized attendee',
                    'email' => null,
                    'email_hash' => null,
                ])->save();
            }
            $eligible->forceFill([
                'primary_name' => 'Anonymized registrant',
                'primary_email' => 'anonymized+'.strtolower($eligible->public_id).'@invalid.tica.local',
                'primary_email_hash' => hash_hmac('sha256', 'anonymized:'.$eligible->public_id, (string) config('app.key')),
                'source_ip_hash' => null,
                'review_note' => null,
                'anonymized_at' => now(),
                'anonymization_reason' => 'retention_policy',
            ])->save();

            AuditLogger::record('event.registration_anonymized', $eligible, ['anonymized' => false], [
                'anonymized' => true,
                'attendee_count' => $attendeeCount,
                'answer_count_deleted' => $answerCount,
                'file_count_deleted' => $fileCount,
            ], [
                'event_id' => $eligible->event_id,
                'retention_days' => max(30, $retentionDays),
            ]);

            return true;
        }, 3);
    }

    private function anonymizeAllocation(int $allocationId, int $retentionDays): bool
    {
        return DB::transaction(function () use ($allocationId, $retentionDays): bool {
            $allocation = $this->eligibleAllocationQuery($retentionDays)->whereKey($allocationId)->lockForUpdate()->first();
            if (!$allocation) return false;

            foreach ($allocation->claims()->lockForUpdate()->get() as $claim) {
                $claim->forceFill([
                    'name' => 'Anonymized attendee',
                    'email' => 'anonymized+claim-'.$claim->public_id.'@invalid.tica.local',
                    'email_hash' => hash_hmac('sha256', 'anonymized:claim:'.$claim->public_id, (string) config('app.key')),
                    'token_hash' => hash_hmac('sha256', 'retired:claim:'.$claim->public_id, (string) config('app.key')),
                    'dispatch_token' => null, 'source_attested_ip_hash' => null,
                    'last_error' => null, 'dispatch_last_error' => null,
                    'anonymized_at' => now(), 'anonymization_reason' => 'retention_policy',
                ])->save();
            }
            $allocation->forceFill([
                'sponsor_name' => 'Anonymized sponsor',
                'sponsor_email' => 'anonymized+allocation-'.$allocation->public_id.'@invalid.tica.local',
                'sponsor_email_hash' => hash_hmac('sha256', 'anonymized:allocation:'.$allocation->public_id, (string) config('app.key')),
                'access_token_hash' => hash_hmac('sha256', 'retired:allocation:'.$allocation->public_id, (string) config('app.key')),
                'dispatch_token' => null, 'attested_ip_hash' => null,
                'last_error' => null, 'dispatch_last_error' => null,
                'anonymized_at' => now(), 'anonymization_reason' => 'retention_policy',
            ])->save();
            AuditLogger::record('event.registration_allocation_anonymized', $allocation, ['anonymized' => false], [
                'anonymized' => true, 'claim_count' => $allocation->claims()->count(),
            ], ['event_id' => $allocation->event_id, 'retention_days' => max(30, $retentionDays)]);
            return true;
        }, 3);
    }
}
