<?php

namespace Tests\Unit;

use Tests\TestCase;

class EventDetailPresentationTest extends TestCase
{
    public function test_multiple_date_schedule_and_ticket_selector_share_the_same_display_source(): void
    {
        $view = (string) file_get_contents(resource_path('views/frontend/event/event-details.blade.php'));

        $this->assertStringContainsString('$displayStartDateTime = $startDateTime ?: null;', $view);
        $this->assertStringContainsString('$displayEndDateTime = $endDateTime ?: $displayStartDateTime;', $view);
        $this->assertStringContainsString('Carbon::parse($displayStartDateTime)', $view);
        $this->assertStringNotContainsString('Carbon::parse($content->start_date)->timezone', $view);
    }

    public function test_mobile_ticket_access_preserves_document_reading_order(): void
    {
        $view = (string) file_get_contents(resource_path('views/frontend/event/event-details.blade.php'));
        $css = (string) file_get_contents(public_path('assets/front/css/event-detail-pulse.css'));

        $this->assertStringContainsString('class="pulse-mobile-ticket-cta" href="#event-ticket-panel"', $view);
        $this->assertStringContainsString('class="sidebar-sticky" id="event-ticket-panel"', $view);
        $this->assertStringContainsString('.pulse-event-layout .event-details-content-inner { grid-row: 3;', $css);
        $this->assertStringContainsString('.pulse-event-layout .sidebar-sticky { position: static; grid-row: 4;', $css);
    }

    public function test_desktop_hero_category_control_has_room_for_its_label(): void
    {
        $css = (string) file_get_contents(public_path('assets/front/css/home-city-pulse.css'));

        $this->assertStringContainsString('width: min(100%, 980px);', $css);
        $this->assertStringContainsString('minmax(215px, 225px)', $css);
    }
}
