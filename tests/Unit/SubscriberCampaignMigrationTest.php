<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriberCampaignMigrationTest extends TestCase
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
    }

    public function test_migration_applies_and_rolls_back_campaign_delivery_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_03_140000_create_subscriber_campaign_tables.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('subscriber_campaigns', [
            'public_id', 'created_by_admin_id', 'subject', 'body', 'status',
            'total_count', 'sent_count', 'failed_count', 'started_at', 'completed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('subscriber_campaign_deliveries', [
            'campaign_id', 'subscriber_id', 'recipient_hash', 'recipient_email', 'status',
            'attempts', 'last_error', 'processing_at', 'sent_at', 'failed_at', 'alerted_at',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasTable('subscriber_campaign_deliveries'));
        $this->assertFalse(Schema::hasTable('subscriber_campaigns'));
    }
}
