<?php

namespace Tests\Unit;

use Tests\TestCase;

class OrganizerEventDefaultPreferencesTest extends TestCase
{
    public function test_transfer_default_flows_from_settings_to_event_enforcement(): void
    {
        $view = (string) file_get_contents(resource_path('views/tica-ui/pages/create.blade.php'));
        $script = (string) file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $controller = (string) file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));
        $prefill = (string) file_get_contents(app_path('Services/TicaUi/EventBuilderPrefill.php'));
        $lifecycle = (string) file_get_contents(app_path('Services/TicketLifecycleService.php'));

        $this->assertStringContainsString('id="c-trans"', $view);
        $this->assertStringContainsString("t.checked=!!pr.transfers", $script);
        $this->assertStringContainsString("fd.append('allow_transfers',$('c-trans').checked?'1':'0')", $script);
        $this->assertStringContainsString("'allow_transfers' => ['required', 'boolean']", $controller);
        $this->assertStringContainsString(
            "'allow_transfers' => TicaPlatform::on('transfers') && \$request->boolean('allow_transfers')",
            $controller,
        );
        $this->assertStringContainsString(
            "'allow_transfers' => (TicaPlatform::on('transfers') || \$event->allow_transfers) && \$request->boolean('allow_transfers')",
            $controller,
        );
        $this->assertStringContainsString("'allow_transfers' => (bool) \$event->allow_transfers", $prefill);
        $this->assertStringContainsString("$('c-trans').checked=!!E.allow_transfers", $script);
        $this->assertStringContainsString('if (isset($event->allow_transfers) && !$event->allow_transfers)', $lifecycle);
    }
}
