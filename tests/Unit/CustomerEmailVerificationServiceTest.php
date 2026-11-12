<?php

namespace Tests\Unit;

use App\Jobs\SendCustomerEmailVerification;
use App\Mail\CustomerEmailVerificationMail;
use App\Models\Customer;
use App\Services\CustomerEmailVerificationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerEmailVerificationServiceTest extends TestCase
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
        DB::table('basic_settings')->insert(['website_title' => 'Tica']);
        DB::table('mail_templates')->insert([
            'mail_type' => 'verify_email', 'mail_subject' => 'Verify email',
            'mail_body' => 'Hello {username}, {verification_link} — {website_title}',
        ]);
        Queue::fake();
    }

    public function test_request_is_durable_cooled_down_and_dispatches_encrypted_job(): void
    {
        $customer = $this->customer();
        $service = new CustomerEmailVerificationService();

        $delivery = $service->request($customer);
        $duplicate = $service->request($customer);

        $this->assertSame($delivery->id, $duplicate->id);
        $this->assertNotSame($customer->email, $delivery->email_hash);
        $this->assertSame(1, $delivery->fresh()->dispatch_attempts);
        $this->assertNotNull($delivery->fresh()->dispatch_last_attempt_at);
        Queue::assertPushed(SendCustomerEmailVerification::class, function ($job) use ($delivery) {
            return $job->deliveryId === $delivery->id && $job instanceof ShouldBeEncrypted;
        });
        Queue::assertPushed(SendCustomerEmailVerification::class, 1);
    }

    public function test_job_sends_once_with_signed_expiring_link(): void
    {
        $delivery = (new CustomerEmailVerificationService())->request($this->customer());
        Mail::fake();

        $job = new SendCustomerEmailVerification($delivery->id);
        $job->handle(new CustomerEmailVerificationService());
        $job->handle(new CustomerEmailVerificationService());

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        Mail::assertSent(CustomerEmailVerificationMail::class, function ($mail) {
            preg_match('/href="([^"]+)"/', $mail->mailBody, $matches);
            return isset($matches[1])
                && Request::create(htmlspecialchars_decode($matches[1]))->hasValidSignature();
        });
        Mail::assertSent(CustomerEmailVerificationMail::class, 1);
    }

    public function test_verification_activates_customer_and_is_idempotent(): void
    {
        $customer = $this->customer();
        $service = new CustomerEmailVerificationService();
        $delivery = $service->request($customer);

        $verified = $service->verify($customer->id, $delivery->public_id, $delivery->email_hash);
        $verifiedAgain = $service->verify($customer->id, $delivery->public_id, $delivery->email_hash);

        $this->assertNotNull($verified->email_verified_at);
        $this->assertSame(1, (int) $verified->status);
        $this->assertSame($verified->id, $verifiedAgain->id);
        $this->assertSame('verified', $delivery->fresh()->status);
    }

    public function test_email_change_and_expiry_fail_closed(): void
    {
        $customer = $this->customer();
        $service = new CustomerEmailVerificationService();
        $delivery = $service->request($customer);
        $customer->forceFill(['email' => 'changed@example.com'])->save();

        try {
            $service->verify($customer->id, $delivery->public_id, $delivery->email_hash);
            $this->fail('Changing the customer email must invalidate the link.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $customer->forceFill(['email' => 'customer@example.com'])->save();
        $delivery->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->expectException(HttpException::class);
        $service->verify($customer->id, $delivery->public_id, $delivery->email_hash);
    }

    public function test_failed_queue_handoff_is_recorded_and_recoverable(): void
    {
        $service = new class extends CustomerEmailVerificationService {
            public bool $failDispatch = true;

            protected function pushDeliveryJob(int $deliveryId): void
            {
                if ($this->failDispatch) throw new \RuntimeException('queue unavailable');
                parent::pushDeliveryJob($deliveryId);
            }
        };

        $delivery = $service->request($this->customer());
        $this->assertSame('queue unavailable', $delivery->fresh()->dispatch_last_error);
        $this->assertSame(1, $delivery->fresh()->dispatch_attempts);
        Queue::assertNothingPushed();

        $service->failDispatch = false;
        $this->assertTrue($service->dispatchDelivery($delivery->id));
        $this->assertSame(2, $delivery->fresh()->dispatch_attempts);
        $this->assertNull($delivery->fresh()->dispatch_last_error);
        Queue::assertPushed(SendCustomerEmailVerification::class);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'email' => 'customer@example.com', 'fname' => 'Ada', 'lname' => 'Okafor',
            'username' => 'ada-okafor', 'password' => 'hashed', 'status' => 0,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->unique(); $table->string('fname')->nullable();
            $table->string('lname')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('email_verified_at')->nullable(); $table->string('verification_token')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_email_verification_deliveries', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('customer_id');
            $table->char('email_hash', 64); $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable(); $table->timestamp('expires_at');
            $table->timestamp('alerted_at')->nullable(); $table->timestamps();
        });
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->string('website_title')->nullable();
        });
        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id(); $table->string('mail_type')->unique(); $table->string('mail_subject');
            $table->text('mail_body');
        });
    }
}
