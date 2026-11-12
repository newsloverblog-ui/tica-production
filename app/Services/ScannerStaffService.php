<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\ScannerStaff;
use App\Support\LifecycleMailCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ScannerStaffService
{
    public const ROLES = ['check_in', 'supervisor'];
    public const MAX_ACTIVE_STAFF = 100;

    public function create(int $organizerId, array $data): ScannerStaff
    {
        if (!in_array($data['role'] ?? null, self::ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Select a valid gate role.']);
        }
        return DB::transaction(function () use ($organizerId, $data) {
            Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            if (ScannerStaff::where('organizer_id', $organizerId)->where('status', 'active')->count() >= self::MAX_ACTIVE_STAFF) {
                throw ValidationException::withMessages(['email' => 'Deactivate an unused gate account before adding another.']);
            }

            $staff = ScannerStaff::create([
                'organizer_id' => $organizerId,
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => 'active',
            ]);
            AuditLogger::record('scanner_staff.created', $staff, [], $this->snapshot($staff));

            $temporaryPassword = (string) $data['password'];
            DB::afterCommit(function () use ($staff, $temporaryPassword): void {
                $staff->loadMissing('organizer');
                app(LifecycleMailService::class)->queueSafely(
                    LifecycleMailCatalog::ORGANIZER_STAFF_INVITE,
                    $staff->email,
                    [
                        'organizer_name' => $staff->organizer->username ?? 'A Tica organiser',
                        'role' => str_replace('_', ' ', ucfirst($staff->role)),
                        'temporary_password' => $temporaryPassword,
                        'login_url' => route('scanner.login'),
                    ],
                    $staff
                );
            });
            return $staff;
        });
    }

    public function update(int $organizerId, int $staffId, array $data): ScannerStaff
    {
        if (!in_array($data['role'] ?? null, self::ROLES, true) || !in_array($data['status'] ?? null, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['role' => 'Select a valid gate role and status.']);
        }
        return DB::transaction(function () use ($organizerId, $staffId, $data) {
            $staff = ScannerStaff::whereKey($staffId)->where('organizer_id', $organizerId)->lockForUpdate()->firstOrFail();
            if ($staff->status !== 'active' && $data['status'] === 'active'
                && ScannerStaff::where('organizer_id', $organizerId)->where('status', 'active')->count() >= self::MAX_ACTIVE_STAFF) {
                throw ValidationException::withMessages(['status' => 'Deactivate an unused gate account before reactivating this one.']);
            }
            $before = $this->snapshot($staff);
            $changes = [
                'name' => trim($data['name']),
                'role' => $data['role'],
                'status' => $data['status'],
                'revoked_at' => $data['status'] === 'inactive' ? ($staff->revoked_at ?: now()) : null,
            ];
            if (!empty($data['password'])) {
                $changes['password'] = Hash::make($data['password']);
                $changes['remember_token'] = null;
            }
            if (!empty($data['password']) || $data['status'] !== $staff->status) {
                $changes['auth_version'] = ((int) $staff->auth_version) + 1;
            }
            $staff->forceFill($changes)->save();
            AuditLogger::record('scanner_staff.updated', $staff, $before, $this->snapshot($staff), [
                'password_rotated' => !empty($data['password']),
            ]);
            return $staff;
        });
    }

    public function snapshot(ScannerStaff $staff): array
    {
        return $staff->only(['name', 'email', 'role', 'status', 'last_login_at', 'last_seen_at', 'revoked_at']);
    }

    public function invalidateOrganizerSessions(int $organizerId): int
    {
        if (!Schema::hasTable('scanner_staff')) {
            return 0;
        }

        return ScannerStaff::where('organizer_id', $organizerId)->increment('auth_version');
    }
}
