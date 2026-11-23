<?php

namespace Tests\Unit;

use Tests\TestCase;

class AudienceExperienceTest extends TestCase
{
    public function test_audience_routes_use_the_final_shared_interface(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['admin.audience.index', 'admin.user_management.mail_for_subscribers', 'newsletter.unsubscribe'] as $name) {
            $this->assertNotNull($routes->getByName($name));
        }

        $directory = file_get_contents(resource_path('views/backend/end-user/subscriber/index.blade.php'));
        $composer = file_get_contents(resource_path('views/backend/end-user/subscriber/write-email.blade.php'));
        $sidebar = file_get_contents(resource_path('views/backend/partials/side-navbar.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/User/SubscriberController.php'));
        $this->assertStringContainsString("DB::table('subscribers')", $controller);
        $this->assertStringNotContainsString("DB::table('customers')", $controller);
        $this->assertStringNotContainsString("DB::table('booking_tickets')", $controller);
        $this->assertStringContainsString('data-audience-row', $directory);
        $this->assertStringContainsString('Customer accounts and ticket buyers are kept separate', $directory);
        $this->assertStringContainsString("__('Subscribers')", $sidebar);
        $this->assertStringContainsString('Create newsletter email', $composer);
        $this->assertStringNotContainsString('Mail to Subscribers', $sidebar);
    }

    public function test_public_footer_has_an_explicit_newsletter_opt_in(): void
    {
        $footer = file_get_contents(resource_path('views/tica-ui/partials/footer.blade.php'));
        $this->assertStringContainsString('data-newsletter-form', $footer);
        $this->assertStringContainsString('Unsubscribe whenever you like', $footer);
        $this->assertStringContainsString('currentlySubscribed()', file_get_contents(app_path('Services/SubscriberCampaignService.php')));
    }

    public function test_public_navigation_and_purchase_actions_share_final_responsive_rules(): void
    {
        $css = file_get_contents(public_path('assets/tica-ui/css/tica.css'));
        $this->assertStringContainsString('@media (max-width:1240px)', $css);
        $this->assertStringContainsString('.card .get', $css);
        $this->assertStringContainsString('background:#2E4FD8', $css);
    }

    public function test_legacy_event_editor_cannot_be_forced_back_on(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Event/EventController.php'));
        $this->assertStringNotContainsString("request()->boolean('legacy')", $controller);
        $this->assertStringContainsString("redirect()->route('admin.event_builder.edit'", $controller);
    }
}
