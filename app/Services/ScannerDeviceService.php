<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\ScannerDevice;
use App\Models\ScannerOfflineManifest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScannerDeviceService
{
    public const MAX_ACTIVE_DEVICES = 25;

    /**
     * The plaintext credential is returned once and is never persisted.
     *
     * @return array{0: ScannerDevice, 1: string}
     */
    public function enroll(int $organizerId, string $name): array
    {
        return DB::transaction(function () use ($organizerId, $name) {
            Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();

            if (ScannerDevice::where('organizer_id', $organizerId)->where('status', 'active')->count() >= self::MAX_ACTIVE_DEVICES) {
                throw ValidationException::withMessages([
                    'name' => 'Revoke an unused scanner before enrolling another device.',
                ]);
            }

            $plainSecret = $this->newSecret();
            $device = ScannerDevice::create([
                'public_id' => (string) Str::uuid(),
                'organizer_id' => $organizerId,
                'name' => trim($name),
                'secret_hash' => hash('sha256', $plainSecret),
                'status' => 'active',
            ]);

            AuditLogger::record('scanner_device.enrolled', $device, [], [
                'public_id' => $device->public_id,
                'name' => $device->name,
                'status' => $device->status,
            ]);

            return [$device, $plainSecret];
        });
    }

    public function authenticate(int $organizerId, string $publicId, string $plainSecret, Request $request): ?ScannerDevice
    {
        $device = ScannerDevice::where('public_id', $publicId)
            ->where('organizer_id', $organizerId)
            ->where('status', 'active')
            ->first();

        if (!$device || !hash_equals((string) $device->secret_hash, hash('sha256', $plainSecret))) {
            return null;
        }

        if (!$device->last_seen_at || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill([
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
                'last_user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ])->save();
        }

        return $device;
    }

    public function revoke(int $organizerId, int $deviceId): ScannerDevice
    {
        return DB::transaction(function () use ($organizerId, $deviceId) {
            $device = ScannerDevice::whereKey($deviceId)
                ->where('organizer_id', $organizerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($device->status === 'revoked') {
                return $device;
            }

            $before = $device->only(['status', 'revoked_at']);
            $device->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by_organizer_id' => $organizerId,
            ]);

            AuditLogger::record('scanner_device.revoked', $device, $before, $device->only(['status', 'revoked_at']));

            return $device;
        });
    }

    private function newSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function assign(int $organizerId, int $deviceId, int $eventId, ?int $ticketId): ScannerDevice
    {
        return DB::transaction(function () use ($organizerId, $deviceId, $eventId, $ticketId): ScannerDevice {
            $device = ScannerDevice::whereKey($deviceId)->where('organizer_id', $organizerId)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            $event = Event::whereKey($eventId)->where('organizer_id', $organizerId)->where('status', 1)->firstOrFail();
            if ($ticketId && !$event->tickets()->whereKey($ticketId)->exists()) {
                throw ValidationException::withMessages(['ticket_id' => 'Select a ticket type for this event.']);
            }
            $fields = ['event_id' => $eventId, 'admission_ticket_id' => $ticketId];
            $before = $device->only(array_keys($fields));
            $device->forceFill($fields);
            if ($device->isDirty()) {
                $device->save();
                ScannerOfflineManifest::where('scanner_device_id', $device->id)->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'status' => 'revoked']);
                AuditLogger::record('scanner_device.assignment_changed', $device, $before, $fields);
            }
            return $device;
        }, 3);
    }
}
