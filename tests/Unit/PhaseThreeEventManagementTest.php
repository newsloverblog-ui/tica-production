<?php

namespace Tests\Unit;

use Tests\TestCase;

class PhaseThreeEventManagementTest extends TestCase
{
    public function test_category_management_has_safe_inline_controls_and_reordering(): void
    {
        $routes = app('router')->getRoutes();
        foreach ([
            'admin.event_management.category.update_status',
            'admin.event_management.category.update_featured',
            'admin.event_management.category.reorder',
        ] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, $name . ' is missing.');
            $this->assertContains('auth:admin', $route->gatherMiddleware());
            $this->assertContains('permission:Event Management', $route->gatherMiddleware());
        }

        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Event/CategoryController.php'));
        $view = file_get_contents(resource_path('views/backend/event/category/index.blade.php'));

        $this->assertStringContainsString("if (\$eventCount > 0)", $controller);
        $this->assertStringNotContainsString("EventContent::where('event_category_id'", $controller);
        $this->assertStringContainsString("'ids.*' => ['required', 'integer', 'distinct', 'exists:event_categories,id']", $controller);
        $this->assertStringContainsString('id="tcat-sortable"', $view);
        $this->assertStringContainsString('data-reorder-url=', $view);
        $this->assertStringContainsString('class="tcat-switch feature"', $view);
        $this->assertStringContainsString("window.location.reload()", $view);
    }

    public function test_shared_builder_supports_timezone_searchable_ownership_and_drafts_at_every_step(): void
    {
        $view = file_get_contents(resource_path('views/tica-ui/pages/create.blade.php'));
        $script = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $controller = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));

        $this->assertStringContainsString('id="c-timezone"', $view);
        $this->assertStringContainsString('id="c-owner-search"', $view);
        $this->assertStringContainsString("fd.append('timezone',\$('c-timezone').value)", $script);
        $this->assertStringContainsString("\$('ce-draft').hidden=false", $script);
        $this->assertStringContainsString("'timezone' => ['required', 'timezone']", $controller);
        $this->assertStringContainsString("'timezone' => \$validated['timezone']", $controller);
    }

    public function test_event_timezone_update_is_available_for_laravel_and_supabase(): void
    {
        $migration = database_path('migrations/2026_11_17_100000_add_timezone_to_events.php');
        $supabase = file_get_contents(database_path('supabase/tica_database_update.sql'));

        $this->assertFileExists($migration);
        $this->assertStringContainsString("add_timezone_to_events", $supabase);
        $this->assertStringContainsString("ADD COLUMN IF NOT EXISTS timezone varchar(64)", $supabase);
    }
}
