<?php

namespace Tests\Unit;

use Tests\TestCase;

class RegistrationFormEditorViewTest extends TestCase
{
    public function test_form_editor_checkboxes_use_their_own_layout_class(): void
    {
        $view = file_get_contents(resource_path('views/organizer/forms/show.blade.php'));
        $styles = file_get_contents(resource_path('views/organizer/forms/_styles.blade.php'));

        // t-check is the 18px checkbox class; on a label it squeezes the sentence into one word per line.
        $this->assertStringNotContainsString('class="t-check', $view);
        $this->assertSame(2, substr_count($view, 'class="rf-check"'));
        $this->assertStringContainsString('class="rf-check rf-check--inline"', $view);
        $this->assertStringContainsString('.rf-page .rf-check{display:flex', $styles);
        $this->assertStringContainsString('name="confirm_switch"', $view);
    }

    public function test_form_editor_keeps_drag_and_drop_hooks(): void
    {
        $view = file_get_contents(resource_path('views/organizer/forms/show.blade.php'));

        foreach (['data-rf-chip', 'data-rf-list', 'data-rf-item', 'data-rf-position', 'id="rf-order"', 'data-rf-options', 'class="rf-field rf-fixed"'] as $hook) {
            $this->assertStringContainsString($hook, $view);
        }
    }
}
