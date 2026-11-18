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

        $rows = file_get_contents(resource_path('views/organizer/forms/_fields.blade.php'));
        $client = file_get_contents(resource_path('views/organizer/forms/_motion.blade.php'));

        foreach (['data-rf-chip', 'data-rf-list', 'data-reorder=', 'data-add=', 'data-rf-position', 'class="rf-field rf-fixed"', "@include('organizer.forms._fields')", 'id="rf-edit"', 'data-rf-live'] as $hook) {
            $this->assertStringContainsString($hook, $view);
        }
        foreach (['data-rf-item', 'data-rf-edit', 'data-rf-remove', "data-q='@json(\$data)'"] as $hook) {
            $this->assertStringContainsString($hook, $rows);
        }
        // Field changes save in the background and redraw the list.
        $this->assertStringContainsString("'Accept': 'application/json'", $client);
        $this->assertStringContainsString("'ordered_questions[]'", $client);
        // The old full-page reorder form and native confirm() boxes are gone.
        $this->assertStringNotContainsString('id="rf-order"', $view);
        $this->assertStringNotContainsString('confirm(', $view . $rows);
    }

    public function test_form_pages_share_calm_motion_that_respects_reduced_motion(): void
    {
        $styles = file_get_contents(resource_path('views/organizer/forms/_styles.blade.php'));
        $motion = file_get_contents(resource_path('views/organizer/forms/_motion.blade.php'));

        foreach (['show', 'index'] as $page) {
            $view = file_get_contents(resource_path("views/organizer/forms/{$page}.blade.php"));
            $this->assertStringContainsString('<div class="rf-page">', $view);
            $this->assertStringContainsString("@include('organizer.forms._motion')", $view);
        }
        $this->assertStringContainsString('@media (prefers-reduced-motion:reduce)', $styles);
        // Cards only ease in on the first visit, not after every save.
        $this->assertStringContainsString("sessionStorage.getItem(key)", $motion);
        $this->assertStringContainsString("if (e.defaultPrevented) return;", $motion);
    }

    public function test_library_field_endpoints_answer_background_saves_with_the_redrawn_list(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Organizer/EventRegistrationController.php'));

        $this->assertStringContainsString('return $request->expectsJson() && $form->event_id === null;', $controller);
        $this->assertStringContainsString("view('organizer.forms._fields'", $controller);
        $this->assertStringContainsString("'adminMode' => Auth::guard('admin')->check()", $controller);
        $this->assertSame(5, substr_count($controller, '$this->liveFields('));
    }
}
