<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminSidebarPresentationTest extends TestCase
{
    public function test_admin_account_menu_is_compact_accessible_and_has_a_resilient_avatar(): void
    {
        $sidebar = file_get_contents(resource_path('views/backend/partials/side-navbar.blade.php'));
        $css = file_get_contents(public_path('assets/tica-ui/css/admin-blend.css'));

        $this->assertStringContainsString('class="tica-admin-account{{', $sidebar);
        $this->assertStringContainsString('aria-label="{{ __(\'Open admin account menu\') }}"', $sidebar);
        $this->assertStringContainsString('class="tica-admin-account-avatar"', $sidebar);
        $this->assertStringContainsString("{{ \$adminInitials ?: 'TA' }}", $sidebar);
        $this->assertStringContainsString('onerror="this.remove()"', $sidebar);

        $this->assertStringContainsString('class="tica-admin-account-menu"', $sidebar);
        $this->assertStringContainsString('method="POST" action="{{ route(\'admin.logout\') }}"', $sidebar);
        $this->assertStringContainsString('class="tica-admin-sidebar-search"', $sidebar);
        $this->assertLessThan(
            strpos($sidebar, '<ul class="nav nav-primary">'),
            strpos($sidebar, 'class="tica-admin-sidebar-search"')
        );
        $this->assertStringContainsString('body.tica-admin .tica-admin-account>summary', $css);
        $this->assertStringContainsString('body.tica-admin .tica-admin-account-menu button:focus-visible', $css);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);
    }

    public function test_sidebar_uses_the_shared_event_builder_and_one_outline_icon_family(): void
    {
        $sidebar = file_get_contents(resource_path('views/backend/partials/side-navbar.blade.php'));

        $this->assertStringContainsString("href=\"{{ route('admin.event_builder') }}\"", $sidebar);
        $this->assertStringNotContainsString("href=\"{{ route('admin.choose-event-type'", $sidebar);
        $this->assertStringNotContainsString('class="la ', $sidebar);
        $this->assertStringNotContainsString('class="fas ', $sidebar);
        $this->assertStringNotContainsString('class="fab ', $sidebar);
        $this->assertStringContainsString("{{ __('Audience') }}", $sidebar);
        $this->assertStringContainsString("{{ __('System updates') }}", $sidebar);
    }

    public function test_sidebar_search_returns_direct_keyboard_accessible_destinations(): void
    {
        $sidebar = file_get_contents(resource_path('views/backend/partials/side-navbar.blade.php'));
        $script = file_get_contents(public_path('assets/admin/js/admin-main.js'));
        $css = file_get_contents(public_path('assets/tica-ui/css/admin-blend.css'));

        $this->assertStringContainsString('aria-controls="tica-admin-search-results"', $sidebar);
        $this->assertStringContainsString('role="listbox"', $sidebar);
        $this->assertStringContainsString('Admin navigation search: show direct destinations', $script);
        $this->assertStringContainsString("event.key === 'ArrowDown'", $script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString('.tica-admin-sidebar-search-results', $css);
    }

    public function test_settings_overview_is_inside_the_basic_settings_permission_boundary(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.settings.index');
        $view = file_get_contents(resource_path('views/backend/settings/index.blade.php'));

        $this->assertNotNull($route);
        $this->assertContains('auth:admin', $route->gatherMiddleware());
        $this->assertContains('permission:Basic Settings', $route->gatherMiddleware());
        $this->assertStringContainsString("{{ __('Platform and website') }}", $view);
        $this->assertStringContainsString("{{ __('Services and communication') }}", $view);
        $this->assertStringContainsString("{{ __('Access and operations') }}", $view);
    }
}
