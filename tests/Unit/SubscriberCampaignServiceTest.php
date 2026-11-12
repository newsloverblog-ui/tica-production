<?php

namespace Tests\Unit;

use App\Jobs\DispatchSubscriberCampaign;
use App\Jobs\SendSubscriberCampaignDelivery;
use App\Models\Subscriber;
use App\Models\SubscriberCampaignDelivery;
use App\Services\ConfiguredMailer;
use App\Services\SubscriberCampaignService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SubscriberCampaignServiceTest extends TestCase
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
        Queue::fake();
    }

    public function test_campaign_snapshots_recipients_encrypts_contact_data_and_dispatches(): void
    {
        Subscriber::create(['email_id' => 'buyer@example.com']);
        Subscriber::create(['email_id' => 'invalid-address']);

        $campaign = (new SubscriberCampaignService())->create('New events', '<p>Discover this week.</p>', 7);
        $campaign->refresh();

        $this->assertSame(2, $campaign->total_count);
        $this->assertSame('pending', $campaign->status);
        $this->assertSame(1, $campaign->dispatch_attempts);
        $this->assertNotNull($campaign->dispatch_last_attempt_at);
        $this->assertNull($campaign->dispatch_last_error);
        $this->assertSame(2, $campaign->deliveries()->count());
        $this->assertSame(1, $campaign->deliveries()->where('status', 'failed')->count());
        $this->assertStringNotContainsString(
            'buyer@example.com',
            (string) DB::table('subscriber_campaign_deliveries')->where('status', 'pending')->value('recipient_email')
        );
        $this->assertStringNotContainsString(
            'Discover this week',
            (string) DB::table('subscriber_campaigns')->where('id', $campaign->id)->value('body')
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'subscriber_campaign.queued', 'subject_id' => $campaign->id,
        ]);
        Queue::assertPushed(DispatchSubscriberCampaign::class, function ($job) use ($campaign): bool {
            return $job->campaignId === $campaign->id && $job instanceof ShouldBeEncrypted;
        });
    }

    public function test_failed_initial_handoff_is_durable_and_scheduler_recovers_same_campaign(): void
    {
        Subscriber::create(['email_id' => 'buyer@example.com']);
        $service = new class extends SubscriberCampaignService {
            protected function pushCampaignJob(int $campaignId): void
            {
                throw new RuntimeException('queue connection unavailable');
            }
        };

        $campaign = $service->create('New events', '<p>Ready</p>', 7);

        $this->assertSame('pending', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->dispatch_attempts);
        $this->assertSame('queue connection unavailable', $campaign->fresh()->dispatch_last_error);
        Queue::assertNothingPushed();

        $this->artisan('campaigns:dispatch-pending')
            ->expectsOutputToContain('Campaign dispatch attempted: 1 | failed: 0 | alerted: 0')
            ->assertSuccessful();

        Queue::assertPushed(DispatchSubscriberCampaign::class, fn ($job) => $job->campaignId === $campaign->id);
        $campaign->refresh();
        $this->assertSame(2, $campaign->dispatch_attempts);
        $this->assertNull($campaign->dispatch_last_error);
        $this->assertSame(1, $campaign->deliveries()->count());
    }

    public function test_fanout_and_delivery_are_unique_encrypted_and_complete_campaign(): void
    {
        Subscriber::create(['email_id' => 'buyer@example.com']);
        $service = new SubscriberCampaignService();
        $campaign = $service->create('New events', '<p>Ready</p>', 7);

        (new DispatchSubscriberCampaign($campaign->id))->handle($service);
        $delivery = SubscriberCampaignDelivery::query()->where('campaign_id', $campaign->id)->firstOrFail();
        Queue::assertPushed(SendSubscriberCampaignDelivery::class, function ($job) use ($delivery): bool {
            return $job->deliveryId === $delivery->id && $job instanceof ShouldBeEncrypted;
        });

        $mailer = Mockery::mock(ConfiguredMailer::class);
        $mailer->shouldReceive('sendHtml')
            ->once()
            ->with('buyer@example.com', 'New events', '<p>Ready</p>');

        (new SendSubscriberCampaignDelivery($delivery->id))->handle($mailer, $service);

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->failed_count);
    }

    public function test_exhausted_fanout_returns_campaign_to_scheduler_recovery(): void
    {
        Subscriber::create(['email_id' => 'buyer@example.com']);
        $campaign = (new SubscriberCampaignService())->create('New events', '<p>Ready</p>', 7);
        $campaign->forceFill(['status' => 'processing'])->save();

        (new DispatchSubscriberCampaign($campaign->id))->failed(
            new RuntimeException('recipient queue unavailable')
        );

        $this->assertSame('pending', $campaign->fresh()->status);
        $this->assertSame('recipient queue unavailable', $campaign->fresh()->dispatch_last_error);

        Queue::fake();
        $this->artisan('campaigns:dispatch-pending')->assertSuccessful();
        Queue::assertPushed(DispatchSubscriberCampaign::class, fn ($job): bool =>
            $job->campaignId === $campaign->id
        );
        $this->assertNull($campaign->fresh()->dispatch_last_error);
    }

    private function createSchema(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id(); $table->string('email_id'); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type')->default('system'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        $migration = require database_path(
            'migrations/2026_11_03_140000_create_subscriber_campaign_tables.php'
        );
        $migration->up();
        $metadata = require database_path(
            'migrations/2026_11_05_070000_add_campaign_dispatch_metadata.php'
        );
        $metadata->up();
    }
}
