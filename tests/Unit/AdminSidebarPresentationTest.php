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
}
