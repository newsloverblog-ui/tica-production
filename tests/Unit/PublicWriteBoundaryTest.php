<?php

namespace Tests\Unit;

use Tests\TestCase;

class PublicWriteBoundaryTest extends TestCase
{
    public function test_every_browser_mutation_has_an_authenticity_or_abuse_boundary(): void
    {
        $frameworkRoutes = [
            'ignition.executeSolution',
            'ignition.updateConfig',
        ];
        $unbounded = [];

        foreach (app('router')->getRoutes() as $route) {
            if (!array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
                continue;
            }
            if (in_array($route->getName(), $frameworkRoutes, true)) {
                continue;
            }

            $hasBoundary = collect($route->gatherMiddleware())->contains(
                fn ($middleware) => is_string($middleware) && (
                    str_starts_with($middleware, 'auth:')
                    || str_starts_with($middleware, 'signed')
                    || str_starts_with($middleware, 'throttle:')
                    || str_starts_with($middleware, 'payment.gateway:')
                    || str_starts_with($middleware, 'webhook.receipt:')
                    || $middleware === 'scanner.access'
                )
            );

            if (!$hasBoundary) {
                $unbounded[] = ($route->getName() ?: '(unnamed)') . ' [' . $route->uri() . ']';
            }
        }

        $this->assertSame([], $unbounded, 'Unbounded browser mutations: ' . implode(', ', $unbounded));
    }

    public function test_public_forms_and_transactional_writes_are_rate_limited(): void
    {
        $expected = [
            'store_subscriber' => 'throttle:public-form',
            'organizer.contact.send_mail' => 'throttle:public-form',
            'contact.send_mail' => 'throttle:public-form',
            'push-notification.store-endpoint' => 'throttle:30,1',
            'check-out2' => 'throttle:checkout',
            'apply-coupon' => 'throttle:checkout',
            'shop.apply-coupon' => 'throttle:checkout',
            'shop.buy' => 'throttle:checkout',
            'advertisement.count-view' => 'throttle:120,1',
            'admin.auth' => 'throttle:authentication',
            'add.cart' => 'throttle:60,1',
            'add.cart2' => 'throttle:60,1',
            'order-now' => 'throttle:60,1',
            'cart.item.remove' => 'throttle:60,1',
            'cart.update' => 'throttle:60,1',
        ];

        foreach ($expected as $name => $middleware) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains(
                $middleware,
                $route->gatherMiddleware(),
                "{$name} is missing its abuse-control boundary"
            );
        }
    }

    public function test_product_reviews_require_an_active_verified_customer_and_are_rate_limited(): void
    {
        $route = app('router')->getRoutes()->getByName('product.review.submit');

        $this->assertNotNull($route);
        $this->assertContains('auth:customer', $route->gatherMiddleware());
        $this->assertContains('Deactive:customer', $route->gatherMiddleware());
        $this->assertContains('EmailStatus:customer', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
        $this->assertSame(['POST'], $route->methods());
    }

    public function test_advertisement_counter_accepts_only_numeric_identifiers(): void
    {
        $route = app('router')->getRoutes()->getByName('advertisement.count-view');

        $this->assertNotNull($route);
        $this->assertSame('[0-9]+', $route->wheres['id'] ?? null);
        $this->assertSame(['POST'], $route->methods());
    }
}
