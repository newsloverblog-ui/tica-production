<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    public const PERMISSIONS = [
        'Admin Management', 'Language Management', 'PWA Settings', 'Basic Settings',
        'Payment Gateways', 'Push Notification', 'Subscribers', 'Announcement Popups',
        'Advertise', 'Contact Page', 'FAQ Management', 'Blog Management', 'Custom Pages',
        'Footer', 'Home Page', 'Shop Management', 'Support Ticket', 'Customer Management',
        'Organizer Mangement', 'Transaction', 'Event Management', 'Event Bookings',
        'Withdraw Method', 'Menu Builder', 'Lifetime Earning', 'Total Profit',
    ];

    public function create(string $name): RolePermission
    {
        return DB::transaction(function () use ($name) {
            $name = $this->validatedName($name);
            $role = RolePermission::create(['name' => $name, 'permissions' => json_encode([])]);

            AuditLogger::record('admin_role.created', $role, [], [
                'name' => $role->name,
                'permissions' => [],
            ]);

            return $role;
        });
    }

    public function rename(int $roleId, string $name): bool
    {
        return DB::transaction(function () use ($roleId, $name) {
            $role = RolePermission::query()->lockForUpdate()->findOrFail($roleId);
            $name = $this->validatedName($name, $role->id);

            if ($role->name === $name) {
                return false;
            }

            $before = ['name' => $role->name];
            $role->update(['name' => $name]);
            AuditLogger::record('admin_role.renamed', $role, $before, ['name' => $name]);

            return true;
        });
    }

    public function updatePermissions(int $roleId, array $permissions): bool
    {
        return DB::transaction(function () use ($roleId, $permissions) {
            $role = RolePermission::query()->lockForUpdate()->findOrFail($roleId);
            $permissions = array_values(array_unique($permissions));
            $unknown = array_values(array_diff($permissions, self::PERMISSIONS));

            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'permissions' => 'One or more selected permissions are not supported.',
                ]);
            }

            $permissions = array_values(array_intersect(self::PERMISSIONS, $permissions));
            $before = $this->decodedPermissions($role);

            if ($before === $permissions) {
                return false;
            }

            $role->update(['permissions' => json_encode($permissions)]);
            AuditLogger::record(
                'admin_role.permissions_changed',
                $role,
                ['permissions' => $before],
                ['permissions' => $permissions]
            );

            return true;
        });
    }

    public function updateAccess(int $roleId, array $permissions, array $exportFields): bool
    {
        return DB::transaction(function () use ($roleId, $permissions, $exportFields): bool {
            $permissionsChanged = $this->updatePermissions($roleId, $permissions);
            $fieldsChanged = $this->updateExportFields($roleId, $exportFields);

            return $permissionsChanged || $fieldsChanged;
        });
    }

    public function updateExportFields(int $roleId, array $fields): bool
    {
        return DB::transaction(function () use ($roleId, $fields): bool {
            $role = RolePermission::query()->lockForUpdate()->findOrFail($roleId);
            try {
                $fields = app(BookingExportFieldPolicyService::class)->validate($fields);
            } catch (\DomainException $exception) {
                throw ValidationException::withMessages(['export_fields' => $exception->getMessage()]);
            }
            $before = is_array($role->export_fields) ? $role->export_fields : [];
            if ($before === $fields) {
                return false;
            }
            $role->forceFill(['export_fields' => $fields])->save();
            AuditLogger::record('admin_role.export_fields_changed', $role,
                ['export_fields' => $before], ['export_fields' => $fields]);

            return true;
        });
    }

    public function delete(int $roleId): void
    {
        DB::transaction(function () use ($roleId) {
            $role = RolePermission::query()->lockForUpdate()->findOrFail($roleId);

            if ($role->adminInfo()->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'Reassign or archive every administrator using this role before deleting it.',
                ]);
            }

            AuditLogger::record('admin_role.deleted', $role, [
                'name' => $role->name,
                'permissions' => $this->decodedPermissions($role),
            ]);
            $role->delete();
        });
    }

    private function validatedName(string $name, ?int $exceptId = null): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages([
                'name' => 'The role name is required and may not exceed 100 characters.',
            ]);
        }

        $duplicate = RolePermission::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }

        return $name;
    }

    private function decodedPermissions(RolePermission $role): array
    {
        $permissions = json_decode((string) $role->permissions, true);
        return is_array($permissions) ? array_values($permissions) : [];
    }
}
