<?php

namespace Tests\Unit;

use App\Services\TicaUi\OrganizerOverviewMetrics;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizerOverviewMetricsTest extends TestCase
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
        Carbon::setTestNow('2026-11-13 12:00:00');

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('paymentStatus');
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->string('currencyText')->nullable();
            $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('funnel_analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_name');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_periods_use_inclusive_calendar_days_and_keep_free_tickets_out_of_gross_sales(): void
    {
        $this->booking(1, 'completed', 100, 6, 'NGN', now());
        $this->booking(1, 'free', 0, 0, 'NGN', now());
        $this->booking(1, 'completed', 200, 12, 'NGN', now()->subDays(13)->startOfDay());
        $this->booking(1, 'completed', 300, 18, 'NGN', now()->subDays(14)->endOfDay());
        $this->booking(1, 'completed', 400, 24, 'NGN', now()->subDays(40));
        $this->booking(1, 'completed', 999, 0, 'GHS', now());
        $this->booking(1, 'pending', 888, 0, 'NGN', now());
        $this->booking(2, 'completed', 777, 0, 'NGN', now());

        $this->ticket(10, now());
        $this->ticket(10, now());
        $this->ticket(10, now()->subDays(13)->startOfDay());
        $this->ticket(10, now()->subDays(14)->endOfDay());
        $this->ticket(10, now()->subDays(40));
        $this->ticket(10, now(), now());
        $this->ticket(99, now());

        $this->pageView(10, now());
        $this->pageView(10, now()->subDays(13)->startOfDay());
        $this->pageView(10, now()->subDays(14)->endOfDay());
        $this->pageView(10, now()->subDays(40));
        $this->pageView(99, now());

        $metrics = (new OrganizerOverviewMetrics())->build(1, [10], 12345.67);

        $this->assertSame(['gross' => 318.0, 'sold' => 3, 'views' => 2, 'available' => 12345.67], $metrics['14']);
        $this->assertSame(['gross' => 636.0, 'sold' => 4, 'views' => 3, 'available' => 12345.67], $metrics['30']);
        $this->assertSame(['gross' => 1060.0, 'sold' => 5, 'views' => 4, 'available' => 12345.67], $metrics['all']);
    }

    public function test_overview_range_control_updates_cards_and_chart_together(): void
    {
        $script = (string) file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString(
            "addEventListener('change',function(){drawOverviewKpis(this.value);drawChart(this.value)})",
            $script
        );
        $this->assertStringContainsString('D.kpisByRange&&D.kpisByRange[range]||D.kpis', $script);
    }

    private function booking(int $organizerId, string $status, float $price, float $tax, string $currency, Carbon $createdAt): void
    {
        DB::table('bookings')->insert([
            'organizer_id' => $organizerId,
            'paymentStatus' => $status,
            'price' => $price,
            'tax' => $tax,
            'currencyText' => $currency,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function ticket(int $eventId, Carbon $createdAt, ?Carbon $revokedAt = null): void
    {
        DB::table('booking_tickets')->insert([
            'event_id' => $eventId,
            'revoked_at' => $revokedAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function pageView(int $eventId, Carbon $occurredAt): void
    {
        DB::table('funnel_analytics_events')->insert([
            'event_name' => 'view_event',
            'event_id' => $eventId,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }
}
