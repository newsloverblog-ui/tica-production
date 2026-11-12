<?php

namespace Tests\Unit;

use Tests\TestCase;

class AccountMutationRouteBoundaryTest extends TestCase
{
    public function test_customer_account_mutations_require_customer_authentication(): void
    {
        $expected = [
            'customer.logout' => ['auth:customer'],
            'customer.change.password' => ['auth:customer', 'Deactive:customer', 'EmailStatus:customer'],
            'customer.password.update' => [
                'auth:customer',
                'Deactive:customer',
                'EmailStatus:customer',
                'throttle:authentication',
            ],
            'addto.wishlist' => ['auth:customer'],
            'remove.wishlist' => ['auth:customer'],
        ];

        $this->assertRouteMiddleware($expected);
    }

    public function test_organizer_account_mutations_require_organizer_authentication(): void
    {
        $expected = [
            'organizer.logout' => ['auth:organizer'],
            'organizer.change.password' => ['auth:organizer', 'Deactive:organizer', 'EmailStatus:organizer'],
            'organizer.update_password' => [
                'auth:organizer',
                'Deactive:organizer',
                'EmailStatus:organizer',
                'throttle:authentication',
            ],
        ];

        $this->assertRouteMiddleware($expected);
    }

    private function assertRouteMiddleware(array $expected): void
    {
        foreach ($expected as $name => $middleware) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route {$name}");
            foreach ($middleware as $item) {
                $this->assertContains($item, $route->gatherMiddleware(), "{$name} is missing {$item}");
            }
        }
    }
}
