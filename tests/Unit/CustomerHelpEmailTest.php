<?php

namespace Tests\Unit;

use Tests\TestCase;

class CustomerHelpEmailTest extends TestCase
{
    public function test_help_uses_direct_organizer_email_without_support_cards(): void
    {
        $view = file_get_contents(resource_path('views/tica-ui/pages/account.blade.php'));
        $script = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString('id="acd-organizer-email"', $view);
        $this->assertStringContainsString('<b>Email organizer</b>', $view);
        $this->assertStringContainsString("organizerEmail.href='mailto:'", $script);
        $this->assertStringNotContainsString('Message an organizer', $view);
        $this->assertStringNotContainsString('id="acd-support"', $view);
        $this->assertStringNotContainsString('/support-ticket/create?recipient=organizer', $view);
    }

    public function test_customer_organizer_inbox_routes_and_menu_are_removed(): void
    {
        $routes = file_get_contents(base_path('routes/organizer.php'));
        $shell = file_get_contents(resource_path('views/components/shell/organizer.blade.php'));
        $permissions = file_get_contents(config_path('tica_team.php'));

        $this->assertStringNotContainsString('customer-messages', $routes);
        $this->assertStringNotContainsString('Customer messages', $shell);
        $this->assertStringNotContainsString('organizer.customer_messages', $permissions);
    }
}
