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
            'tica-api/organizer/promotions/{campaign}/checkout' => 'throttle:5,10',
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
            'admin.advertise.tica_promotions.plan.store',
            'admin.advertise.tica_promotions.plan',
            'admin.advertise.tica_promotions.plan.duplicate',
            'admin.advertise.tica_promotions.plan.archive',
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
        foreach (['campaigns', 'plans', 'analytics', 'tracking', 'tools'] as $section) {
            $this->assertStringContainsString('data-promotion-panel="' . $section . '"', $view);
        }
        $this->assertStringContainsString("name=\"starts_at\"", $view);
        $this->assertStringContainsString("name=\"ends_at\"", $view);
        $this->assertStringContainsString("name=\"current_password\"", $view);

        $this->assertIsString($controller);
        $this->assertStringContainsString("Rule::in(['approve', 'reject', 'request_changes', 'pause', 'resume', 'cancel', 'refund'])", $controller);
        $this->assertStringContainsString("where('payment_status', 'paid')->sum('price')", $controller);
        $this->assertStringContainsString('An approved promotion must have an end time in the future.', $controller);
    }

    public function test_promotion_system_uses_server_authority_capacity_locks_and_idempotent_payments(): void
    {
        $service = file_get_contents(app_path('Services/TicaPromotionService.php'));
        $migration = file_get_contents(database_path('migrations/2026_11_14_180000_expand_tica_promotion_system.php'));
        $booking = file_get_contents(app_path('Http/Controllers/FrontEnd/Event/BookingController.php'));

        $this->assertStringContainsString('whereKey($planId)->lockForUpdate()', $service);
        $this->assertStringContainsString("'price' => \$plan->price", $service);
        $this->assertStringContainsString('assertCapacity($plan', $service);
        $this->assertStringContainsString("if (\$campaign->payment_status === 'paid'", $service);
        $this->assertStringContainsString('refundToBalance', $service);
        $this->assertStringContainsString("'transcation_type' => 4", $service);
        $this->assertStringContainsString("Schema::create('tica_promotion_events'", $migration);
        $this->assertStringContainsString("'promotion_campaign_id'", $booking);
        $this->assertStringContainsString("orWhereColumn('used_count', '<', 'max_uses')", $booking);
        $gateway = file_get_contents(app_path('Services/TicaPromotionPaystackService.php'));
        $this->assertStringContainsString("'payment_kind' => 'promotion'", $gateway);
        $this->assertStringContainsString('PaystackPaymentVerifier::errors', $gateway);
        $this->assertStringContainsString("['gateway' => 'paystack', 'gateway_reference' => \$lockedIntent->reference]", $gateway);
    }

    public function test_public_pop_events_and_conversion_analytics_are_connected_to_real_campaigns(): void
    {
        $ui = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaUiController.php'));
        $tracking = file_get_contents(app_path('Http/Controllers/FrontEnd/PromotionTrackingController.php'));
        $analytics = file_get_contents(app_path('Services/TicaPromotionAnalyticsService.php'));
        $sync = file_get_contents(app_path('Console/Commands/SyncTicaPromotions.php'));

        $this->assertStringContainsString("->rotating('event_popup')", $ui);
        $this->assertStringContainsString("recordVisit(\$popup, 'impression'", $ui);
        $this->assertStringContainsString("route('tica.promotion.click'", $ui);
        $this->assertStringContainsString("recordVisit(\$record, 'click'", $tracking);
        $this->assertStringContainsString("session()->put('tica_promotion_campaign_id'", $tracking);
        $this->assertStringContainsString("'event_type' => 'conversion'", $analytics);
        $this->assertStringContainsString("->where('payment_status', 'paid')", $sync);
    }

    public function test_organizer_promote_page_includes_paid_campaigns_codes_and_links(): void
    {
        $view = file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $workspace = file_get_contents(app_path('Services/TicaUi/OrganizerWorkspace.php'));

        // Codes and tracking links stay on the organiser's Promote page.
        foreach (['od-code-min', 'od-link-channel'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $view);
        }
        // Paid packages and campaign performance are part of the same calm Promote workspace.
        foreach (['od-promo-kpis', 'od-package-grid', 'od-campaigns', 'od-promo-f'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $view);
        }
        $this->assertStringContainsString('id="pr-pay-dlg"', $view);
        $this->assertStringNotContainsString('window.prompt', $client);
        // Every code and link row can be edited, paused, deleted; links also copy and show a QR.
        foreach (['data-predit', 'data-prpause', 'data-prdel', 'data-prqr', 'data-copy'] as $hook) {
            $this->assertStringContainsString($hook, $client);
        }
        foreach (['pr-code-dlg', 'pr-link-dlg', 'pr-qr-dlg'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $view);
        }
        $this->assertStringContainsString("'promotionStats' => \$this->promotionStats", $workspace);
        $this->assertStringContainsString("'attributed_tickets'", file_get_contents(app_path('Models/TicaPromotionCampaign.php')));
    }

    public function test_organizer_can_edit_and_delete_codes_and_tracking_links(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaWorkspaceController.php'));

        $this->assertStringContainsString("Route::post('/codes/{coupon}/delete', 'FrontEnd\\TicaWorkspaceController@deleteCode')", $routes);
        $this->assertStringContainsString("Route::post('/links/{link}/delete', 'FrontEnd\\TicaWorkspaceController@deleteLink')", $routes);
        $this->assertStringContainsString('public function deleteCode(int $coupon)', $controller);
        $this->assertStringContainsString('public function deleteLink(int $link)', $controller);
        // A code that is on past orders is kept for records; it can only be paused.
        $this->assertStringContainsString('kept for your records', $controller);
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
