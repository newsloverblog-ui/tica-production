<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicaPromotionExpansionMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'promotion_migration_test',
            'database.connections.promotion_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
        ]);
        DB::purge('promotion_migration_test');
        $this->legacySchema();
    }

    protected function tearDown(): void
    {
        DB::purge('promotion_migration_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_existing_promotion_data_is_backfilled_without_repricing_campaigns(): void
    {
        DB::table('tica_promotion_plans')->insert([
            'id' => 1, 'name' => 'Event popup', 'placement' => 'event_popup', 'price' => 75000,
            'currency' => 'NGN', 'duration_days' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tica_promotion_campaigns')->insert([
            'id' => 4, 'organizer_id' => 1, 'event_id' => 2, 'plan_id' => 1, 'payment_transaction_id' => 9,
            'status' => 'pending_review', 'price' => 50000, 'currency' => 'NGN', 'paid_at' => now(),
            'starts_at' => now(), 'ends_at' => now()->addWeek(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_14_180000_expand_tica_promotion_system.php');
        $migration->up();

        $plan = DB::table('tica_promotion_plans')->first();
        $campaign = DB::table('tica_promotion_campaigns')->first();
        $this->assertSame('pop_event', $plan->type);
        $this->assertSame('published', $plan->status);
        $this->assertSame('TP-00000004', $campaign->reference);
        $this->assertSame('paid', $campaign->payment_status);
        $this->assertSame(50000.0, (float) $campaign->price);
        $this->assertTrue(Schema::hasTable('tica_promotion_events'));
        $this->assertTrue(Schema::hasColumn('bookings', 'promotion_campaign_id'));
        $this->assertTrue(Schema::hasColumn('coupons', 'minimum_purchase'));
    }

    private function legacySchema(): void
    {
        Schema::create('tica_promotion_plans', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->string('placement')->unique(); $t->string('description')->nullable(); $t->decimal('price', 12, 2); $t->string('currency', 3); $t->integer('duration_days'); $t->boolean('is_active'); $t->json('settings')->nullable(); $t->timestamps(); });
        Schema::create('tica_promotion_campaigns', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('payment_transaction_id')->nullable(); $t->unsignedBigInteger('approved_by')->nullable(); $t->string('status'); $t->string('headline')->nullable(); $t->text('body')->nullable(); $t->string('destination_url')->nullable(); $t->string('creative_path')->nullable(); $t->decimal('price', 12, 2); $t->string('currency', 3); $t->timestamp('paid_at')->nullable(); $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable(); $t->timestamp('approved_at')->nullable(); $t->text('admin_notes')->nullable(); $t->timestamps(); });
        Schema::create('tica_tracking_links', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->unsignedBigInteger('event_id')->nullable(); $t->string('name'); $t->string('code'); $t->integer('clicks'); $t->timestamps(); });
        Schema::create('coupons', function (Blueprint $t): void { $t->id(); $t->integer('used_count')->default(0); $t->timestamps(); });
        Schema::create('bookings', function (Blueprint $t): void { $t->id(); $t->timestamps(); });
    }
}
