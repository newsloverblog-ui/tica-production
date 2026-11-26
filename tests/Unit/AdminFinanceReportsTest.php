<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\AdminController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminFinanceReportsTest extends TestCase
{
    public function test_monthly_reports_include_current_and_legacy_sales_in_the_base_currency(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('currency')->nullable();
            $table->string('payment_status');
            $table->integer('transcation_type');
            $table->integer('organizer_id')->nullable();
            $table->decimal('grand_total');
            $table->decimal('commission');
            $table->decimal('tax');
            $table->timestamp('created_at');
        });

        $sale = [
            'currency' => 'NGN', 'payment_status' => 'completed', 'transcation_type' => 1,
            'organizer_id' => 1, 'grand_total' => 200, 'commission' => 5, 'tax' => 10,
            'created_at' => '2026-01-15 12:00:00',
        ];
        DB::table('transactions')->insert([
            $sale,
            array_replace($sale, ['currency' => null, 'payment_status' => '1']),
            array_replace($sale, ['currency' => 'USD']),
            array_replace($sale, ['payment_status' => 'pending']),
            array_replace($sale, ['payment_status' => 'processed', 'transcation_type' => 6]),
            array_replace($sale, ['created_at' => '2025-01-15 12:00:00']),
        ]);

        $controller = app(AdminController::class);
        $request = Request::create('/admin/monthly-earning', 'GET', ['year' => 2026]);
        $revenue = $controller->monthly_earning($request)->getData();
        $earnings = $controller->monthly_profit($request)->getData();

        $this->assertSame('January', $earnings['months'][0]);
        $this->assertSame(400.0, (float) $revenue['incomes'][0]);
        $this->assertSame(20.0, (float) $revenue['taxs'][0]);
        $this->assertSame(10.0, (float) $earnings['incomes'][0]);
        $this->assertSame(20.0, (float) $earnings['taxs'][0]);
        $this->assertSame(0, $revenue['incomes'][1]);
    }

    public function test_dashboard_renders_report_links_only_with_their_permissions(): void
    {
        $render = fn (array $granted) => view('backend.admin.dashboard-reports', [
            'can' => fn (string $permission) => in_array($permission, $granted, true),
        ])->render();

        $bookings = $render(['Event Bookings']);
        $this->assertStringContainsString('Booking report', $bookings);
        $this->assertStringContainsString('from_date=', $bookings);
        $this->assertStringNotContainsString('Monthly revenue', $bookings);

        $finance = $render(['Lifetime Earning', 'Total Profit', 'Transaction', 'Withdraw Method']);
        $this->assertStringContainsString('Monthly revenue', $finance);
        $this->assertStringContainsString('Payment reconciliation', $finance);
        $this->assertStringContainsString('Payout exports', $finance);
        $this->assertStringNotContainsString('Booking report', $finance);
    }
}
