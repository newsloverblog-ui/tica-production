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
            resource_path('views/organizer/pwa/index.blade.php'),
            resource_path('views/auth/account-security.blade.php'),
            resource_path('views/auth/mfa-challenge.blade.php'),
            resource_path('views/auth/become-organizer.blade.php'),
            resource_path('views/organizer/kyc/lock-payout-account.blade.php'),
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
            '--tica-text-display-xl:',
            '--tica-text-display-l:',
            '--tica-text-hero:',
            '--tica-text-h1:',
            '--tica-text-h2:',
            '--tica-text-h3:',
            '--tica-text-h4:',
            '--tica-text-h5:',
            '--tica-text-h6:',
            '--tica-text-body-lg:',
            '--tica-text-body:',
            '--tica-text-body-sm:',
            '--tica-text-secondary:',
            '--tica-text-nav:',
            '--tica-text-label:',
            '--tica-text-input:',
            '--tica-text-button:',
            '--tica-text-table-head:',
            '--tica-text-table:',
            '--tica-text-caption:',
            '--tica-text-metric:',
            '--tica-text-financial:',
            '--tica-text-dashboard-title:',
            '--tica-text-interface-heading:',
            '--tica-text-eyebrow:',
            '--tica-text-order-total:',
        ];

        foreach ($tokens as $token) {
            $this->assertStringContainsString($token, $css);
        }

        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString('font-family: "Montserrat"', $css);
        $this->assertStringNotContainsString('font-family: "Inter"', $css);
        $this->assertStringNotContainsString('inter-latin', $css);
        $this->assertStringContainsString('font-weight: 400 800', $css);
        $this->assertStringContainsString('--tica-font-brand: "Montserrat"', $css);
        $this->assertStringContainsString('--tica-font-ui: var(--tica-font-brand)', $css);
        $this->assertStringContainsString('--tica-type-display-xl: clamp(', $css);
        $this->assertStringContainsString('--tica-type-body: 1rem', $css);
        $this->assertStringContainsString('--tica-type-input: 1rem', $css);
        $this->assertStringContainsString('--tica-type-caption: .8125rem', $css);
        $this->assertStringNotContainsString('@import', $css);
    }

    public function test_typography_layer_normalizes_core_components_and_compact_pages(): void
    {
        $css = file_get_contents(public_path('assets/tica-ui/css/typography.css'));

        foreach ([
            '.page-header .page-title',
            'body h2',
            '.nav > .nav-item > a p',
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

        $this->assertStringContainsString('font: var(--tica-text-table-head) !important', $css);
        $this->assertStringContainsString('font: var(--tica-text-table) !important', $css);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $css);
    }
}
