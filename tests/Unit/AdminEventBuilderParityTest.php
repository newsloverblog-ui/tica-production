<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminEventBuilderParityTest extends TestCase
{
    public function test_admin_event_creation_stays_in_the_admin_workspace(): void
    {
        $builder = app('router')->getRoutes()->getByName('admin.event_builder');
        $store = app('router')->getRoutes()->getByName('admin.event_builder.store');

        $this->assertNotNull($builder);
        $this->assertNotNull($store);
        $this->assertSame(['GET', 'HEAD'], $builder->methods());
        $this->assertSame(['POST'], $store->methods());
        foreach ([$builder, $store] as $route) {
            $this->assertContains('auth:admin', $route->gatherMiddleware());
            $this->assertContains('permission:Event Management', $route->gatherMiddleware());
        }

        $ui = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaUiController.php'));
        $shared = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Event/EventController.php'));
        $view = file_get_contents(resource_path('views/tica-ui/pages/create.blade.php'));
        $shell = file_get_contents(resource_path('views/tica-ui/app.blade.php'));

        $this->assertStringContainsString("'adminBuilder' => \$this->adminBuilder()", $ui);
        $this->assertStringContainsString("'endpoint' => route('admin.event_builder.store')", $ui);
        $this->assertStringContainsString('public function storeAdminEvent', $shared);
        $this->assertStringContainsString("return \$this->createEvent(\n      \$request,", $shared);
        $this->assertStringContainsString("return view('backend.event.event_type')", $controller);
        $this->assertStringContainsString('name="organizer_id"', $view);
        $this->assertStringContainsString('id="c-featured"', $view);
        $this->assertStringContainsString('id="ce-admin"', $view);
        $this->assertStringContainsString("@includeIf('backend.partials.side-navbar')", $shell);
        $this->assertStringContainsString('tica-admin-event-builder', $shell);
        $this->assertStringContainsString("@if (\$adminShell)", $shell);
        $this->assertStringContainsString("if(!m||!topbar)return", file_get_contents(public_path('assets/tica-ui/js/tica.js')));
        $this->assertStringContainsString("if(!m)return", file_get_contents(public_path('assets/tica-ui/js/tica.js')));
        $this->assertStringContainsString("if(!fab||!fk||!fv||!fb)return", file_get_contents(public_path('assets/tica-ui/js/tica.js')));
    }

    public function test_admin_and_organizer_share_the_event_type_decision_but_keep_separate_rights(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));
        $adminType = file_get_contents(resource_path('views/backend/event/event_type.blade.php'));
        $organizerType = file_get_contents(resource_path('views/organizer/event/event_type.blade.php'));
        $selector = file_get_contents(resource_path('views/shared/event-type-selector.blade.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString("return \$this->createEvent(\$request, (int) \$organizer->id, 'organizer');", $controller);
        $this->assertStringContainsString('public function storeAdminEvent', $controller);
        $this->assertStringContainsString("@include('shared.event-type-selector'", $adminType);
        $this->assertStringContainsString("@include('shared.event-type-selector'", $organizerType);
        $this->assertStringContainsString("__('In-person event')", $selector);
        $this->assertStringContainsString("__('Online event')", $selector);
        $this->assertStringContainsString("new URLSearchParams(location.search).get('type')", $client);
    }
}
