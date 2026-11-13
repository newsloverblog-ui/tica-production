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
}
