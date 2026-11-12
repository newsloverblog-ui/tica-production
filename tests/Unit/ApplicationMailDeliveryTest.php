<?php

namespace Tests\Unit;

use App\Jobs\SendApplicationMailDelivery;
use App\Models\ApplicationMailDelivery;
use App\Services\ApplicationMailService;
use App\Services\ConfiguredMailer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ApplicationMailDeliveryTest extends TestCase
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
        $migration = require database_path(
            'migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'
        );
        $migration->up();
        $dispatchMigration = require database_path(
            'migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'
        );
        $dispatchMigration->up();
        Queue::fake();
    }

    public function test_delivery_is_private_durable_and_dispatches_encrypted_job(): void
    {
        $delivery = (new ApplicationMailService())->queueHtml(
            'password_reset', 'Buyer@Example.com', 'Reset access', '<p>Private link</p>', [], 'support@example.com'
        );

        $raw = DB::table('application_mail_deliveries')->where('id', $delivery->id)->first();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('buyer@example.com', $delivery->recipient_email);
        $this->assertStringNotContainsString('buyer@example.com', (string) $raw->recipient_email);
        $this->assertStringNotContainsString('Private link', (string) $raw->body);
        $this->assertNotSame('support@example.com', (string) $raw->reply_to);
        $this->assertSame(1, $delivery->fresh()->dispatch_attempts);
        $this->assertNotNull($delivery->fresh()->dispatch_last_attempt_at);
        Queue::assertPushed(SendApplicationMailDelivery::class, function ($job) use ($delivery): bool {
            return $job->deliveryId === $delivery->id && $job instanceof ShouldBeEncrypted;
        });
    }

    public function test_job_sends_once_and_records_terminal_state(): void
    {
        $delivery = (new ApplicationMailService())->queueHtml(
            'contact', 'team@example.com', 'Question', '<p>Hello</p>', [], 'buyer@example.com'
        );
        $mailer = Mockery::mock(ConfiguredMailer::class);
        $mailer->shouldReceive('sendHtml')
            ->once()
            ->with('team@example.com', 'Question', '<p>Hello</p>', [], 'buyer@example.com');
        $job = new SendApplicationMailDelivery($delivery->id);

        $job->handle($mailer);
        $job->handle($mailer);

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertNotNull($delivery->fresh()->sent_at);
    }

    public function test_invalid_recipient_reply_to_and_attachment_fail_before_dispatch(): void
    {
        $service = new ApplicationMailService();

        foreach ([
            fn () => $service->queueHtml('contact', 'invalid', 'Subject', '<p>Body</p>'),
            fn () => $service->queueHtml('contact', 'team@example.com', 'Subject', '<p>Body</p>', [], 'invalid'),
            fn () => $service->queueHtml('contact', 'team@example.com', 'Subject', '<p>Body</p>', ['/missing.pdf']),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Invalid delivery input must fail closed.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, ApplicationMailDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_failed_queue_handoff_is_durable_and_recovered_by_the_poller(): void
    {
        $service = new class extends ApplicationMailService {
            protected function pushDeliveryJob(int $deliveryId): void
            {
                throw new \RuntimeException('queue unavailable');
            }
        };
        $delivery = $service->queueHtml(
            'contact', 'team@example.com', 'Question', '<p>Hello</p>'
        );

        $this->assertSame('pending', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->dispatch_attempts);
        $this->assertSame('queue unavailable', $delivery->fresh()->dispatch_last_error);
        Queue::assertNothingPushed();

        $this->artisan('mail:dispatch-pending')
            ->expectsOutputToContain('Application mail dispatch attempted: 1')
            ->assertSuccessful();

        $this->assertSame(2, $delivery->fresh()->dispatch_attempts);
        $this->assertNull($delivery->fresh()->dispatch_last_error);
        Queue::assertPushed(SendApplicationMailDelivery::class, fn ($job): bool =>
            $job->deliveryId === $delivery->id
        );
    }

    public function test_migration_exposes_delivery_contract(): void
    {
        $this->assertTrue(Schema::hasColumns('application_mail_deliveries', [
            'public_id', 'kind', 'source_type', 'source_id', 'created_by_type', 'created_by_id',
            'recipient_hash', 'recipient_email', 'subject', 'body', 'attachments', 'reply_to',
            'status', 'attempts', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
            'last_error', 'processing_at', 'sent_at', 'failed_at', 'alerted_at',
        ]));
    }
}
