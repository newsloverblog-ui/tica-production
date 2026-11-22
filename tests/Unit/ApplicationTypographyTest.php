<?php

namespace Tests\Unit;

use Tests\TestCase;

class ApplicationTypographyTest extends TestCase
{
    public function test_every_application_shell_loads_the_shared_typography_layer_last(): void
    {
        $layouts = [
            resource_path('views/backend/layout.blade.php'),
            resource_path('views/organizer/layout.blade.php'),
            resource_path('views/frontend/layout.blade.php'),
            resource_path('views/frontend/embed-layout.blade.php'),
            resource_path('views/tica-ui/app.blade.php'),
            resource_path('views/backend/auth-layout.blade.php'),
        ];

        foreach ($layouts as $path) {
            $layout = file_get_contents($path);
            $typography = strpos($layout, 'typography.css');

            $this->assertNotFalse($typography, basename($path).' must load typography.css');
            $this->assertGreaterThan(strrpos($layout, "@yield('style')") ?: -1, $typography);
            $this->assertGreaterThan(strrpos($layout, "@yield('custom-style')") ?: -1, $typography);
        }

        $app = file_get_contents(resource_path('views/tica-ui/app.blade.php'));
        $embed = file_get_contents(resource_path('views/frontend/embed-layout.blade.php'));
        $frontend = file_get_contents(resource_path('views/frontend/layout.blade.php'));
        $this->assertStringContainsString('class="tica-app-ui ', $app);
        $this->assertStringContainsString('class="tica-embed-ui ', $embed);
        $this->assertGreaterThan(strpos($app, "@includeIf('backend.partials.styles')"), strpos($app, 'typography.css'));
        $this->assertGreaterThan(strpos($frontend, "@vite(['resources/js/tica-app.tsx'])"), strpos($frontend, 'typography.css'));
    }

    public function test_typography_tokens_cover_the_complete_readable_ui_scale(): void
    {
        $css = file_get_contents(public_path('assets/tica-ui/css/typography.css'));

        $tokens = [
            '--tica-font-sans:',
            '--tica-type-page:',
            '--tica-type-section:',
            '--tica-type-card:',
            '--tica-type-body:',
            '--tica-type-secondary:',
            '--tica-type-nav:',
            '--tica-type-label:',
            '--tica-type-input:',
            '--tica-type-button:',
            '--tica-type-table-head:',
            '--tica-type-table-body:',
            '--tica-type-badge:',
            '--tica-type-helper:',
        ];

        foreach ($tokens as $token) {
            $this->assertStringContainsString($token, $css);
        }

        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString('font-family: "Montserrat"', $css);
        $this->assertStringContainsString('--tica-type-body: clamp(15px', $css);
        $this->assertStringContainsString('--tica-type-table-body: 15px', $css);
        $this->assertStringContainsString('--tica-type-helper: 13px', $css);
        $this->assertStringNotContainsString('--tica-type-body: 12px', $css);
    }

    public function test_typography_layer_normalizes_core_components_and_compact_pages(): void
    {
        $css = file_get_contents(public_path('assets/tica-ui/css/typography.css'));

        foreach ([
            '.page-header .page-title',
            '.page-inner h2',
            '.sidebar .nav > .nav-item > a p',
            '.form-group > label',
            '.form-control',
            '.directory-table',
            '.promotion-table',
            '.tica-report-table',
            '.coupon-list-header',
            '.coupon-row',
            '.coupon-status',
            '.text-xs',
            '.text-sm',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('font-size: var(--tica-type-table-head) !important', $css);
        $this->assertStringContainsString('font-size: var(--tica-type-table-body) !important', $css);
        $this->assertStringContainsString('font-size: 16px !important', $css);
    }
}
