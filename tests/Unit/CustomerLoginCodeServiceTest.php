<?php

namespace Tests\Unit;

use App\Models\ApplicationMailDelivery;
use App\Models\Customer;
use App\Models\CustomerLoginCode;
use App\Services\CustomerLoginCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLoginCodeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        (require database_path('migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'))->up();
        (require database_path('migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'))->up();
        (require database_path('migrations/2026_11_10_121000_add_dedupe_key_to_application_mail_deliveries.php'))->up();
        Queue::fake();
    }

    public function test_verified_customer_receives_single_use_expiring_code(): void
    {
        $customer = $this->customer();
        $service = app(CustomerLoginCodeService::class);

        $service->request(' ADA@example.com ');
        $delivery = ApplicationMailDelivery::query()->sole();
        preg_match('/class="code">\s*(\d{6})\s*</', $delivery->body, $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertSame($customer->id, $service->consume('ada@example.com', $matches[1])->id);
        $this->assertNotNull(CustomerLoginCode::query()->sole()->consumed_at);

        $this->expectException(ValidationException::class);
        $service->consume('ada@example.com', $matches[1]);
    }

    public function test_bad_attempts_persist_and_unknown_email_is_enumeration_safe(): void
    {
        $this->customer();
        $service = app(CustomerLoginCodeService::class);
        $service->request('ada@example.com');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->consume('ada@example.com', '000000');
                $this->fail('An incorrect code must never authenticate.');
            } catch (ValidationException) {
                $this->assertSame($attempt, CustomerLoginCode::query()->sole()->attempts);
            }
        }

        $service->request('missing@example.com');
        $this->assertSame(1, ApplicationMailDelivery::query()->count());
    }

    private function customer(): Customer
    {
        return Customer::create([
            'email' => 'ada@example.com', 'fname' => 'Ada', 'lname' => 'Okafor',
            'username' => 'ada', 'password' => 'not-used', 'status' => 1,
            'email_verified_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->unique(); $table->string('fname')->nullable();
            $table->string('lname')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('email_verified_at')->nullable(); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('customer_login_codes', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('customer_id')->index();
            $table->char('code_hash', 64); $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at'); $table->timestamp('consumed_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
