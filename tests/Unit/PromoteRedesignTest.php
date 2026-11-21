<?php

namespace Tests\Unit;

use Tests\TestCase;

class PromoteRedesignTest extends TestCase
{
    public function test_organiser_promote_page_has_tool_cards_and_side_panels(): void
    {
        $view = file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        foreach (['data-prnew="choose"', 'data-prnew="code"', 'data-prnew="link"', 'data-prnew="featured"', 'id="pr-feat-dlg"', 'id="pr-choose-dlg"', 'data-prfilter="feat"'] as $hook) {
            $this->assertStringContainsString($hook, $view);
        }
        $this->assertStringContainsString('pr-drawer" id="pr-code-dlg"', $view);
        $this->assertStringContainsString('function prOpenFeatured()', $client);
        // Featured placements go straight to Paystack after the campaign is reserved.
        $this->assertStringContainsString("'/tica-api/organizer/promotions/'+id+'/checkout'", $client);
        $this->assertStringContainsString("'id' => (int) \$campaign->id", file_get_contents(app_path('Http/Controllers/FrontEnd/TicaWorkspaceController.php')));
    }

    public function test_admin_page_has_review_queue_placements_and_offers(): void
    {
        $view = file_get_contents(resource_path('views/backend/tica/promotions.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/TicaPromotionController.php'));

        foreach (['queue', 'placements', 'plans'] as $panel) {
            $this->assertStringContainsString('data-promotion-panel="' . $panel . '"', $view);
        }
        $this->assertStringContainsString('name="decision" value="approve"', $view);
        $this->assertStringContainsString('name="decision" value="request_changes"', $view);
        $this->assertStringContainsString('private function reviewChecks(', $controller);
        $this->assertStringContainsString("'calendarBookings' => \$calendarBookings", $controller);
    }
}
