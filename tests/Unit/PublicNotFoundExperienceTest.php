<?php

namespace Tests\Unit;

use Tests\TestCase;

class PublicNotFoundExperienceTest extends TestCase
{
    public function test_not_found_page_uses_the_public_site_shell(): void
    {
        $view = file_get_contents(resource_path('views/errors/404.blade.php'));

        $this->assertStringContainsString("@include('tica-ui.partials.header')", $view);
        $this->assertStringContainsString("@include('tica-ui.partials.footer')", $view);
        $this->assertStringContainsString('class="page missing-page"', $view);
        $this->assertStringContainsString("We can't find that page", $view);
        $this->assertStringNotContainsString('class="error-card"', $view);
    }

    public function test_missing_page_and_event_cards_use_the_shared_polish(): void
    {
        $missing = file_get_contents(resource_path('views/tica-ui/pages/missing.blade.php'));
        $styles = file_get_contents(public_path('assets/tica-ui/css/tica.css'));
        $scripts = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString('class="wrap missing-hero"', $missing);
        $this->assertStringContainsString('.missing-hero{', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion:reduce)', $styles);
        $this->assertStringContainsString('.get{position:relative;z-index:2;display:inline-flex', $styles);
        $this->assertStringContainsString('class="buy-meta"', $scripts);
    }
}
