<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\AdminController;
use App\Http\Controllers\BackEnd\Organizer\OrganizerController;
use App\Models\Organizer;
use App\Support\MonthlyReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonthlyReportTest extends TestCase
{
    public function test_monthly_reports_preserve_finance_totals_and_calendar_boundaries(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->integer('transcation_type');
            $table->integer('organizer_id')->nullable();
            $table->string('payment_status');
            $table->decimal('grand_total', 12, 2);
            $table->decimal('commission', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->dateTime('created_at');
        });

        foreach ([
            [1, 12, '1', 100, 10, 2, '2026-01-01 00:00:00'],
            [2, null, '1', 80, 0, 3, '2026-01-31 23:59:59'],
            [3, 12, '1', 35, 7, 0, '2026-01-09 12:00:00'],
            [3, 12, '2', 25, 3, 5, '2026-01-09 12:00:00'],
            [1, 13, '1', 60, 6, 4, '2026-01-09 12:00:00'],
            [1, 12, '1', 125, 5, 5, '2026-12-31 23:59:59'],
            [1, 12, '1', 999, 50, 50, '2027-01-01 00:00:00'],
        ] as [$type, $organizer, $status, $total, $commission, $tax, $date]) {
            DB::table('transactions')->insert([
                'transcation_type' => $type, 'organizer_id' => $organizer, 'payment_status' => $status,
                'grand_total' => $total, 'commission' => $commission, 'tax' => $tax, 'created_at' => $date,
            ]);
        }

        $request = Request::create('/admin/monthly-profit', 'GET', ['year' => '2026']);
        $profit = (new AdminController())->monthly_profit($request)->getData();
        $earnings = (new AdminController())->monthly_earning($request)->getData();
        Auth::guard('organizer')->setUser((new Organizer())->forceFill(['id' => 12]));
        $income = (new OrganizerController())->monthly_income($request)->getData();

        $this->assertCount(12, $profit['months']);
        $this->assertSame('January', $earnings['months'][0]);
        $this->assertEquals([23, 9, 80], [$profit['incomes'][0], $profit['taxs'][0], $profit['admin_profit'][0]]);
        $this->assertEquals([5, 5, 0], [$profit['incomes'][11], $profit['taxs'][11], $profit['admin_profit'][11]]);
        $this->assertEquals([240, 6], [$earnings['incomes'][0], $earnings['taxs'][0]]);
        $this->assertSame(0, $earnings['incomes'][1]);
        $this->assertEquals([100, 25, 17, 60], [$income['incomes'][0], $income['rejects'][0], $income['commissions'][0], $income['expenses'][0]]);
    }

    public function test_postgresql_month_grouping_uses_extract_and_a_year_range(): void
    {
        $query = MonthlyReport::query(DB::connection('pgsql')->table('transactions'), 2026);
        $this->assertStringContainsString('CAST(EXTRACT(MONTH FROM created_at) AS integer)', $query->toSql());
        $this->assertSame(['2026-01-01 00:00:00', '2027-01-01 00:00:00'], $query->getBindings());
    }
}
