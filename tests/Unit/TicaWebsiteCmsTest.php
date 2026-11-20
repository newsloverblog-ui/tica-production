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
}
