<?php

namespace Tests\Unit;

use Tests\TestCase;

class CheckoutGatewayOfferingBoundaryTest extends TestCase
{
    public function test_event_checkout_filters_every_active_gateway_through_the_policy(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/FrontEnd/CheckOutController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('canOfferOnline((string) $gateway->keyword)', $source);
        $this->assertStringNotContainsString("environment('production')", $source);
    }

    public function test_shop_checkout_filters_every_active_gateway_through_the_policy(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/FrontEnd/Shop/ShopController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('canOfferShopOnline((string) $gateway->keyword)', $source);
        $this->assertStringNotContainsString("environment('production')", $source);
    }
}
