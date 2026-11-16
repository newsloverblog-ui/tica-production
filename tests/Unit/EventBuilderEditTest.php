<?php

namespace Tests\Unit;

use App\Services\TicaUi\EventBuilderPrefill;
use Tests\TestCase;

class EventBuilderEditTest extends TestCase
{
    public function test_edit_routes_use_the_builder_and_keep_their_guards(): void
    {
        $routes = app('router')->getRoutes();
        $edit = $routes->getByName('admin.event_builder.edit');
        $update = $routes->getByName('admin.event_builder.update');
        $organizer = $routes->getByName('tica.edit');

        $this->assertNotNull($edit);
        $this->assertNotNull($update);
        $this->assertNotNull($organizer);
        foreach ([$edit, $update] as $route) {
            $this->assertContains('auth:admin', $route->gatherMiddleware());
            $this->assertContains('permission:Event Management', $route->gatherMiddleware());
        }
        $this->assertContains('auth:organizer', $organizer->gatherMiddleware());

        $legacy = file_get_contents(app_path('Http/Controllers/BackEnd/Event/EventController.php'));
        $this->assertStringContainsString("redirect()->route('admin.event_builder.edit'", $legacy);
    }

    public function test_saved_descriptions_come_back_as_plain_paragraphs(): void
    {
        $this->assertSame("First line\nsecond\n\nNext & last", EventBuilderPrefill::plainText('<p>First line<br>second</p><p>Next &amp; last</p>'));
        $this->assertSame('', EventBuilderPrefill::plainText(''));
    }

    public function test_edit_builder_restores_the_attached_registration_form(): void
    {
        $script = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString("set('c-reg-form',E.registration_form_id||'')", $script);
        $this->assertStringContainsString("sel.value=keep||editForm||''", $script);
    }

    public function test_publishing_does_not_require_a_flyer(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));
        $script = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString("'flyer' => ['nullable', 'file'", $controller);
        $this->assertStringNotContainsString("setCreateMessage('Add a flyer before you publish.'", $script);
    }
}
