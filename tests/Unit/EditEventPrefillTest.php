<?php

namespace Tests\Unit;

use Tests\TestCase;

class EditEventPrefillTest extends TestCase
{
    public function test_pages_that_need_server_data_load_as_pages(): void
    {
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        // Editing an event (/create/12) must load the saved event from the server.
        $this->assertStringContainsString("if(p==='create'&&(path!=='create'||(window.TICA_DATA&&TICA_DATA.builderEdit)))return false;", $client);
        // The dashboard and gate check-in only get their data with a full page load.
        $this->assertStringContainsString("if((p==='dashboard'||p==='create')&&!(window.TICA_DATA&&TICA_DATA.dashboard))return false;", $client);
        $this->assertStringContainsString("if(p==='checkin'&&!(window.TICA_DATA&&TICA_DATA.gate))return false;", $client);
    }

    public function test_edit_link_points_at_the_event_editor(): void
    {
        $workspace = file_get_contents(app_path('Services/TicaUi/OrganizerWorkspace.php'));
        $this->assertStringContainsString("'editUrl' => url('/create/' . \$event['id'])", $workspace);
    }
}
