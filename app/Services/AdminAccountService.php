<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAccountService
{
    public function setStatus(int $adminId, int $status, int $actingAdminId): bool
    {
        $this->validateStatus($status);

        return DB::transaction(function () use ($adminId, $status, $actingAdminId) {
            $admin = Admin::whereKey($adminId)->lockForUpdate()->firstOrFail();
            $this->guardProtectedAccount($admin, $actingAdminId, $status === 0);

            if ((int) $admin->status === $status) {
                return false;
            }

            $before = (int) $admin->status;
            $admin->status = $status;
            $admin->save();
            AuditLogger::record('admin.status_changed', $admin, ['status' => $before], ['status' => $status]);

            return true;
        }, 3);
    }

    public function archive(int $adminId, int $actingAdminId): bool
    {
        return DB::transaction(function () use ($adminId, $actingAdminId) {
            $admin = Admin::whereKey($adminId)->lockForUpdate()->firstOrFail();
            $this->guardProtectedAccount($admin, $actingAdminId, true);

            if ((int) $admin->status === 0) {
                return false;
            }

            $admin->status = 0;
            $admin->save();
            AuditLogger::record('admin.archived', $admin, ['status' => 1], ['status' => 0]);

            return true;
        }, 3);
    }

    private function guardProtectedAccount(Admin $admin, int $actingAdminId, bool $deactivating): void
    {
        if ($admin->role_id === null) {
            throw ValidationException::withMessages([
                'admin' => ['The primary administrator account cannot be changed here.'],
            ]);
        }

        if ($deactivating && (int) $admin->id === $actingAdminId) {
            throw ValidationException::withMessages([
                'admin' => ['You cannot deactivate or archive your own administrator account.'],
            ]);
        }
    }

    private function validateStatus(int $status): void
    {
        if (!in_array($status, [0, 1], true)) {
            throw ValidationException::withMessages(['status' => ['Status must be active or inactive.']]);
        }
    }
}
