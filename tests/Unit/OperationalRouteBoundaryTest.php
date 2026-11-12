<?php

namespace Tests\Unit;

use Tests\TestCase;

class OperationalRouteBoundaryTest extends TestCase
{
    public function test_scanner_metrics_require_scanner_access_and_a_verified_device(): void
    {
        $route = app('router')->getRoutes()->getByName('organizer.scanner.metrics.show');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('scanner.access', $route->gatherMiddleware());
        $this->assertContains('scanner.device', $route->gatherMiddleware());
    }

    public function test_current_ticket_document_requires_a_verified_customer_and_is_rate_limited(): void
    {
        $route = app('router')->getRoutes()->getByName('customer.booking.current-tickets');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('auth:customer', $route->gatherMiddleware());
        $this->assertContains('Deactive:customer', $route->gatherMiddleware());
        $this->assertContains('EmailStatus:customer', $route->gatherMiddleware());
        $this->assertContains('throttle:6,1', $route->gatherMiddleware());
    }
}
