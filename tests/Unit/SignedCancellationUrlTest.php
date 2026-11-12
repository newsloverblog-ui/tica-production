<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Tests\TestCase;

class SignedCancellationUrlTest extends TestCase
{
    public function test_event_booking_cancellation_url_is_signed_and_tamper_evident(): void
    {
        $url = eventBookingCancelUrl(42);

        $this->assertTrue(Request::create($url)->hasValidSignature());
        $this->assertFalse(Request::create(str_replace('/42/', '/43/', $url))->hasValidSignature());
    }
}
