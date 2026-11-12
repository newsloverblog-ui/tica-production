<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminProfileUploadValidationTest extends TestCase
{
    public function test_admin_profile_image_is_optional_and_storage_errors_are_visible(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/AdminController.php'));
        $view = file_get_contents(resource_path('views/backend/admin/edit-profile.blade.php'));

        $method = substr(
            $controller,
            strpos($controller, 'public function updateProfile'),
            strpos($controller, 'public function changePassword') - strpos($controller, 'public function updateProfile')
        );

        $this->assertStringNotContainsString("['image'] = 'required'", $method);
        $this->assertStringContainsString("if (\$request->hasFile('image'))", $method);
        $this->assertStringContainsString("\$errors->has('file')", $view);
        $this->assertStringContainsString('Optional. Use a square PNG', $view);
    }
}
