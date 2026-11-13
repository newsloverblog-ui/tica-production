<?php

namespace Tests\Unit;

use App\Http\Middleware\TicaRetiredAdminPage;
use Illuminate\Http\Request;
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

    public function test_admin_promotion_center_covers_the_organizer_governance_flow(): void
    {
        $view = file_get_contents(resource_path('views/backend/tica/promotions.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/TicaPromotionController.php'));

        $this->assertIsString($view);
        foreach (['campaigns', 'plans', 'tracking', 'tools'] as $section) {
            $this->assertStringContainsString('data-promotion-panel="' . $section . '"', $view);
        }
        $this->assertStringContainsString("name=\"starts_at\"", $view);
        $this->assertStringContainsString("name=\"ends_at\"", $view);
        $this->assertStringContainsString("name=\"current_password\"", $view);

        $this->assertIsString($controller);
        $this->assertStringContainsString("Rule::in(['approve', 'reject', 'pause', 'cancel'])", $controller);
        $this->assertStringContainsString("whereNotNull('paid_at')->sum('price')", $controller);
        $this->assertStringContainsString('An approved promotion must have an end time in the future.', $controller);
    }

    public function test_modern_promotion_center_is_not_redirected_as_a_retired_advertising_page(): void
    {
        $routes = app('router')->getRoutes();
        $promotion = Request::create('/admin/advertise/tica-promotions', 'GET');
        $promotion->setRouteResolver(fn () => $routes->getByName('admin.advertise.tica_promotions'));

        $response = (new TicaRetiredAdminPage())->handle($promotion, fn () => response('promotion center'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('promotion center', $response->getContent());

        $legacy = Request::create('/admin/advertise/settings', 'GET');
        $legacy->setRouteResolver(fn () => $routes->getByName('admin.advertise.settings'));
        $redirect = (new TicaRetiredAdminPage())->handle($legacy, fn () => response('legacy'));

        $this->assertTrue($redirect->isRedirect(route('admin.tica.website', ['tab' => 'home'])));
    }
}
