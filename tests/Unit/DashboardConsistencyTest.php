<?php

namespace Tests\Unit;

use Tests\TestCase;

class DashboardConsistencyTest extends TestCase
{
    public function test_admin_and_organizer_load_the_shared_consistency_layer_last(): void
    {
        $admin = file_get_contents(resource_path('views/backend/layout.blade.php'));
        $organizer = file_get_contents(resource_path('views/organizer/layout.blade.php'));

        foreach ([$admin, $organizer] as $layout) {
            $yield = strpos($layout, "@yield('style')");
            $consistency = strpos($layout, 'dashboard-consistency.css');

            $this->assertNotFalse($yield);
            $this->assertNotFalse($consistency);
            $this->assertGreaterThan($yield, $consistency);
        }

        $this->assertGreaterThan(
            strpos($admin, 'human-ui.css'),
            strpos($admin, 'dashboard-consistency.css')
        );
    }

    public function test_shared_dashboard_type_scale_covers_modern_and_legacy_pages(): void
    {
        $css = file_get_contents(public_path('assets/tica-ui/css/dashboard-consistency.css'));

        $this->assertStringContainsString('font-family: "Montserrat"', $css);
        $this->assertStringContainsString('.page-header .page-title', $css);
        $this->assertStringContainsString('.t-page-head h1', $css);
        $this->assertStringContainsString('.promotion-page-header h1', $css);
        $this->assertStringContainsString('.pm-head h1', $css);
        $this->assertStringContainsString('.at-head h1', $css);
        $this->assertStringContainsString('.tica-report-heading h1', $css);
        $this->assertStringContainsString('.page-inner .table h2.d-inline-block', $css);
        $this->assertStringContainsString('font-size: var(--tica-type-page, 30px) !important', $css);
        $this->assertStringContainsString('font-weight: 600 !important', $css);
        $this->assertStringContainsString('font-size: var(--tica-type-page-mobile, 26px) !important', $css);
    }

    public function test_booking_and_transaction_filters_use_responsive_shared_toolbars(): void
    {
        $adminBookings = file_get_contents(resource_path('views/backend/event/booking/index.blade.php'));
        $organizerBookings = file_get_contents(resource_path('views/organizer/event/booking/index.blade.php'));
        $transactions = file_get_contents(resource_path('views/backend/admin/transaction.blade.php'));

        foreach ([$adminBookings, $organizerBookings] as $bookings) {
            $this->assertStringContainsString('class="card-header booking-toolbar"', $bookings);
            $this->assertStringContainsString('class="booking-filters"', $bookings);
            $this->assertStringContainsString('name="booking_id" type="search"', $bookings);
            $this->assertStringContainsString('name="event_title" type="search"', $bookings);
            $this->assertStringNotContainsString('id="searchByStatusForm"', $bookings);
        }

        $this->assertStringContainsString('class="card-header transaction-toolbar"', $transactions);
        $this->assertStringContainsString('class="transaction-filters"', $transactions);
        $this->assertStringContainsString('class="transaction-toolbar__links"', $transactions);
    }

    public function test_numeric_table_headers_and_values_share_the_same_alignment_contract(): void
    {
        $consistency = file_get_contents(public_path('assets/tica-ui/css/dashboard-consistency.css'));
        $designSystem = file_get_contents(public_path('assets/tica-ui/css/ds.css'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $adminTransactions = file_get_contents(resource_path('views/backend/admin/transaction.blade.php'));
        $organizerWithdrawals = file_get_contents(resource_path('views/organizer/withdraw/index.blade.php'));

        $this->assertStringContainsString('.page-inner :is(th, td).t-num', $consistency);
        $this->assertStringContainsString('text-align: right !important', $consistency);
        $this->assertStringContainsString('.t-table :is(th,td).t-num', $designSystem);
        $this->assertStringContainsString('font-variant-numeric:tabular-nums', $designSystem);

        foreach (['Inventory', 'Sold', 'Available', 'Revenue'] as $heading) {
            $this->assertStringContainsString('<th scope="col" class="t-num">'.$heading.'</th>', $client);
            $this->assertStringContainsString('data-label="'.$heading.'" class="t-num"', $client);
        }

        foreach (['Pre Balance', 'Amount', 'After Balance'] as $heading) {
            $this->assertStringContainsString('class="t-num">{{ __(\''.$heading.'\') }}</th>', $adminTransactions);
        }

        foreach (['Total Amount', 'Total Charge', 'Total Payable Amount'] as $heading) {
            $this->assertStringContainsString('class="t-num">{{ __(\''.$heading.'\') }}</th>', $organizerWithdrawals);
        }
    }
}
