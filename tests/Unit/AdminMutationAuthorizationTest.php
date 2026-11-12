<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminMutationAuthorizationTest extends TestCase
{
    public function test_every_authenticated_admin_mutation_has_a_functional_permission_boundary(): void
    {
        $selfServiceAllowlist = [
            'admin.auth',
            'admin.mail_for_forget_password',
            'admin.update_forgotten_password',
            'admin.change_theme',
            'admin.update_profile',
            'admin.update_password',
            'admin.logout',
        ];
        $unprotected = [];

        foreach (app('router')->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'admin/')) {
                continue;
            }
            if (!array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
                continue;
            }
            if (in_array($route->getName(), $selfServiceAllowlist, true)) {
                continue;
            }

            $hasPermission = collect($route->gatherMiddleware())->contains(
                fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:')
            );
            if (!$hasPermission) {
                $unprotected[] = ($route->getName() ?: '(unnamed)') . ' [' . $route->uri() . ']';
            }
        }

        $this->assertSame([], $unprotected, 'Admin mutations without a permission boundary: ' . implode(', ', $unprotected));
    }

    public function test_sensitive_route_groups_use_the_expected_permission(): void
    {
        $expected = [
            'admin.customer_management.bulk_delete_customer' => 'permission:Customer Management',
            'admin.product.setting.update' => 'permission:Shop Management',
            'admin.update_admin_keywords' => 'permission:Language Management',
            'admin.user_management.subscriber.delete' => 'permission:Subscribers',
            'admin.user_management.bulk_delete_subscriber' => 'permission:Subscribers',
            'admin.user_management.subscribers.send_email' => 'permission:Subscribers',
            'admin.user_management.push_notification.update_settings' => 'permission:Push Notification',
            'admin.user_management.push_notification.send_notification' => 'permission:Push Notification',
            'admin.event_booking.invoice' => 'permission:Event Bookings',
            'admin.event_booking.attachment' => 'permission:Event Bookings',
        ];

        foreach ($expected as $name => $middleware) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains($middleware, $route->gatherMiddleware(), "{$name} has the wrong permission boundary");
        }

        foreach (['admin.summernote.upload', 'admin.summernote.remove'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertTrue(collect($route->gatherMiddleware())->contains(
                fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:')
                    && str_contains($middleware, 'Blog Management') && str_contains($middleware, 'Event Management')
            ));
            $this->assertContains('throttle:60,1', $route->gatherMiddleware());
        }
    }

    public function test_money_moving_manual_actions_require_domain_and_transaction_permissions(): void
    {
        $expected = [
            'admin.event_booking.update_payment_status' => ['permission:Event Bookings', 'permission:Transaction'],
            'admin.order.update_payment_status' => ['permission:Shop Management', 'permission:Transaction'],
            'admin.product_order.refunds.store' => ['permission:Shop Management', 'permission:Transaction'],
            'admin.event_cancellations.approve' => ['permission:Event Management', 'permission:Transaction'],
            'admin.event_cancellations.reject' => ['permission:Event Management', 'permission:Transaction'],
            'admin.event_cancellation_cases.dispute' => ['permission:Event Management', 'permission:Transaction'],
            'admin.event_cancellation_cases.resolve' => ['permission:Event Management', 'permission:Transaction'],
        ];

        foreach ($expected as $name => $middleware) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            foreach ($middleware as $boundary) {
                $this->assertContains($boundary, $route->gatherMiddleware(), "{$name} is missing {$boundary}");
            }
            $this->assertSame(['POST'], $route->methods(), "{$name} must remain a POST-only mutation");
        }
    }

    public function test_cancellation_review_ui_hides_finance_actions_without_transaction_permission(): void
    {
        $view = file_get_contents(resource_path('views/backend/event/cancellations.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("hasPermission('Transaction')", $view);
        $this->assertStringContainsString('$canManageCancellationFinance', $view);
        $this->assertStringContainsString('Finance permission required', $view);
        $this->assertStringContainsString('$cancellation->status === \'approval_pending\' && $canManageCancellationFinance', $view);
        $this->assertStringContainsString('$case->status === \'manual_review\' && $canManageCancellationFinance', $view);
    }

    public function test_admin_state_changes_are_not_exposed_as_get_requests(): void
    {
        $allowedReadRoutes = ['admin.reset_password'];
        $unsafe = [];

        foreach (app('router')->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'admin/') || !in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (in_array($route->getName(), $allowedReadRoutes, true)) {
                continue;
            }
            $description = strtolower(($route->getName() ?: '') . ' ' . $route->getActionName());
            if (preg_match('/(delete|destroy|remove|update|approve|reject|decline|unassign|assign|make.default|secret.login|logout|clear)/', $description)) {
                $unsafe[] = ($route->getName() ?: '(unnamed)') . ' [' . $route->uri() . ']';
            }
        }

        $this->assertSame([], $unsafe, 'State-changing admin GET routes: ' . implode(', ', $unsafe));
        $this->assertSame(['POST'], app('router')->getRoutes()
            ->getByName('admin.support_tickets.unassign')->methods());
    }
}
