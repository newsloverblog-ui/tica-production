<?php

namespace Tests\Unit;

use Tests\TestCase;

class RegistrationFormPreviewTest extends TestCase
{
    public function test_editor_has_preview_button_and_dialog(): void
    {
        $show = file_get_contents(resource_path('views/organizer/forms/show.blade.php'));
        $this->assertStringContainsString('data-rf-preview-open', $show);
        $this->assertStringContainsString('id="rf-preview"', $show);
        $this->assertStringContainsString('data-rf-pv-size="phone"', $show);
        $this->assertStringContainsString('data-rf-pv-page', $show);
    }

    public function test_preview_builds_the_form_safely_from_the_fields_on_screen(): void
    {
        $js = file_get_contents(resource_path('views/organizer/forms/_motion.blade.php'));
        $start = strpos($js, '// Preview:');
        $this->assertNotFalse($start);
        $preview = substr($js, $start, strpos($js, '// Every other form posts normally') - $start);

        $this->assertStringContainsString('[data-rf-list] [data-rf-item]', $preview);
        $this->assertStringContainsString('Primary contact', $preview);
        $this->assertStringContainsString('Attendee 1', $preview);
        $this->assertStringContainsString('applyConditions', $preview);
        $this->assertStringContainsString("preventDefault", $preview);
        // Field labels come from organisers: text only, never HTML.
        $this->assertStringNotContainsString('innerHTML', $preview);
    }
}
