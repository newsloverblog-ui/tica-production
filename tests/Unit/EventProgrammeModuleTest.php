<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EventProgrammeModuleTest extends TestCase
{
    public function test_schema_and_live_database_script_cover_the_programme(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/migrations/2026_11_17_180000_create_event_programme_tables.php');
        $sql = file_get_contents($root . '/database/supabase/tica_database_update.sql');

        $this->assertStringContainsString("Schema::create('event_programme_people'", $migration);
        $this->assertStringContainsString("Schema::create('event_agenda_items'", $migration);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS public.event_programme_people', $sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS public.event_agenda_items', $sql);
        $this->assertStringContainsString('ALTER TABLE public.event_programme_people ENABLE ROW LEVEL SECURITY', $sql);
        $this->assertStringContainsString("('2026_11_17_180000_create_event_programme_tables')", $sql);
    }

    public function test_builder_collects_people_images_and_agenda_without_replacing_tickets(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/resources/views/tica-ui/pages/create.blade.php');
        $script = file_get_contents($root . '/public/assets/tica-ui/js/tica.js');

        $this->assertStringContainsString('id="c-programme"', $view);
        $this->assertStringContainsString('value="speaker"', $view);
        $this->assertStringContainsString('value="artist"', $view);
        $this->assertStringContainsString('id="ce-agenda-editor"', $view);
        $this->assertStringContainsString("programme_people['+i+'][image]", $script);
        $this->assertStringContainsString("agenda_items['+i+'][person_index]", $script);
        $this->assertStringContainsString("fd.append('tiers['+i+'][name]'", $script);
    }

    public function test_server_validates_ownership_counts_and_agenda_times(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/app/Http/Controllers/FrontEnd/TicaOrganizerController.php');
        $service = file_get_contents($root . '/app/Services/EventProgrammeService.php');

        $this->assertStringContainsString("'programme_people' =>", $controller);
        $this->assertStringContainsString("'max:24'", $controller);
        $this->assertStringContainsString("'agenda_items' => ['nullable', 'array', 'max:50']", $controller);
        $this->assertStringContainsString('An agenda item must end after it starts.', $controller);
        $this->assertStringContainsString("where('event_id', \$event->id)", $service);
        $this->assertStringContainsString('This programme guest no longer belongs to the event.', $service);
    }

    public function test_public_event_page_has_responsive_people_and_agenda_sections(): void
    {
        $root = dirname(__DIR__, 2);
        $catalog = file_get_contents($root . '/app/Services/TicaUi/EventCatalog.php');
        $script = file_get_contents($root . '/public/assets/tica-ui/js/tica.js');
        $css = file_get_contents($root . '/public/assets/tica-ui/css/tica.css');

        $this->assertStringContainsString("'programme' => [", $catalog);
        $this->assertStringContainsString('function eventProgramme(e)', $script);
        $this->assertStringContainsString('Featured speakers', $script);
        $this->assertStringContainsString('ev-agenda-list', $script);
        $this->assertStringContainsString('.ev-people{display:grid', $css);
        $this->assertStringContainsString('@media (max-width:520px)', $css);
    }
}
