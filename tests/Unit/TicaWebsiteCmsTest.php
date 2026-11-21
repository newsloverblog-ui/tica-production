<?php

namespace Tests\Unit;

use App\Support\TicaSite;
use Illuminate\Http\Request;
use Tests\TestCase;

class TicaWebsiteCmsTest extends TestCase
{
    public function test_site_defaults_cover_branding_seo_and_ordered_home_sections(): void
    {
        $site = TicaSite::defaults();

        $this->assertSame(['branding', 'seo', 'home', 'menu', 'footer', 'contact'], array_keys($site));
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $site['branding']['primary_color']);
        $this->assertNotEmpty($site['seo']['title']);
        $this->assertSame(array_keys(TicaSite::SECTIONS), $site['home']['section_order']);
    }

    public function test_website_studio_has_branding_seo_pages_and_reordering_controls(): void
    {
        $view = file_get_contents(resource_path('views/backend/tica/website.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/TicaWebsiteController.php'));

        foreach (['branding', 'seo', 'home', 'pages', 'menu', 'footer', 'contact'] as $tab) {
            $this->assertStringContainsString("'{$tab}' =>", $controller);
        }
        foreach (['logo_upload', 'og_image_upload', 'section_order[]', 'data-down', 'admin.custom_pages.create_page'] as $hook) {
            $this->assertStringContainsString($hook, $view);
        }
    }

    public function test_public_site_uses_branding_seo_sitemap_robots_and_server_purification(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertNotNull($routes->getByName('tica.sitemap'));
        $this->assertNotNull($routes->getByName('tica.robots'));

        $app = file_get_contents(resource_path('views/tica-ui/app.blade.php'));
        $header = file_get_contents(resource_path('views/tica-ui/partials/header.blade.php'));
        $content = file_get_contents(resource_path('views/tica-ui/pages/content.blade.php'));
        $customPages = file_get_contents(app_path('Http/Controllers/BackEnd/CustomPageController.php'));
        $this->assertStringContainsString("TicaSite::get('branding')", $app);
        $this->assertStringContainsString("TicaSite::get('branding')", $header);
        $this->assertStringContainsString('Purifier::clean', $content);
        $this->assertStringContainsString("Purifier::clean(\$request[\$language->code . '_content'], 'youtube')", $customPages);
    }

    public function test_removed_push_vendor_is_absent_from_runtime_and_admin_registry(): void
    {
        $paths = [config_path('services.php'), app_path('Support/Integrations/IntegrationRegistry.php'), app_path('Support/Integrations/IntegrationTester.php')];
        foreach ($paths as $path) {
            $this->assertStringNotContainsString('onesignal', strtolower(file_get_contents($path)), $path);
        }
    }

    public function test_public_policies_have_final_company_details_without_draft_placeholders(): void
    {
        $policies = file_get_contents(resource_path('views/tica-ui/pages/policies.blade.php'));

        $this->assertStringContainsString('TICA Innovations', $policies);
        $this->assertStringContainsString('Paul Unigwe', $policies);
        foreach (['Draft for legal review', '[company legal name]', '[analytics tool]', '[ad platforms]', 'Nigeria Data Protection Commission', 'Federal Competition and Consumer Protection Commission'] as $draftText) {
            $this->assertStringNotContainsString($draftText, $policies);
        }
    }

    public function test_home_search_stays_relaxed_and_keeps_feature_art_square(): void
    {
        $home = file_get_contents(resource_path('views/tica-ui/pages/home.blade.php'));
        $discover = file_get_contents(resource_path('views/tica-ui/pages/discover.blade.php'));
        $script = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $styles = file_get_contents(public_path('assets/tica-ui/css/tica.css'));

        $this->assertStringNotContainsString('h-format', $home . $script);
        $this->assertStringNotContainsString('All events', $home);
        $searchStart = strpos($home, '<form class="search ms"');
        $searchEnd = strpos($home, '</form>', $searchStart);
        $searchMarkup = substr($home, $searchStart, $searchEnd - $searchStart);
        $this->assertSame(1, substr_count($searchMarkup, '<svg '), 'Only the primary search icon should be visible.');
        $this->assertStringContainsString('.search.ms .ms-f label.sr', $styles);
        $this->assertStringContainsString('height: 52px !important', $styles);
        $this->assertStringContainsString('height: 50px !important', $styles);
        $this->assertStringNotContainsString('d-format', $discover . $script);
        $this->assertStringContainsString('--featured-square: clamp(280px, 30vw, 340px)', $styles);
        $this->assertStringContainsString('aspect-ratio: 1 / 1 !important', $styles);
    }

    public function test_home_event_cards_preserve_mobile_posters_and_ticket_entry_photos(): void
    {
        $home = file_get_contents(resource_path('views/tica-ui/pages/home.blade.php'));
        $styles = file_get_contents(public_path('assets/tica-ui/css/tica.css'));

        foreach (['event-phone-concert-pexels-v3.webp', 'event-ticket-check-pexels-v1.webp'] as $asset) {
            $this->assertStringContainsString($asset, $home);
            $this->assertFileExists(public_path("assets/tica-ui/img/{$asset}"));
        }

        $this->assertStringNotContainsString('tk-pass-qr', $home);
        $this->assertStringNotContainsString('Ticket ready', $home);
        $this->assertStringContainsString('.band #home-wall .cardlink', $styles);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) clamp(116px, 30vw, 132px)', $styles);
        $this->assertStringContainsString('aspect-ratio: 4 / 5 !important', $styles);
        $this->assertStringContainsString('object-fit: contain !important', $styles);
        $this->assertStringContainsString('.tk-art .tk-entry-detail img', $styles);
    }
}
