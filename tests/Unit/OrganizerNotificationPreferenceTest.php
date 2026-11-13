<?php

namespace Tests\Unit;

use App\Models\Organizer;
use Tests\TestCase;

class OrganizerNotificationPreferenceTest extends TestCase
{
    public function test_boolean_preferences_are_read_with_safe_defaults(): void
    {
        $organizer = new Organizer();
        $organizer->preferences = json_encode([
            'notify_sale' => true,
            'daily_summary' => false,
            'legacy_false' => 'false',
            'legacy_true' => '1',
        ]);

        $this->assertTrue($organizer->preferenceEnabled('notify_sale'));
        $this->assertFalse($organizer->preferenceEnabled('daily_summary', true));
        $this->assertFalse($organizer->preferenceEnabled('legacy_false', true));
        $this->assertTrue($organizer->preferenceEnabled('legacy_true'));
        $this->assertTrue($organizer->preferenceEnabled('missing', true));
        $this->assertFalse($organizer->preferenceEnabled('missing'));
    }

    public function test_malformed_preferences_fall_back_without_enabling_mail(): void
    {
        $organizer = new Organizer();
        $organizer->preferences = '{not-json';

        $this->assertFalse($organizer->preferenceEnabled('notify_sale'));
        $this->assertTrue($organizer->preferenceEnabled('daily_summary', true));
    }
}
