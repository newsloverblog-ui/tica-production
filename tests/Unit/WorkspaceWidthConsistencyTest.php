<?php

namespace Tests\Unit;

use Tests\TestCase;

class WorkspaceWidthConsistencyTest extends TestCase
{
    public function test_organizer_pages_share_the_wide_workspace_frame(): void
    {
        $css = file_get_contents(public_path('assets/tica-ui/css/ds.css'));

        $this->assertStringContainsString('--t-page-max:1720px', $css);
        $this->assertStringContainsString('body.orgshell .ov,body.orgshell .ov-wrap,body.orgshell .creategrid', $css);
        $this->assertStringContainsString('body.tica-organizer .tsc,body.tica-organizer .kv', $css);
        $this->assertStringContainsString('body.orgshell-legacy .page-inner', $css);
        $this->assertStringContainsString('max-width:var(--t-page-max)!important', $css);
    }

    public function test_customer_workspaces_use_the_same_wide_responsive_canvas(): void
    {
        $ticaCss = file_get_contents(public_path('assets/tica-ui/css/tica.css'));
        $frontCss = file_get_contents(resource_path('css/tica-front.css'));
        $reactCss = file_get_contents(resource_path('css/react-app.css'));
        $dashboard = file_get_contents(resource_path('js/components/ui/Dashboard.tsx'));

        $this->assertStringContainsString('.acd-main{width:100%;max-width:1720px', $ticaCss);
        $this->assertStringContainsString('width:min(1720px,calc(100% - 32px))', $frontCss);
        $this->assertStringContainsString('.dashboard-page .shell', $reactCss);
        $this->assertStringContainsString('width:min(100%,1760px)', $reactCss);
        $this->assertStringContainsString("'dashboard-page min-h-[70vh] bg-background'", $dashboard);
    }

    public function test_gate_setup_keeps_operational_hooks_inside_the_responsive_layout(): void
    {
        $page = file_get_contents(resource_path('views/organizer/pwa/dashboard.blade.php'));
        $panels = file_get_contents(resource_path('views/organizer/pwa/partials/panels.blade.php'));
        $css = file_get_contents(public_path('assets/tica-ui/css/ds.css'));

        $this->assertStringContainsString('class="tsc tsc-setup"', $page);
        $this->assertStringContainsString('id="scanner-device"', $panels);
        $this->assertStringContainsString('id="scanner-staff-form"', $panels);
        $this->assertStringContainsString('id="scanner-offline"', $panels);
        $this->assertStringContainsString('id="scanner-conflicts-card"', $panels);
        $this->assertStringContainsString('body.tica-organizer .tsc-setup>.row', $css);
        $this->assertStringContainsString('grid-template-columns:minmax(300px,.82fr) minmax(500px,1.18fr)', $css);
    }
}
