<?php

namespace Tests\Unit;

use App\Models\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventPublicationFlagSerializationTest extends TestCase
{
    public static function eventFlags(): array
    {
        return [
            'hybrid mode' => ['is_hybrid'],
            'remaining ticket count' => ['show_remaining'],
            'waitlist' => ['waitlist_enabled'],
            'ticket transfers' => ['allow_transfers'],
        ];
    }

    #[DataProvider('eventFlags')]
    public function test_event_flags_are_written_as_smallint_compatible_values(string $attribute): void
    {
        $event = new Event();

        $event->setAttribute($attribute, false);
        $this->assertSame(0, $event->getAttributes()[$attribute]);
        $this->assertFalse($event->getAttribute($attribute));

        $event->setAttribute($attribute, true);
        $this->assertSame(1, $event->getAttributes()[$attribute]);
        $this->assertTrue($event->getAttribute($attribute));
    }

    public function test_allow_transfers_is_persisted_by_mass_assignment(): void
    {
        $event = new Event(['allow_transfers' => true]);

        $this->assertSame(1, $event->getAttributes()['allow_transfers']);
    }
}
