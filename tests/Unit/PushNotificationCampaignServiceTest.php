<?php

namespace Tests\Unit;

use App\Jobs\DispatchPushNotificationCampaign;
use App\Jobs\SendPushNotificationCampaignDelivery;
use App\Models\Guest;
use App\Models\PushNotificationCampaignDelivery;
use App\Notifications\PushNotification;
use App\Services\PushNotificationCampaignService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PushNotificationCampaignServiceTest extends TestCase
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
        config()->set('webpush.database_connection', 'sqlite');
        $this->createSchema();
        Queue::fake();
    }

    public function test_campaign_snapshots_only_subscribed_guests_encrypts_content_and_dispatches(): void
    {
        $subscribed = Guest::create(['endpoint' => 'subscribed']);
        $subscribed->updatePushSubscription('https://push.example.test/one', 'public-key', 'auth-token');
        Guest::create(['endpoint' => 'not-subscribed']);

        $campaign = (new PushNotificationCampaignService())->create(
            'Tonight nearby', 'Doors open at 7.', 'View event', 'https://tica.test/events/1', 7
        );
        $campaign->refresh();

        $this->assertSame(1, $campaign->total_count);
        $this->assertSame(1, $campaign->dispatch_attempts);
        $this->assertNotNull($campaign->dispatch_last_attempt_at);
        $this->assertNull($campaign->dispatch_last_error);
        $this->assertSame($subscribed->id, $campaign->deliveries()->value('guest_id'));
        $this->assertStringNotContainsString(
            'Tonight nearby',
            (string) DB::table('push_notification_campaigns')->where('id', $campaign->id)->value('title')
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'push_notification_campaign.queued', 'subject_id' => $campaign->id,
        ]);
        Queue::assertPushed(DispatchPushNotificationCampaign::class, function ($job) use ($campaign): bool {
            return $job->campaignId === $campaign->id && $job instanceof ShouldBeEncrypted;
        });
    }

    public function test_fanout_and_delivery_are_unique_encrypted_and_complete_campaign(): void
    {
        Notification::fake();
        $guest = Guest::create(['endpoint' => 'subscribed']);
        $guest->updatePushSubscription('https://push.example.test/one', 'public-key', 'auth-token');
        $service = new PushNotificationCampaignService();
        $campaign = $service->create(
            'Tonight nearby', 'Doors open at 7.', 'View event', 'https://tica.test/events/1', 7
        );

        (new DispatchPushNotificationCampaign($campaign->id))->handle($service);
        $delivery = PushNotificationCampaignDelivery::query()->where('campaign_id', $campaign->id)->firstOrFail();
        Queue::assertPushed(SendPushNotificationCampaignDelivery::class, function ($job) use ($delivery): bool {
            return $job->deliveryId === $delivery->id && $job instanceof ShouldBeEncrypted;
        });

        (new SendPushNotificationCampaignDelivery($delivery->id))->handle($service);

        Notification::assertSentTo($guest, PushNotification::class);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->failed_count);
    }

    public function test_exhausted_fanout_returns_campaign_to_scheduler_recovery(): void
    {
        $guest = Guest::create(['endpoint' => 'subscribed']);
        $guest->updatePushSubscription('https://push.example.test/one', 'public-key', 'auth-token');
        $campaign = (new PushNotificationCampaignService())->create(
            'Tonight nearby', 'Doors open at 7.', 'View event', 'https://tica.test/events/1', 7
        );
        $campaign->forceFill(['status' => 'processing'])->save();

        (new DispatchPushNotificationCampaign($campaign->id))->failed(
            new RuntimeException('recipient queue unavailable')
        );

        $this->assertSame('pending', $campaign->fresh()->status);
        $this->assertSame('recipient queue unavailable', $campaign->fresh()->dispatch_last_error);

        Queue::fake();
        $this->artisan('campaigns:dispatch-pending')->assertSuccessful();
        Queue::assertPushed(DispatchPushNotificationCampaign::class, fn ($job): bool =>
            $job->campaignId === $campaign->id
        );
        $this->assertNull($campaign->fresh()->dispatch_last_error);
    }

    private function createSchema(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id(); $table->string('endpoint')->nullable(); $table->timestamps();
        });
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id(); $table->string('subscribable_type'); $table->unsignedBigInteger('subscribable_id');
            $table->string('endpoint', 1024)->unique(); $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable(); $table->string('content_encoding')->nullable(); $table->timestamps();
            $table->index(['subscribable_type', 'subscribable_id']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type')->default('system'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        $migration = require database_path(
            'migrations/2026_11_03_160000_create_push_notification_campaign_tables.php'
        );
        $migration->up();
        $metadata = require database_path(
            'migrations/2026_11_05_070000_add_campaign_dispatch_metadata.php'
        );
        $metadata->up();
    }
}
