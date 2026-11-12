<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Event\EventContent;
use App\Services\EventShareCardService;
use Tests\TestCase;

class EventShareCardServiceTest extends TestCase
{
    public function test_it_renders_a_social_preview_with_the_required_dimensions(): void
    {
        $event = new Event([
            'thumbnail' => 'missing-file.jpg',
            'start_date' => '2026-11-20',
            'start_time' => '18:30:00',
        ]);
        $content = new EventContent([
            'title' => 'A clear, trustworthy event preview for social sharing',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $png = (new EventShareCardService())->render($event, $content);
        $size = getimagesizefromstring($png);

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertIsArray($size);
        $this->assertSame(1200, $size[0]);
        $this->assertSame(630, $size[1]);
        $this->assertSame('image/png', $size['mime']);
    }

    public function test_it_handles_long_titles_without_changing_the_canvas_contract(): void
    {
        $event = new Event(['start_date' => '2026-12-01', 'start_time' => '09:00:00']);
        $content = new EventContent([
            'title' => str_repeat('An exceptionally descriptive event title ', 12),
            'city' => 'Port Harcourt',
            'country' => 'Nigeria',
        ]);

        $png = (new EventShareCardService())->render($event, $content);
        $size = getimagesizefromstring($png);

        $this->assertSame([1200, 630], [$size[0], $size[1]]);
        $this->assertLessThan(2 * 1024 * 1024, strlen($png));
    }
}
