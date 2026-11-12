<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Services\AdminAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminAccountServiceTest extends TestCase
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

    public function test_status_change_is_validated_idempotent_and_audited(): void
    {
        $actor = $this->admin(1, 1);
        $target = $this->admin(1, 1);
        $service = new AdminAccountService();

        $this->assertTrue($service->setStatus($target->id, 0, $actor->id));
        $this->assertFalse($service->setStatus($target->id, 0, $actor->id));
        $this->assertSame(0, (int) $target->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'admin.status_changed')->count());
    }

    public function test_administrator_cannot_deactivate_or_archive_self(): void
    {
        $admin = $this->admin(1, 1);
        $service = new AdminAccountService();

        foreach (['status', 'archive'] as $operation) {
            try {
                $operation === 'status'
                    ? $service->setStatus($admin->id, 0, $admin->id)
                    : $service->archive($admin->id, $admin->id);
                $this->fail('Self-deactivation must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('admin', $exception->errors());
            }
        }

        $this->assertSame(1, (int) $admin->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_primary_administrator_is_protected_from_managed_account_actions(): void
    {
        $actor = $this->admin(1, 1);
        $primary = $this->admin(1, null);

        $this->expectException(ValidationException::class);
        (new AdminAccountService())->archive($primary->id, $actor->id);
    }

    private function admin(int $status, ?int $roleId): Admin
    {
        return Admin::create([
            'role_id' => $roleId,
            'username' => uniqid('admin-', true),
            'email' => uniqid('admin-', true) . '@example.com',
            'password' => 'x',
            'status' => $status,
        ]);
    }

    private function createSchema(): void
    {
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
