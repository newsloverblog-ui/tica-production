<?php

namespace Tests\Unit;

use App\Http\Middleware\HasPermission;
use App\Models\Admin;
use App\Models\RolePermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HasPermissionMiddlewareTest extends TestCase
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
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->text('permissions')->nullable(); $table->timestamps();
        });
        Schema::create('admins', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('role_id')->nullable(); $table->string('username')->nullable();
            $table->string('email')->nullable(); $table->string('password')->nullable(); $table->integer('status'); $table->timestamps();
        });
    }

    public function test_primary_administrator_is_explicitly_unrestricted(): void
    {
        $this->loginAdmin(null);
        $response = (new HasPermission())->handle(Request::create('/admin'), fn () => response('ok'), 'Anything');
        $this->assertSame('ok', $response->getContent());
    }

    public function test_assigned_administrator_only_receives_whitelisted_permission(): void
    {
        $role = RolePermission::create(['name' => 'Events', 'permissions' => json_encode(['Event Management'])]);
        $this->loginAdmin($role->id);
        $response = (new HasPermission())->handle(Request::create('/admin'), fn () => response('ok'), 'Event Management');
        $this->assertSame('ok', $response->getContent());

        $this->expectException(HttpException::class);
        (new HasPermission())->handle(Request::create('/admin'), fn () => response('bad'), 'Admin Management');
    }

    public function test_missing_assigned_role_fails_closed(): void
    {
        $this->loginAdmin(999);
        $this->expectException(HttpException::class);
        (new HasPermission())->handle(Request::create('/admin'), fn () => response('bad'), 'Admin Management');
    }

    public function test_assigned_administrator_can_satisfy_any_of_multiple_explicit_permissions(): void
    {
        $role = RolePermission::create(['name' => 'Content', 'permissions' => json_encode(['Blog Management'])]);
        $this->loginAdmin($role->id);

        $response = (new HasPermission())->handle(
            Request::create('/admin/summernote/upload-image'),
            fn () => response('ok'),
            'Home Page',
            'Blog Management',
            'Footer'
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_admin_model_centralizes_primary_and_assigned_permission_checks(): void
    {
        $primary = $this->loginAdmin(null);
        $this->assertTrue($primary->hasPermission('Transaction'));

        Auth::guard('admin')->logout();
        $role = RolePermission::create([
            'name' => 'Shop operator',
            'permissions' => json_encode(['Shop Management']),
        ]);
        $operator = $this->loginAdmin($role->id);

        $this->assertTrue($operator->hasPermission('Shop Management'));
        $this->assertFalse($operator->hasPermission('Transaction'));
        $this->assertTrue($operator->hasPermission('Transaction', 'Shop Management'));
    }

    private function loginAdmin(?int $roleId): Admin
    {
        $admin = Admin::create([
            'role_id' => $roleId,
            'username' => uniqid('admin-', true),
            'email' => uniqid('admin-', true) . '@example.com',
            'password' => 'x',
            'status' => 1,
        ]);
        Auth::guard('admin')->login($admin);

        return $admin;
    }
}
