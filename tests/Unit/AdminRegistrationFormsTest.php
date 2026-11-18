<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminRegistrationFormsTest extends TestCase
{
    public function test_admin_form_routes_use_admin_auth_and_event_permission(): void
    {
        foreach (['index', 'store', 'show', 'settings', 'events', 'push', 'duplicate', 'delete', 'adopt', 'stop'] as $action) {
            $route = Route::getRoutes()->getByName('admin.registration_forms.'.$action);
            $this->assertNotNull($route, $action);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:admin', $middleware, $action);
            $this->assertContains('permission:Event Management', $middleware, $action);
        }
    }

    public function test_admin_field_editor_routes_exist(): void
    {
        foreach (['store', 'reorder', 'update', 'move', 'delete'] as $action) {
            $route = Route::getRoutes()->getByName('admin.registration_forms.questions.'.$action);
            $this->assertNotNull($route, $action);
            $this->assertContains('auth:admin', $route->gatherMiddleware());
        }
    }

    public function test_admin_menu_groups_forms_with_events_and_marks_it_active(): void
    {
        $sidebar = file_get_contents(resource_path('views/backend/partials/side-navbar.blade.php'));

        $this->assertStringContainsString("'admin.registration_forms.*'", $sidebar);
        $this->assertStringContainsString("route('admin.registration_forms.index')", $sidebar);
        $this->assertStringContainsString("request()->routeIs('admin.registration_forms.*') ? 'active'", $sidebar);
    }

    public function test_shared_form_views_switch_to_admin_routes_and_layout(): void
    {
        $index = file_get_contents(resource_path('views/organizer/forms/index.blade.php'));
        $show = file_get_contents(resource_path('views/organizer/forms/show.blade.php'));
        $fields = file_get_contents(resource_path('views/organizer/forms/_fields.blade.php'));

        $this->assertStringContainsString("@extends(\$layout ?? 'organizer.layout')", $index);
        $this->assertStringContainsString("'admin.registration_forms.'", $index);
        $this->assertStringContainsString('name="organizer_id"', $index);
        $this->assertStringContainsString("'admin.registration_forms.'", $show);
        $this->assertStringContainsString("'admin.registration_forms.questions.'", $show);
        $this->assertStringContainsString("'admin.registration_forms.questions.update'", $fields);
    }

    public function test_not_found_page_has_working_role_aware_actions(): void
    {
        $view = file_get_contents(resource_path('views/errors/404.blade.php'));

        $this->assertStringContainsString("auth('admin')->check()", $view);
        $this->assertStringContainsString("auth('organizer')->check()", $view);
        $this->assertStringContainsString("route('admin.dashboard')", $view);
        $this->assertStringContainsString("url('/dashboard')", $view);
        $this->assertStringContainsString("url('/discover')", $view);
        $this->assertStringNotContainsString("url('/events')", $view);
    }
}
