<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\PasswordResetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
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

    public function test_tokens_are_hashed_expiring_one_time_and_audited(): void
    {
        $customer = Customer::create(['email' => 'reset@example.com', 'password' => Hash::make('old'), 'status' => 1]);
        $service = new PasswordResetService();
        $token = $service->issue($customer);
        $stored = DB::table('password_resets')->where('email', $customer->email)->first();

        $this->assertNotSame($token, $stored->token);
        $this->assertTrue(Hash::check($token, $stored->token));
        $service->consume(Customer::class, $customer->email, $token, 'new-password');
        $this->assertTrue(Hash::check('new-password', $customer->fresh()->password));
        $this->assertDatabaseMissing('password_resets', ['email' => $customer->email]);
        $this->assertSame(2, DB::table('audit_logs')->count());

        $this->expectException(ValidationException::class);
        $service->consume(Customer::class, $customer->email, $token, 'another-password');
    }

    public function test_expired_or_tampered_token_cannot_change_password(): void
    {
        $customer = Customer::create(['email' => 'expired@example.com', 'password' => Hash::make('old'), 'status' => 1]);
        $service = new PasswordResetService();
        $service->issue($customer);
        DB::table('password_resets')->where('email', $customer->email)->update(['created_at' => now()->subMinutes(61)]);

        try {
            $service->consume(Customer::class, $customer->email, 'wrong-token', 'new-password');
            $this->fail('Expired or tampered reset tokens must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('token', $exception->errors());
        }

        $this->assertTrue(Hash::check('old', $customer->fresh()->password));
    }

    private function createSchema(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); $table->string('email')->unique(); $table->string('password');
            $table->integer('status'); $table->timestamps();
        });
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index(); $table->string('token'); $table->timestamp('created_at')->nullable();
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
