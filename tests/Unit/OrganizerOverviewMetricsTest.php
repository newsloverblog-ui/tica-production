<?php

namespace Tests\Unit;

use App\Services\TicaUi\OrganizerOverviewMetrics;
use App\Services\TicaUi\OrganizerWorkspace;
use App\Models\Language;
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
            $table->decimal('commission', 14, 2)->default(0);
            $table->string('currencyText')->nullable();
            $table->string('email')->nullable();
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

        // Orders count completed and free bookings; each period also carries the window just before it.
        $this->assertSame(['gross' => 300.0, 'sold' => 3, 'views' => 2, 'orders' => 4, 'available' => 12345.67,
            'prev' => ['gross' => 300.0, 'sold' => 1, 'views' => 1, 'orders' => 1]], $metrics['14']);
        $this->assertSame(['gross' => 600.0, 'sold' => 4, 'views' => 3, 'orders' => 5, 'available' => 12345.67,
            'prev' => ['gross' => 400.0, 'sold' => 1, 'views' => 1, 'orders' => 1]], $metrics['30']);
        $this->assertSame(['gross' => 1000.0, 'sold' => 5, 'views' => 4, 'orders' => 6, 'available' => 12345.67, 'prev' => null], $metrics['all']);
    }

    public function test_overview_range_control_updates_cards_and_chart_together(): void
    {
        $script = (string) file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        // The segmented control and the phone dropdown both go through setRange.
        $this->assertStringContainsString("function setRange(v){OV.range=v;drawRangeControls();drawOverviewKpis(v);drawChart(v)}", $script);
        $this->assertStringContainsString("\$('od-range').addEventListener('change',function(){setRange(this.value)})", $script);
        $this->assertStringContainsString('D.kpisByRange&&D.kpisByRange[OV.range]||D.kpis', $script);
    }

    public function test_overview_is_a_detailed_responsive_dashboard(): void
    {
        $view = (string) file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
        $script = (string) file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $styles = (string) file_get_contents(public_path('assets/tica-ui/css/ds.css'));

        foreach (['db-kpis', 'db-chart', 'od-chart-table', 'od-money', 'od-top', 'od-next', 'db-ord', 'od-channels', 'od-range-seg', 'od-range'] as $hook) {
            $this->assertStringContainsString('id="'.$hook.'"', $view, $hook);
        }
        foreach (['od-attn', 'od-types', 'od-audience'] as $removed) {
            $this->assertStringNotContainsString('id="'.$removed.'"', $view, $removed);
        }
        foreach (['Audience', 'conversion', 'Available now', 'Where sales come from', 'New tracking link', 'Show as a table'] as $label) {
            $this->assertStringContainsString($label, $script.$view, $label);
        }
        // The cards stay concise; detailed comparison remains in the chart below.
        $this->assertStringNotContainsString('function delta(now,prev)', $script);
        $this->assertStringNotContainsString('ov-summary__foot', $script);
        $this->assertStringNotContainsString('No change', $script);
        $this->assertStringContainsString("svg.addEventListener('pointermove'", $script);
        // The overview uses one integrated four-part summary instead of five repetitive cards.
        $this->assertStringContainsString('class="ov-summary"', $view);
        $this->assertStringContainsString("class=\"ov-summary__item", $script);
        $this->assertStringNotContainsString("class=\"ov-kpi", $script);
        $this->assertStringContainsString('.ov-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr))', $styles);
        // The layout follows the dashboard's own width, not the screen's, and the events table
        // turns into cards when its card gets narrow.
        $this->assertStringContainsString('.ov-wrap{container-type:inline-size;container-name:ov}', $styles);
        $this->assertStringContainsString('@container ov (max-width:899px)', $styles);
        $this->assertStringContainsString('@container (max-width:620px)', $styles);
        $this->assertStringContainsString('.ov-table thead{display:none}', $styles);
        $this->assertSame(2, substr_count($view, 'class="ov-row '));
        $this->assertStringNotContainsString('No other events yet', $script);
        $this->assertStringContainsString("'checkedIn' => (int) \$categories->sum('checkedIn')", (string) file_get_contents(app_path('Services/TicaUi/OrganizerWorkspace.php')));
    }

    public function test_audience_analytics_are_scoped_deduplicated_and_aggregate_only(): void
    {
        $this->booking(1, 'completed', 100, 0, 'NGN', now(), 'ada@example.com');
        $this->booking(1, 'completed', 100, 0, 'NGN', now()->subDays(10), 'ADA@example.com');
        $this->booking(1, 'free', 0, 0, 'NGN', now()->subDays(40), 'chi@example.com');
        $this->booking(1, 'pending', 100, 0, 'NGN', now(), 'pending@example.com');
        $this->booking(2, 'completed', 100, 0, 'NGN', now(), 'other@example.com');

        $method = new \ReflectionMethod(OrganizerWorkspace::class, 'audience');
        $method->setAccessible(true);
        $analytics = $method->invoke(new OrganizerWorkspace(new Language()), 1);

        $this->assertSame(['customers' => 2, 'active30' => 1, 'repeat' => 1], $analytics);
    }

    private function booking(int $organizerId, string $status, float $price, float $tax, string $currency, Carbon $createdAt, ?string $email = null): void
    {
        DB::table('bookings')->insert([
            'organizer_id' => $organizerId,
            'paymentStatus' => $status,
            'price' => $price,
            'tax' => $tax,
            'commission' => 0,
            'currencyText' => $currency,
            'email' => $email,
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
