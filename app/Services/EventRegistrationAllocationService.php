<?php

namespace App\Services;

use App\Jobs\SendEventRegistrationAllocationInvitation;
use App\Jobs\SendEventRegistrationSponsorAccess;
use App\Models\Event;
use App\Models\EventRegistrationAllocation;
use App\Models\EventRegistrationAllocationClaim;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class EventRegistrationAllocationService
{
    public function create(EventRegistrationForm $form, int $organizerId, array $data, ?string $ip = null): EventRegistrationAllocation
    {
        $form->loadMissing('event');
        if ((int) $form->organizer_id !== $organizerId || (int) $form->event?->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s registration allocations.');
        }
        if ($form->status !== 'published' || !$this->eventIsOpen($form->event)) {
            throw ValidationException::withMessages(['allocation' => 'Publish an active corporate registration form before creating an allocation.']);
        }
        $name = trim((string) ($data['sponsor_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['sponsor_email'] ?? '')));
        $capacity = (int) ($data['capacity'] ?? 0);
        if ($name === '' || mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['sponsor_name' => 'Enter a sponsor name of 150 characters or fewer.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            throw ValidationException::withMessages(['sponsor_email' => 'Enter a valid sponsor contact email.']);
        }
        $max = (int) config('registration.allocation_max_capacity', 1000);
        if ($capacity < 1 || $capacity > $max) {
            throw ValidationException::withMessages(['capacity' => "Allocation capacity must be between 1 and {$max}."]);
        }
        if (empty($data['source_attested'])) {
            throw ValidationException::withMessages(['source_attested' => 'Confirm that the sponsor is authorized to receive and manage this allocation.']);
        }
        $expiresAt = !empty($data['expires_at']) ? now()->parse($data['expires_at'])->endOfDay() : now()->addDays((int) config('registration.allocation_default_days', 30));
        if ($expiresAt->isPast() || $expiresAt->greaterThan(now()->addYear())) {
            throw ValidationException::withMessages(['expires_at' => 'Choose an expiry within the next year.']);
        }

        $token = Str::random(64);
        $emailHash = $this->contactHash($email);
        $allocation = DB::transaction(function () use ($form, $organizerId, $name, $email, $emailHash, $capacity, $expiresAt, $token, $ip): EventRegistrationAllocation {
            $allocation = EventRegistrationAllocation::query()
                ->where('event_id', $form->event_id)->where('sponsor_email_hash', $emailHash)
                ->lockForUpdate()->first();
            $reserved = $allocation ? $allocation->claims()->whereIn('status', ['pending', 'completed'])->count() : 0;
            if ($capacity < $reserved) {
                throw ValidationException::withMessages(['capacity' => "Capacity cannot be lower than the {$reserved} allocated attendee places already in use."]);
            }
            $before = $allocation?->only(['capacity', 'status', 'expires_at']) ?: [];
            $allocation ??= new EventRegistrationAllocation([
                'public_id' => (string) Str::ulid(), 'event_id' => $form->event_id,
                'form_id' => $form->id, 'organizer_id' => $organizerId, 'sponsor_email_hash' => $emailHash,
            ]);
            $allocation->forceFill([
                'form_id' => $form->id, 'sponsor_name' => $name, 'sponsor_email' => $email,
                'capacity' => $capacity, 'status' => 'active', 'access_token_hash' => hash('sha256', $token),
                'dispatch_token' => $token, 'dispatch_attempts' => 0,
                'dispatch_last_attempt_at' => null, 'dispatch_last_error' => null,
                'attestation_version' => (string) config('registration.allocation_attestation_version'),
                'attested_at' => now(), 'attested_ip_hash' => $ip ? $this->contactHash($ip) : null,
                'expires_at' => $expiresAt, 'cancelled_at' => null, 'delivery_status' => 'pending',
                'delivery_attempts' => 0, 'processing_at' => null, 'alerted_at' => null, 'last_error' => null,
            ])->save();
            AuditLogger::record('event.registration_allocation.saved', $allocation, $before, [
                'capacity' => $allocation->capacity, 'status' => $allocation->status,
                'expires_at' => $allocation->expires_at->toIso8601String(),
            ], ['event_id' => $allocation->event_id, 'form_id' => $allocation->form_id]);
            return $allocation;
        }, 3);

        $this->dispatchSponsorAccess($allocation->id);
        return $allocation;
    }

    public function assertSponsorAccess(EventRegistrationAllocation $allocation, string $token): EventRegistrationAllocation
    {
        if (!hash_equals((string) $allocation->access_token_hash, hash('sha256', $token))) {
            throw new AuthorizationException('This allocation link is invalid.');
        }
        $allocation->loadMissing('event', 'form');
        if ($allocation->status !== 'active' || $allocation->expires_at->isPast() || !$this->eventIsOpen($allocation->event)
            || $allocation->form?->status !== 'published') {
            throw ValidationException::withMessages(['allocation' => 'This attendee allocation is no longer available.']);
        }
        return $allocation;
    }

    public function invite(EventRegistrationAllocation $allocation, string $accessToken, array $data, ?string $ip = null): EventRegistrationAllocationClaim
    {
        $this->assertSponsorAccess($allocation, $accessToken);
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($name === '' || mb_strlen($name) > 150) throw ValidationException::withMessages(['name' => 'Enter an attendee name of 150 characters or fewer.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) throw ValidationException::withMessages(['email' => 'Enter a valid attendee email address.']);
        if (empty($data['source_attested'])) throw ValidationException::withMessages(['source_attested' => 'Confirm that you are authorized to invite this attendee.']);

        $token = Str::random(64);
        $emailHash = $this->contactHash($email);
        $claim = DB::transaction(function () use ($allocation, $accessToken, $name, $email, $emailHash, $token, $ip): EventRegistrationAllocationClaim {
            $locked = EventRegistrationAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $this->assertSponsorAccess($locked, $accessToken);
            $claim = EventRegistrationAllocationClaim::query()->where('allocation_id', $locked->id)
                ->where('email_hash', $emailHash)->lockForUpdate()->first();
            if ($claim?->status === 'completed') throw ValidationException::withMessages(['email' => 'This attendee has already completed registration.']);
            $used = EventRegistrationAllocationClaim::where('allocation_id', $locked->id)
                ->whereIn('status', ['pending', 'completed'])->when($claim, fn ($query) => $query->where('id', '!=', $claim->id))->count();
            if ($used >= $locked->capacity) throw ValidationException::withMessages(['allocation' => 'Every place in this allocation is already assigned.']);
            $claim ??= new EventRegistrationAllocationClaim([
                'public_id' => (string) Str::ulid(), 'allocation_id' => $locked->id,
                'event_id' => $locked->event_id, 'form_id' => $locked->form_id, 'email_hash' => $emailHash,
            ]);
            $claim->forceFill([
                'name' => $name, 'email' => $email, 'token_hash' => hash('sha256', $token),
                'dispatch_token' => $token, 'dispatch_attempts' => 0,
                'dispatch_last_attempt_at' => null, 'dispatch_last_error' => null,
                'source_attestation_version' => (string) config('registration.allocation_attestation_version'),
                'source_attested_at' => now(), 'source_attested_ip_hash' => $ip ? $this->contactHash($ip) : null,
                'status' => 'pending', 'submission_id' => null, 'expires_at' => $locked->expires_at,
                'invited_at' => null, 'completed_at' => null, 'cancelled_at' => null,
                'delivery_status' => 'pending', 'delivery_attempts' => 0, 'processing_at' => null,
                'alerted_at' => null, 'last_error' => null,
            ])->save();
            AuditLogger::record('event.registration_allocation.attendee_invited', $claim, [], ['status' => 'pending'], [
                'event_id' => $locked->event_id, 'allocation_id' => $locked->id,
            ]);
            return $claim;
        }, 3);

        $this->dispatchClaimInvitation($claim->id);
        return $claim;
    }

    public function assertClaimAccess(EventRegistrationAllocationClaim $claim, string $token): EventRegistrationAllocationClaim
    {
        if (!hash_equals((string) $claim->token_hash, hash('sha256', $token))) throw new AuthorizationException('This registration link is invalid.');
        $claim->loadMissing('allocation.event', 'form');
        if ($claim->status !== 'pending' || $claim->expires_at->isPast() || $claim->allocation?->status !== 'active'
            || !$this->eventIsOpen($claim->allocation?->event) || $claim->form?->status !== 'published') {
            throw ValidationException::withMessages(['allocation' => 'This allocated registration place is no longer available.']);
        }
        return $claim;
    }

    public function submitClaim(EventRegistrationAllocationClaim $claim, string $token, array $data, ?int $customerId, ?string $ip, EventRegistrationService $registrations): EventRegistrationSubmission
    {
        $this->assertClaimAccess($claim, $token);
        $data['registrant']['name'] = $claim->name;
        $data['registrant']['email'] = $claim->email;
        $attendee = is_array($data['attendees'][0] ?? null) ? $data['attendees'][0] : [];
        $attendee['name'] = $claim->name;
        $attendee['email'] = $claim->email;
        $data['attendees'] = [$attendee];

        return DB::transaction(function () use ($claim, $token, $data, $customerId, $ip, $registrations): EventRegistrationSubmission {
            $locked = EventRegistrationAllocationClaim::with('event')->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $this->assertClaimAccess($locked, $token);
            $submission = $registrations->submit($locked->event, $data, $customerId, $ip, null, $locked->id);
            $locked->forceFill([
                'status' => 'completed', 'submission_id' => $submission->id,
                'completed_at' => now(), 'dispatch_token' => null,
            ])->save();
            AuditLogger::record('event.registration_allocation.completed', $locked, ['status' => 'pending'], [
                'status' => 'completed', 'submission_id' => $submission->id,
            ], ['event_id' => $locked->event_id, 'allocation_id' => $locked->allocation_id]);
            return $submission;
        }, 3);
    }

    public function cancel(EventRegistrationAllocation $allocation, int $organizerId): EventRegistrationAllocation
    {
        if ((int) $allocation->organizer_id !== $organizerId) throw new AuthorizationException('You cannot cancel another organizer’s allocation.');
        return DB::transaction(function () use ($allocation): EventRegistrationAllocation {
            $locked = EventRegistrationAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') return $locked;
            $locked->forceFill([
                'status' => 'cancelled', 'cancelled_at' => now(),
                'delivery_status' => 'suppressed', 'dispatch_token' => null,
            ])->save();
            EventRegistrationAllocationClaim::where('allocation_id', $locked->id)->where('status', 'pending')
                ->update([
                    'status' => 'cancelled', 'cancelled_at' => now(),
                    'delivery_status' => 'suppressed', 'dispatch_token' => null,
                ]);
            AuditLogger::record('event.registration_allocation.cancelled', $locked, ['status' => 'active'], ['status' => 'cancelled']);
            return $locked;
        }, 3);
    }

    public function dispatchSponsorAccess(int $allocationId): bool
    {
        $allocation = EventRegistrationAllocation::query()->find($allocationId);
        if (!$allocation || $allocation->status !== 'active' || $allocation->delivery_status !== 'pending') {
            return true;
        }
        $allocation->forceFill([
            'dispatch_attempts' => (int) $allocation->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();
        try {
            $token = (string) $allocation->dispatch_token;
            if ($token === '' || !hash_equals($allocation->access_token_hash, hash('sha256', $token))) {
                throw new RuntimeException('The sponsor allocation dispatch token is unavailable or invalid.');
            }
            $this->pushSponsorAccessJob($allocation->id, $token);
            return true;
        } catch (Throwable $exception) {
            $allocation->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    public function dispatchClaimInvitation(int $claimId): bool
    {
        $claim = EventRegistrationAllocationClaim::query()->find($claimId);
        if (!$claim || $claim->status !== 'pending' || $claim->delivery_status !== 'pending') {
            return true;
        }
        $claim->forceFill([
            'dispatch_attempts' => (int) $claim->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();
        try {
            $token = (string) $claim->dispatch_token;
            if ($token === '' || !hash_equals($claim->token_hash, hash('sha256', $token))) {
                throw new RuntimeException('The attendee allocation dispatch token is unavailable or invalid.');
            }
            $this->pushClaimInvitationJob($claim->id, $token);
            return true;
        } catch (Throwable $exception) {
            $claim->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushSponsorAccessJob(int $allocationId, string $token): void
    {
        SendEventRegistrationSponsorAccess::dispatch($allocationId, $token);
    }

    protected function pushClaimInvitationJob(int $claimId, string $token): void
    {
        SendEventRegistrationAllocationInvitation::dispatch($claimId, $token);
    }

    private function eventIsOpen(?Event $event): bool
    {
        return $event && ($event->registration_mode ?: 'ticketing') === 'corporate' && (int) $event->status === 1
            && ($event->lifecycle_status ?: 'scheduled') === 'scheduled' && !$event->cancelled_at;
    }

    private function contactHash(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), (string) config('app.key'));
    }
}
