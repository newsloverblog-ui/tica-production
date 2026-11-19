<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecurityRemediationBoundaryTest extends TestCase
{
    public function test_legacy_event_date_update_is_scoped_to_the_owned_event(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Organizer/EventController.php'));
        $request = file_get_contents(app_path('Http/Requests/Event/UpdateRequest.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString("->where('event_id', \$event->id)", $controller);
        $this->assertStringContainsString('->lockForUpdate()', $controller);
        $this->assertStringContainsString('->firstOrFail()', $controller);
        $this->assertStringContainsString("\$ruleArray['date_ids.*'] = ['nullable', 'integer', 'distinct'];", $request);
    }

    public function test_support_downloads_are_authorized_routes_not_public_asset_links(): void
    {
        $routes = [
            'customer.support_ticket.attachment',
            'customer.support_ticket.reply_attachment',
            'organizer.support_ticket.attachment',
            'organizer.support_ticket.reply_attachment',
            'admin.support_ticket.attachment',
            'admin.support_ticket.reply_attachment',
        ];

        foreach ($routes as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing secure support download route {$name}");
            $this->assertContains('throttle:6,1', $route->gatherMiddleware());
        }

        foreach ([
            resource_path('views/frontend/customer/dashboard/support_ticket/messages.blade.php'),
            resource_path('views/organizer/support_ticket/messages.blade.php'),
            resource_path('views/backend/support_ticket/messages.blade.php'),
        ] as $view) {
            $source = file_get_contents($view);
            $this->assertIsString($source);
            $this->assertStringNotContainsString("asset('assets/admin/img/support-ticket/", $source);
        }

        $publicAssets = file_get_contents(app_path('Http/Controllers/FrontEnd/PublicAssetController.php'));
        $this->assertStringContainsString("str_starts_with(\$path, 'admin/img/support-ticket/')", $publicAssets);
    }

    public function test_support_request_fields_are_explicitly_mapped(): void
    {
        foreach ([
            app_path('Http/Controllers/FrontEnd/SupportTicketController.php'),
            app_path('Http/Controllers/BackEnd/Organizer/SupportTicketController.php'),
            app_path('Http/Controllers/BackEnd/SupportTicketController.php'),
        ] as $controller) {
            $source = file_get_contents($controller);
            $this->assertIsString($source);
            $this->assertStringNotContainsString('$in = $request->all();', $source);
            $this->assertStringNotContainsString('$input = $request->all();', $source);
        }
    }

    public function test_csp_is_enforced_without_unsafe_eval(): void
    {
        $policy = (string) config('security.content_security_policy');

        $this->assertTrue((bool) config('security.enforce_content_security_policy'));
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertSame([
            'admin.withdraw_payment_method.mange_input',
            'admin.withdraw_payment_method.edit_input',
            'organizer.event.add.ticket',
        ], config('security.legacy_vue_compiler_routes'));
    }

    public function test_database_update_control_plane_is_not_web_accessible(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName('system.database.show'));
        $this->assertNull(app('router')->getRoutes()->getByName('system.database.apply'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/SystemUpdateController.php'));

        $workflow = file_get_contents(base_path('.github/workflows/cpanel.yml'));
        $this->assertStringNotContainsString('DB_UPDATE_TOKEN', $workflow);
        $this->assertStringContainsString('php artisan migrate --force', $workflow);
        $this->assertStringContainsString('/system/update)" = 404', $workflow);
    }

    public function test_legacy_support_attachments_have_a_private_migration_command(): void
    {
        $command = file_get_contents(app_path('Console/Commands/SecureLegacySupportAttachments.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('support:secure-attachments', $command);
        $this->assertStringContainsString("'missing' => 0", $command);
        $this->assertStringContainsString('return self::FAILURE;', $command);
    }
}
