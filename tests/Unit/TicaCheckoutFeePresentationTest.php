<?php

namespace Tests\Unit;

use Tests\TestCase;

class TicaCheckoutFeePresentationTest extends TestCase
{
    public function test_booking_fee_is_shown_once_in_the_order_summary(): void
    {
        $script = (string) file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringNotContainsString("naira(feeFor(p,1))+' fee", $script);
        $this->assertStringContainsString('>Booking fee</span>', $script);
    }
}
