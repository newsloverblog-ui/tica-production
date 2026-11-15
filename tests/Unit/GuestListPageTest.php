<?php

namespace Tests\Unit;

use Tests\TestCase;

class GuestListPageTest extends TestCase
{
    public function test_guest_list_leads_with_the_guests_and_keeps_tools_in_dialogs(): void
    {
        $view = file_get_contents(resource_path('views/organizer/event/guest-list/index.blade.php'));

        // One header with the main action; import, invite and export open in dialogs.
        foreach (['id="gl-invite"', 'id="gl-import"', 'id="gl-export"', 'data-gl-open="gl-invite"', 'data-gl-event', 'class="gl-stats"', 'class="gl-tabs"'] as $hook) {
            $this->assertStringContainsString($hook, $view, $hook);
        }
        // Every original action still posts to its route.
        foreach (['guest-list.store', 'guest-list.import', 'guest-list.import-template', 'guest-list.export', 'guest-list.check-in', 'guest-list.cancel'] as $route) {
            $this->assertStringContainsString("organizer.event.{$route}", $view, $route);
        }
        // The consent ticks the server requires are still sent.
        $this->assertSame(2, substr_count($view, 'name="source_attested"'));
        $this->assertStringContainsString('name="privacy_confirmed"', $view);
        // A failed invite or import reopens its dialog so the error is visible.
        $this->assertStringContainsString('data-gl-autoopen', $view);
        // Old Bootstrap layout is gone.
        $this->assertStringNotContainsString('class="row"', $view);
        $this->assertStringNotContainsString('table-striped', $view);
    }

    public function test_guest_list_controller_sends_counts_and_a_safe_status_filter(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Organizer/EventGuestListController.php'));

        $this->assertStringContainsString("in_array(\$request->query('status'), ['accepted', 'pending', 'declined', 'checked_in'], true)", $controller);
        $this->assertStringContainsString("compact('events', 'event', 'invitations', 'imports', 'stats', 'filter')", $controller);
    }
}
