<?php

namespace Tests\Unit;

use Tests\TestCase;

class TicaCheckoutClientFlowTest extends TestCase
{
    public function test_paid_checkout_goes_from_attendee_details_directly_to_the_gateway(): void
    {
        $client = (string) file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString('Continue to secure payment', $client);
        $this->assertStringContainsString("'Opening '+gwLabel()+'…'", $client);
        $this->assertStringContainsString('place(function(){submit.disabled=false;submit.textContent=label}', $client);

        $this->assertStringNotContainsString('Review your order', $client);
        $this->assertStringNotContainsString('paymodal', $client);
        $this->assertStringNotContainsString('Payment cancelled. Your details are saved', $client);
    }
}
