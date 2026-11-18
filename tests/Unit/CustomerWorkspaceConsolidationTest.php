<?php

namespace Tests\Unit;

use Tests\TestCase;

class CustomerWorkspaceConsolidationTest extends TestCase
{
    public function test_legacy_customer_landing_pages_redirect_to_the_unified_account(): void
    {
        $customer = file_get_contents(app_path('Http/Controllers/FrontEnd/CustomerController.php'));
        $bookings = file_get_contents(app_path('Http/Controllers/FrontEnd/Event/CustomerBookingController.php'));

        $this->assertMatchesRegularExpression('/function change_password\(\).*?redirect\(\'\/account#profile\'\)/s', $customer);
        $this->assertMatchesRegularExpression('/function edit_profile\(\).*?redirect\(\'\/account#profile\'\)/s', $customer);
        $this->assertMatchesRegularExpression('/function wishlist\(\).*?redirect\(\'\/account#saved\'\)/s', $customer);
        $this->assertMatchesRegularExpression('/function my_booking\(\).*?redirect\(\'\/account#orders\'\)/s', $bookings);
    }

    public function test_legacy_sidebar_points_at_current_account_sections(): void
    {
        $sidebar = file_get_contents(resource_path('views/frontend/customer/partials/sidebar.blade.php'));

        foreach (['/account', '/account#orders', '/account#saved', '/account#profile'] as $url) {
            $this->assertStringContainsString($url, $sidebar);
        }
        $this->assertStringNotContainsString("route('customer.edit.profile')", $sidebar);
        $this->assertStringNotContainsString("route('customer.change.password')", $sidebar);
    }
}
