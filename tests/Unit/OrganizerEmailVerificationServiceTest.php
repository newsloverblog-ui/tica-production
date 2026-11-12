<?php

namespace Tests\Unit;

use App\Jobs\SendOrganizerEmailVerification;
use App\Mail\OrganizerEmailVerificationMail;
use App\Models\Organizer;
use App\Models\OrganizerEmailVerificationDelivery;
use App\Services\OrganizerEmailVerificationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrganizerEmailVerificationServiceTest extends TestCase
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
        DB::table('basic_settings')->insert([
            'uniqid' => 12345, 'website_title' => 'Tica', 'organizer_admin_approval' => 0,
        ]);
        DB::table('mail_templates')->insert([
            'mail_type' => 'verify_email', 'mail_subject' => 'Verify email',
            'mail_body' => 'Hello {username}, {verification_link} — {website_title}',
        ]);
        Queue::fake();
    }

    public function test_request_is_durable_rate_limited_and_dispatches_an_encrypted_job(): void
    {
        $organizer = $this->organizer();
        $service = new OrganizerEmailVerificationService();

        $delivery = $service->request($organizer);
        $duplicate = $service->request($organizer);

        $this->assertSame($delivery->id, $duplicate->id);
        $this->assertSame('pending', $delivery->status);
        $this->assertNotSame($organizer->email, $delivery->email_hash);
        $this->assertSame(1, $delivery->fresh()->dispatch_attempts);
        $this->assertNotNull($delivery->fresh()->dispatch_last_attempt_at);
        Queue::assertPushed(SendOrganizerEmailVerification::class, function ($job) use ($delivery) {
            return $job->deliveryId === $delivery->id && $job instanceof ShouldBeEncrypted;
        });
        Queue::assertPushed(SendOrganizerEmailVerification::class, 1);
    }

    public function test_job_sends_once_through_configured_mailer_with_a_signed_expiring_link(): void
    {
        $organizer = $this->organizer();
        $delivery = (new OrganizerEmailVerificationService())->request($organizer);
        Mail::fake();

        $job = new SendOrganizerEmailVerification($delivery->id);
        $job->handle(new OrganizerEmailVerificationService());
        $job->handle(new OrganizerEmailVerificationService());

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        Mail::assertSent(OrganizerEmailVerificationMail::class, function ($mail) {
            preg_match('/href="([^"]+)"/', $mail->mailBody, $matches);
            return isset($matches[1])
                && Request::create(htmlspecialchars_decode($matches[1]))->hasValidSignature();
        });
        Mail::assertSent(OrganizerEmailVerificationMail::class, 1);
    }

    public function test_verification_is_bound_to_current_email_and_activates_when_admin_approval_is_off(): void
    {
        $organizer = $this->organizer();
        $service = new OrganizerEmailVerificationService();
        $delivery = $service->request($organizer);

        $verified = $service->verify($organizer->id, $delivery->public_id, $delivery->email_hash);

        $this->assertNotNull($verified->email_verified_at);
        $this->assertSame(1, (int) $verified->status);
        $this->assertSame('verified', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->verified_at);
    }

    public function test_email_change_and_expired_delivery_fail_closed(): void
    {
        $organizer = $this->organizer();
        $service = new OrganizerEmailVerificationService();
        $delivery = $service->request($organizer);
        $organizer->forceFill(['email' => 'changed@example.com'])->save();

        try {
            $service->verify($organizer->id, $delivery->public_id, $delivery->email_hash);
            $this->fail('An email-change must invalidate the verification link.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $organizer->forceFill(['email' => 'organizer@example.com'])->save();
        $delivery->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->expectException(HttpException::class);
        $service->verify($organizer->id, $delivery->public_id, $delivery->email_hash);
    }

    public function test_failed_queue_handoff_is_recorded_and_recoverable(): void
    {
        $service = new class extends OrganizerEmailVerificationService {
            public bool $failDispatch = true;

            protected function pushDeliveryJob(int $deliveryId): void
            {
                if ($this->failDispatch) throw new \RuntimeException('queue unavailable');
                parent::pushDeliveryJob($deliveryId);
            }
        };

        $delivery = $service->request($this->organizer());
        $this->assertSame('queue unavailable', $delivery->fresh()->dispatch_last_error);
        $this->assertSame(1, $delivery->fresh()->dispatch_attempts);
        Queue::assertNothingPushed();

        $service->failDispatch = false;
        $this->assertTrue($service->dispatchDelivery($delivery->id));
        $this->assertSame(2, $delivery->fresh()->dispatch_attempts);
        $this->assertNull($delivery->fresh()->dispatch_last_error);
        Queue::assertPushed(SendOrganizerEmailVerification::class);
    }

    private function organizer(): Organizer
    {
        return Organizer::create([
            'email' => 'organizer@example.com', 'username' => 'organizer-one',
            'password' => 'hashed', 'status' => 0,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->unique(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('email_verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('organizer_email_verification_deliveries', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->char('email_hash', 64); $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable(); $table->timestamp('expires_at');
            $table->timestamp('alerted_at')->nullable(); $table->timestamps();
        });
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('uniqid')->unique(); $table->string('website_title')->nullable();
            $table->unsignedTinyInteger('organizer_admin_approval')->default(0);
        });
        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id(); $table->string('mail_type')->unique(); $table->string('mail_subject');
            $table->text('mail_body');
        });
    }
}
