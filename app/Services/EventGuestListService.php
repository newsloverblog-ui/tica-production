<?php

namespace App\Services;

use App\Jobs\SendEventGuestInvitation;
use App\Models\Event;
use App\Models\EventGuestInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class EventGuestListService
{
    public function invite(Event $event, int $organizerId, array $data, ?string $ip = null): EventGuestInvitation
    {
        $this->assertOrganizerOwns($event, $organizerId);
        if ((int) $event->status !== 1 || ($event->lifecycle_status ?: 'scheduled') !== 'scheduled' || $event->cancelled_at) {
            throw ValidationException::withMessages(['event' => 'Guest invitations are available only for an active scheduled event.']);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $partySize = (int) ($data['party_size'] ?? 1);
        if ($name === '' || mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['name' => 'Enter a guest name of 150 characters or fewer.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            throw ValidationException::withMessages(['email' => 'Enter a valid guest email address.']);
        }
        if ($partySize < 1 || $partySize > 20) {
            throw ValidationException::withMessages(['party_size' => 'Party size must be between 1 and 20.']);
        }
        if (empty($data['source_attested'])) {
            throw ValidationException::withMessages([
                'source_attested' => 'Confirm that you are permitted to contact this guest about this event.',
            ]);
        }

        $sourceImportId = isset($data['source_import_id']) ? (int) $data['source_import_id'] : null;
        $sourceImportRow = isset($data['source_import_row']) ? (int) $data['source_import_row'] : null;
        if ($sourceImportId && $sourceImportRow && Schema::hasColumns('event_guest_invitations', ['source_import_id', 'source_import_row'])) {
            $imported = EventGuestInvitation::query()
                ->where('source_import_id', $sourceImportId)
                ->where('source_import_row', $sourceImportRow)
                ->first();
            if ($imported) {
                return $imported;
            }
        }

        $token = Str::random(64);
        $emailHash = hash_hmac('sha256', $email, (string) config('app.key'));
        $ipHash = $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;

        $invitation = DB::transaction(function () use ($event, $organizerId, $name, $email, $emailHash, $partySize, $token, $ipHash, $sourceImportId, $sourceImportRow): EventGuestInvitation {
            $invitation = EventGuestInvitation::query()
                ->where('event_id', $event->id)
                ->where('email_hash', $emailHash)
                ->lockForUpdate()
                ->first();

            if ($invitation?->checked_in_at) {
                throw ValidationException::withMessages(['email' => 'This guest has already checked in.']);
            }

            if ($invitation?->status === 'accepted') {
                throw ValidationException::withMessages(['email' => 'This guest has already accepted the invitation.']);
            }

            $before = $invitation?->only(['status', 'delivery_status', 'party_size', 'expires_at']) ?: [];
            $invitation ??= new EventGuestInvitation([
                'public_id' => (string) Str::ulid(),
                'event_id' => $event->id,
                'organizer_id' => $organizerId,
                'email_hash' => $emailHash,
            ]);
            $attributes = [
                'name' => $name,
                'email' => $email,
                'party_size' => $partySize,
                'status' => 'pending',
                'delivery_status' => 'pending',
                'token_hash' => hash('sha256', $token),
                'dispatch_token' => $token,
                'dispatch_attempts' => 0,
                'dispatch_last_attempt_at' => null,
                'dispatch_last_error' => null,
                'source_attestation_version' => (string) config('tickets.guest_invitation_attestation_version'),
                'source_attested_at' => now(),
                'source_attested_ip_hash' => $ipHash,
                'invited_at' => null,
                'expires_at' => now()->addDays((int) config('tickets.guest_invitation_days')),
                'responded_at' => null,
                'rsvp_consent_at' => null,
                'marketing_consent_at' => null,
                'cancelled_at' => null,
                'delivery_attempts' => 0,
                'processing_at' => null,
                'alerted_at' => null,
                'last_error' => null,
            ];
            if ($sourceImportId && $sourceImportRow && Schema::hasColumns('event_guest_invitations', ['source_import_id', 'source_import_row'])) {
                $attributes['source_import_id'] = $sourceImportId;
                $attributes['source_import_row'] = $sourceImportRow;
            }
            $invitation->forceFill($attributes)->save();

            AuditLogger::record('event_guest_invitation.created', $invitation, $before, [
                'status' => $invitation->status,
                'delivery_status' => $invitation->delivery_status,
                'party_size' => $invitation->party_size,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ], ['event_id' => $event->id]);

            return $invitation;
        });

        $this->dispatchInvitation($invitation->id);

        return $invitation;
    }

    public function respond(EventGuestInvitation $invitation, string $token, string $response, bool $rsvpConsent, bool $marketingConsent): EventGuestInvitation
    {
        if (!hash_equals((string) $invitation->token_hash, hash('sha256', $token))) {
            throw new AuthorizationException('This RSVP link is invalid.');
        }
        if (!in_array($response, ['accepted', 'declined'], true)) {
            throw ValidationException::withMessages(['response' => 'Choose whether you will attend.']);
        }
        if ($response === 'accepted' && !$rsvpConsent) {
            throw ValidationException::withMessages(['rsvp_consent' => 'Confirm that Tica may process this RSVP and send essential event updates.']);
        }

        return DB::transaction(function () use ($invitation, $token, $response, $rsvpConsent, $marketingConsent): EventGuestInvitation {
            $locked = EventGuestInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (!hash_equals((string) $locked->token_hash, hash('sha256', $token))) {
                throw new AuthorizationException('This RSVP link is invalid.');
            }
            if ($locked->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'response' => 'This RSVP invitation has expired. Ask the organizer to send a new invitation.',
                ]);
            }
            if (in_array($locked->status, ['cancelled', 'expired'], true)) {
                throw ValidationException::withMessages(['response' => 'This RSVP invitation is no longer available.']);
            }
            if (in_array($locked->status, ['accepted', 'declined'], true)) {
                if ($locked->status !== $response) {
                    throw ValidationException::withMessages([
                        'response' => 'This RSVP has already been submitted. Contact the organizer to change it.',
                    ]);
                }
                return $locked;
            }

            $locked->forceFill([
                'status' => $response,
                'dispatch_token' => null,
                'responded_at' => now(),
                'rsvp_consent_at' => $rsvpConsent ? now() : null,
                'marketing_consent_at' => $response === 'accepted' && $marketingConsent ? now() : null,
            ])->save();
            AuditLogger::record('event_guest_invitation.responded', $locked, ['status' => 'pending'], [
                'status' => $response,
                'party_size' => $locked->party_size,
                'rsvp_consent' => $rsvpConsent,
                'marketing_consent' => $response === 'accepted' && $marketingConsent,
            ], ['event_id' => $locked->event_id]);

            return $locked;
        });
    }

    public function cancel(EventGuestInvitation $invitation, int $organizerId): EventGuestInvitation
    {
        $this->assertInvitationOwner($invitation, $organizerId);
        return DB::transaction(function () use ($invitation): EventGuestInvitation {
            $locked = EventGuestInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($locked->checked_in_at) {
                throw ValidationException::withMessages(['guest' => 'A checked-in guest cannot be cancelled.']);
            }
            if ($locked->status === 'cancelled') return $locked;
            $before = $locked->status;
            $locked->forceFill([
                'status' => 'cancelled',
                'delivery_status' => $locked->delivery_status === 'sent' ? 'sent' : 'suppressed',
                'dispatch_token' => null,
                'processing_at' => null,
                'cancelled_at' => now(),
            ])->save();
            AuditLogger::record('event_guest_invitation.cancelled', $locked, ['status' => $before], ['status' => 'cancelled']);
            return $locked;
        });
    }

    public function checkIn(EventGuestInvitation $invitation, int $organizerId): EventGuestInvitation
    {
        $this->assertInvitationOwner($invitation, $organizerId);
        return DB::transaction(function () use ($invitation, $organizerId): EventGuestInvitation {
            $locked = EventGuestInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'accepted') {
                throw ValidationException::withMessages(['guest' => 'Only an accepted RSVP can be checked in.']);
            }
            if ($locked->checked_in_at) return $locked;
            $locked->forceFill(['checked_in_at' => now(), 'checked_in_by_organizer_id' => $organizerId])->save();
            AuditLogger::record('event_guest_invitation.checked_in', $locked, [], [
                'checked_in_at' => $locked->checked_in_at->toIso8601String(),
                'party_size' => $locked->party_size,
            ]);
            return $locked;
        });
    }

    private function assertOrganizerOwns(Event $event, int $organizerId): void
    {
        if ((int) $event->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s guest list.');
        }
    }

    public function dispatchInvitation(int $invitationId): bool
    {
        $invitation = EventGuestInvitation::query()->find($invitationId);
        if (!$invitation || $invitation->status !== 'pending' || $invitation->delivery_status !== 'pending') {
            return true;
        }

        $invitation->forceFill([
            'dispatch_attempts' => (int) $invitation->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $token = (string) $invitation->dispatch_token;
            if ($token === '' || !hash_equals((string) $invitation->token_hash, hash('sha256', $token))) {
                throw new RuntimeException('The guest invitation dispatch token is unavailable or invalid.');
            }
            $this->pushInvitationJob($invitation->id, $token);
            return true;
        } catch (Throwable $exception) {
            $invitation->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushInvitationJob(int $invitationId, string $token): void
    {
        SendEventGuestInvitation::dispatch($invitationId, $token);
    }

    private function assertInvitationOwner(EventGuestInvitation $invitation, int $organizerId): void
    {
        if ((int) $invitation->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s guest invitation.');
        }
    }
}
