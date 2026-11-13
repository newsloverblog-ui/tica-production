<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminEventBuilderParityTest extends TestCase
{
    public function test_admin_event_creation_stays_in_the_admin_workspace(): void
    {
        $builder = app('router')->getRoutes()->getByName('admin.event_builder');

        $this->assertNotNull($builder);
        $this->assertSame(['GET', 'HEAD'], $builder->methods());
        $this->assertContains('auth:admin', $builder->gatherMiddleware());
        $this->assertContains('permission:Event Management', $builder->gatherMiddleware());
        $this->assertNull(app('router')->getRoutes()->getByName('admin.event_builder.store'));

        $redirect = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaUiController.php'));
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Event/EventController.php'));
        $view = file_get_contents(resource_path('views/backend/event/create.blade.php'));

        $this->assertStringContainsString("redirect()->route('admin.choose-event-type'", $redirect);
        $this->assertStringContainsString("return view('backend.event.event_type')", $controller);
        $this->assertStringContainsString("return view('backend.event.create'", $controller);
        $this->assertStringContainsString("@extends('backend.layout')", $view);
        $this->assertStringContainsString('name="organizer_id"', $view);
        $this->assertStringContainsString('name="is_featured"', $view);
        $this->assertStringContainsString('name="status"', $view);
        $this->assertStringContainsString('id="my-dropzone"', $view);
        $this->assertStringContainsString('name="online_join_url"', $view);
    }

    public function test_admin_and_organizer_share_the_event_type_decision_but_keep_separate_rights(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));
        $adminType = file_get_contents(resource_path('views/backend/event/event_type.blade.php'));
        $organizerType = file_get_contents(resource_path('views/organizer/event/event_type.blade.php'));
        $selector = file_get_contents(resource_path('views/shared/event-type-selector.blade.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString('return $this->createEvent($request, (int) $organizer->id);', $controller);
        $this->assertStringNotContainsString('storeAdminEvent', $controller);
        $this->assertStringContainsString("@include('shared.event-type-selector'", $adminType);
        $this->assertStringContainsString("@include('shared.event-type-selector'", $organizerType);
        $this->assertStringContainsString("__('In-person event')", $selector);
        $this->assertStringContainsString("__('Online event')", $selector);
        $this->assertStringContainsString("new URLSearchParams(location.search).get('type')", $client);
    }
}
