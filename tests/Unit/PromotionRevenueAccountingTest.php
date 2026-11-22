<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromotionRevenueAccountingTest extends TestCase
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

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price', 14, 2);
            $table->decimal('commission', 14, 2)->default(0);
        });
        Schema::create('tica_promotion_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->decimal('attributed_revenue', 14, 2)->default(0);
        });
        Schema::create('tica_promotion_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('event_type');
            $table->decimal('amount', 14, 2)->default(0);
        });
    }

    public function test_cached_promotion_revenue_is_rebuilt_from_organizer_ticket_revenue(): void
    {
        DB::table('bookings')->insert(['id' => 9, 'price' => 5000, 'commission' => 400]);
        DB::table('tica_promotion_campaigns')->insert(['id' => 3, 'attributed_revenue' => 5400]);
        DB::table('tica_promotion_events')->insert([
            'id' => 7, 'campaign_id' => 3, 'booking_id' => 9,
            'event_type' => 'conversion', 'amount' => 5400,
        ]);

        (include database_path('migrations/2026_11_22_180000_reconcile_promotion_ticket_revenue.php'))->up();

        $this->assertSame(4600.0, (float) DB::table('tica_promotion_events')->value('amount'));
        $this->assertSame(4600.0, (float) DB::table('tica_promotion_campaigns')->value('attributed_revenue'));
    }
}
