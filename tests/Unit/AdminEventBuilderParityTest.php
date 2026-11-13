<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminEventBuilderParityTest extends TestCase
{
    public function test_admin_builder_routes_are_protected_and_the_legacy_entries_converge(): void
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
        $this->assertContains('throttle:10,1', $store->gatherMiddleware());

        $legacy = file_get_contents(app_path('Http/Controllers/BackEnd/Event/EventController.php'));
        $this->assertGreaterThanOrEqual(2, substr_count($legacy, "redirect()->route('admin.event_builder')"));
    }

    public function test_admin_and_organizer_share_one_event_persistence_flow_with_separate_rights(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));
        $view = file_get_contents(resource_path('views/tica-ui/pages/create.blade.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString('return $this->createEvent($request, (int) $organizer->id, false, \'organizer\');', $controller);
        $this->assertStringContainsString('return $this->createEvent($request, $organizerId, $request->boolean(\'is_featured\'), \'admin\');', $controller);
        $this->assertStringContainsString("'organizer_id' => ['nullable', 'integer'", $controller);
        $this->assertStringContainsString("'is_featured' => ['nullable', 'boolean']", $controller);
        $this->assertStringContainsString('Assign an organizer before using registration approvals.', $controller);

        $this->assertStringContainsString('id="ce-admin" hidden', $view);
        $this->assertStringContainsString('id="c-owner"', $view);
        $this->assertStringContainsString('id="c-featured"', $view);
        $this->assertStringContainsString("fetch(ADMIN?ADMIN.endpoint:'/tica-api/events'", $client);
        $this->assertStringContainsString("if(ADMIN){fd.append('organizer_id'", $client);
    }
}
