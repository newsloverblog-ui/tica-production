<?php

namespace Tests\Unit;

use Tests\TestCase;

class TicaPromotionGovernanceTest extends TestCase
{
    public function test_organizer_promotion_mutations_are_authenticated_and_rate_limited(): void
    {
        $expected = [
            'tica-api/organizer/promotions' => 'throttle:10,1',
            'tica-api/organizer/promotions/{campaign}/pay' => 'throttle:5,10',
            'tica-api/organizer/promotions/{campaign}/cancel' => 'throttle:10,1',
            'tica-api/organizer/tracking-pixels' => 'throttle:10,10',
        ];

        foreach ($expected as $uri => $throttle) {
            $route = collect(app('router')->getRoutes())->first(
                fn ($candidate) => $candidate->uri() === $uri && in_array('POST', $candidate->methods(), true)
            );

            $this->assertNotNull($route, "Missing TICA organizer route {$uri}");
            $this->assertContains('auth:organizer', $route->gatherMiddleware());
            $this->assertContains('Deactive:organizer', $route->gatherMiddleware());
            $this->assertContains('EmailStatus:organizer', $route->gatherMiddleware());
            $this->assertContains($throttle, $route->gatherMiddleware());
        }
    }

    public function test_admin_promotion_mutations_require_advertise_permission_and_step_up_password(): void
    {
        foreach ([
            'admin.advertise.tica_promotions.plan',
            'admin.advertise.tica_promotions.campaign',
            'admin.advertise.tica_promotions.pixel',
        ] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing admin promotion route {$name}");
            $this->assertSame(['POST'], $route->methods());
            $this->assertContains('permission:Advertise', $route->gatherMiddleware());
            $this->assertContains('throttle:10,10', $route->gatherMiddleware());
        }

        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/TicaPromotionController.php'));
        $this->assertIsString($controller);
        $this->assertStringContainsString("'current_password' => ['required', 'string', 'max:255']", $controller);
        $this->assertStringContainsString('Hash::check(', $controller);
    }

    public function test_promotions_cannot_submit_scripts_or_self_approve_featured_events(): void
    {
        $workspace = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaWorkspaceController.php'));
        $eventController = file_get_contents(app_path('Http/Controllers/BackEnd/Organizer/EventController.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertIsString($workspace);
        $this->assertStringContainsString("Rule::in(['meta', 'tiktok', 'google'])", $workspace);
        $this->assertStringContainsString("'regex:/^[A-Za-z0-9_-]+$/'", $workspace);
        $this->assertStringNotContainsString('pixel_script', $workspace);

        $this->assertIsString($eventController);
        $this->assertStringContainsString("\$in['is_featured'] = 'no';", $eventController);
        $this->assertStringContainsString("unset(\$in['is_featured']);", $eventController);

        $this->assertIsString($client);
        $this->assertStringContainsString("tag.textContent='Sponsored'", $client);
        $this->assertStringContainsString("h.textContent=p.headline", $client);
        $this->assertStringContainsString("copy.textContent=p.body", $client);
    }
}
