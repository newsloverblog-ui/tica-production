<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminLoginPresentationTest extends TestCase
{
    public function test_admin_login_has_no_redundant_credential_instruction(): void
    {
        $login = file_get_contents(resource_path('views/backend/login.blade.php'));
        $styles = file_get_contents(public_path('assets/tica-ui/css/admin-auth.css'));

        $this->assertStringNotContainsString('Use the admin username and password you were given.', $login);
        $this->assertStringContainsString('.taa-card h1+form{margin-top:24px}', $styles);
    }
}
