<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Services\RolePermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RolePermissionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_role_changes_are_validated_idempotent_and_audited(): void
    {
        $service = new RolePermissionService();
        $role = $service->create('Finance');

        $this->assertTrue($service->rename($role->id, 'Finance Operations'));
        $this->assertFalse($service->rename($role->id, 'Finance Operations'));
        $this->assertTrue($service->updatePermissions($role->id, ['Transaction', 'Total Profit']));
        $this->assertFalse($service->updatePermissions($role->id, ['Transaction', 'Total Profit']));

        $this->assertSame(['Transaction', 'Total Profit'], json_decode($role->fresh()->permissions, true));
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_duplicate_names_and_unknown_permissions_are_rejected(): void
    {
        $service = new RolePermissionService();
        $role = $service->create('Support');

        try {
            $service->create(' support ');
            $this->fail('Duplicate role name should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $service->updatePermissions($role->id, ['Server Root Access']);
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        $service = new RolePermissionService();
        $role = $service->create('Operations');
        Admin::create([
            'role_id' => $role->id,
            'username' => 'operator',
            'email' => 'operator@example.com',
            'password' => 'x',
            'status' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $service->delete($role->id);
    }

    public function test_unassigned_role_deletion_is_audited(): void
    {
        $service = new RolePermissionService();
        $role = $service->create('Temporary');
        $service->delete($role->id);

        $this->assertDatabaseMissing('role_permissions', ['id' => $role->id]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'admin_role.deleted')->count());
    }

    public function test_export_fields_are_allowlisted_nonempty_and_audited(): void
    {
        $service = new RolePermissionService();
        $role = $service->create('Reporting');

        $this->assertTrue($service->updateExportFields($role->id, ['booking_id', 'event', 'email']));
        $this->assertFalse($service->updateExportFields($role->id, ['booking_id', 'event', 'email']));
        $this->assertSame(['booking_id', 'event', 'email'], $role->fresh()->export_fields);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_role.export_fields_changed']);

        foreach ([[], ['booking_id', 'database_password']] as $invalid) {
            try {
                $service->updateExportFields($role->id, $invalid);
                $this->fail('Invalid field policies must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('export_fields', $exception->errors());
            }
        }
    }

    public function test_combined_access_update_rolls_back_permissions_when_field_policy_is_invalid(): void
    {
        $service = new RolePermissionService();
        $role = $service->create('Restricted reporting');

        try {
            $service->updateAccess($role->id, ['Event Bookings'], []);
            $this->fail('Invalid combined access should fail.');
        } catch (ValidationException) {
            $this->assertSame([], json_decode($role->fresh()->permissions, true));
            $this->assertNull($role->fresh()->export_fields);
        }
    }

    private function createSchema(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->text('permissions')->nullable();
            $table->text('export_fields')->nullable(); $table->timestamps();
        });
        Schema::create('admins', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('role_id')->nullable(); $table->string('username')->nullable();
            $table->string('email')->nullable(); $table->string('password')->nullable(); $table->integer('status'); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
