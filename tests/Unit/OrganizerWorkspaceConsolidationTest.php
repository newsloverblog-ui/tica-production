<?php

namespace Tests\Unit;

use Tests\TestCase;

class OrganizerWorkspaceConsolidationTest extends TestCase
{
    public function test_duplicate_organizer_pages_are_get_only_workspace_redirects(): void
    {
        $expected = [
            'organizer.dashboard' => ['overview', 'organizer/dashboard'],
            'organizer.monthly_income' => ['overview', 'organizer/monthly-income'],
            'organizer.edit.profile' => ['settings', 'organizer/edit-profile'],
            'organizer.change.password' => ['settings', 'organizer/change-password'],
            'organizer.event_management.event' => ['events', 'organizer/event-management/events'],
            'organizer.event.ticket' => ['events', 'organizer/event/ticket'],
            'choose-event-type' => ['create', 'organizer/choose-event-type'],
            'organizer.add.event.event' => ['create', 'organizer/add-event'],
            'organizer.event.booking' => ['orders', 'organizer/event-booking'],
            'organizer.withdraw' => ['payouts', 'organizer/withdraw'],
            'organizer.withdraw.create' => ['payouts', 'organizer/withdraw/create'],
        ];

        foreach ($expected as $name => [$method, $uri]) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertSame(['GET', 'HEAD'], $route->methods(), "{$name} must remain read-only");
            $this->assertSame($uri, $route->uri());
            $this->assertSame(
                "App\\Http\\Controllers\\BackEnd\\Organizer\\OrganizerWorkspaceRedirectController@{$method}",
                $route->getActionName()
            );
            $this->assertContains('auth:organizer', $route->gatherMiddleware());
        }
    }

    public function test_specialist_organizer_workflows_remain_available(): void
    {
        foreach ([
            'organizer.event_booking.details',
            'organizer.event.guest-list.index',
            'organizer.event.registrations.index',
            'organizer.data_exports.index',
            'organizer.check_in_ledger_exports.index',
            'organizer.kyc.show',
            'organizer.scanner.devices.index',
        ] as $name) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($name), "Missing specialist route {$name}");
        }
    }

    public function test_shared_organizer_shell_and_report_keep_the_dashboard_contract(): void
    {
        $sidebar = file_get_contents(resource_path('views/organizer/partials/side-navbar.blade.php'));
        $report = file_get_contents(resource_path('views/organizer/event/booking/report.blade.php'));
        $organizerCss = file_get_contents(public_path('assets/tica-ui/css/organizer-blend.css'));
        $dashboardCss = file_get_contents(public_path('assets/tica-ui/css/tica.css'));
        $adminCss = file_get_contents(public_path('assets/tica-ui/css/admin-blend.css'));

        $positions = array_map(fn (string $section) => strpos($sidebar, "'{$section}']"), [
            'Main', 'Grow', 'Money', 'Account',
        ]);
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, array_values(array_unique($positions)));
        $sortedPositions = $positions;
        sort($sortedPositions);
        $this->assertSame($sortedPositions, $positions);
        $this->assertStringContainsString("href=\"{{ url('/dashboard/settings') }}\"", $sidebar);

        $this->assertStringContainsString('class="tica-report-filters"', $report);
        $this->assertStringContainsString('class="table tica-report-table"', $report);
        $this->assertStringContainsString('No bookings match these filters', $report);
        $this->assertStringNotContainsString("<th scope=\"col\">{{ __('Zip Code') }}</th>", $report);

        foreach ([$organizerCss, $dashboardCss, $adminCss] as $css) {
            $this->assertStringContainsString('#FEF7EE', $css);
            $this->assertStringContainsString('#FCEBD7', $css);
            $this->assertStringContainsString('#F2C594', $css);
            $this->assertStringContainsString('#E39A55', $css);
            $this->assertStringContainsString('#B4682E', $css);
        }
    }

    public function test_create_event_uses_the_same_responsive_organizer_shell(): void
    {
        $create = file_get_contents(resource_path('views/tica-ui/pages/create.blade.php'));
        $dashboard = file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
        $script = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $css = file_get_contents(public_path('assets/tica-ui/css/tica.css'));

        $this->assertStringContainsString('id="ce-side"', $create);
        $this->assertStringContainsString('class="db-side od-side"', $dashboard);
        $this->assertStringContainsString("slot.appendChild(side)", $script);
        $this->assertStringContainsString('.ce-shell.has-side{grid-template-columns:248px minmax(0,1fr)', $css);
        $this->assertStringContainsString('body.dashnav-open .ce-side .db-side{transform:none;visibility:visible}', $css);
        $this->assertStringContainsString('.od-side .od-orgc.od-orgc-top{margin:0 4px 10px!important', $css);
        $this->assertStringContainsString('.od-side .od-new{width:calc(100% - 8px);min-height:42px;margin:0 4px 2px', $css);
        $this->assertStringContainsString('.ce-side .db-side a.od-new.on{background:var(--dash-primary)!important', $css);
        $this->assertStringNotContainsString('.page[data-page=dashboard] .od-orgc.od-orgc-top{margin:', $css);
        $this->assertStringContainsString('body.isorg.appmode footer{display:none}', $css);
        $this->assertStringContainsString('body.isorg.appmode #workspace-role{display:none}', $css);
    }

    public function test_team_workspace_has_no_raw_blade_and_explains_server_enforcement(): void
    {
        $dashboard = file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
        $script = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringNotContainsString('@endforeach', $dashboard);
        $this->assertStringNotContainsString('Every protected action is checked on the server', $dashboard);
        $this->assertStringContainsString("D.roleSummaries||{}", $script);
        $this->assertStringContainsString("[['manager','Manager'],['finance','Finance'],['gate','Gate staff']]", $script);
        $this->assertStringNotContainsString("['owner','Owner']", $script);
        $this->assertStringContainsString("Role updated. It applies from their next action.", $script);
    }

    public function test_settings_are_grouped_into_profile_preferences_notifications_and_security(): void
    {
        $dashboard = file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
        $css = file_get_contents(public_path('assets/tica-ui/css/tica.css'));

        $this->assertStringContainsString('class="od-settings-layout"', $dashboard);
        $this->assertStringContainsString('class="od-settings-stack"', $dashboard);
        foreach (['Public profile', 'Sign-in details', 'Contact and location', 'Social profiles', 'Defaults for new events', 'Notifications', 'Security'] as $heading) {
            $this->assertStringContainsString($heading, $dashboard);
        }
        $this->assertStringNotContainsString('Sales update automatically as orders come in.', $dashboard);
        $this->assertStringNotContainsString('These details identify your account and appear where relevant on TICA.', $dashboard);
        $this->assertStringContainsString('.od-settings-layout{display:grid;grid-template-columns:', $css);
    }
}
