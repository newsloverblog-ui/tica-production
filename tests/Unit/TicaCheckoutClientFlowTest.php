<?php

namespace Tests\Unit;

use Tests\TestCase;

class TicaCheckoutClientFlowTest extends TestCase
{
    public function test_checkout_controller_resolves_the_purchase_limit_service_from_services_namespace(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/FrontEnd/TicaApiController.php'));

        $this->assertStringContainsString(
            'use App\\Services\\EventTicketPurchaseLimitService;',
            $controller
        );
        $this->assertInstanceOf(
            \App\Services\EventTicketPurchaseLimitService::class,
            app(\App\Services\EventTicketPurchaseLimitService::class)
        );
    }

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

    public function test_reserved_seat_checkout_creates_its_ticket_hold_before_reserving_seats(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/FrontEnd/TicaApiController.php'));

        $clearPreviousHold = strpos($controller, "Session::pull('ticket_reservation_token', '')");
        $releasePreviousHold = strpos($controller, "'checkout_replaced'");
        $createHold = strpos($controller, '$token = app(TicketReservationService::class)->reserve(');
        $saveHold = strpos($controller, 'Session::put(\'ticket_reservation_token\', $token);');
        $reserveSeats = strpos($controller, 'app(SeatReservationService::class)->reserve(');

        $this->assertNotFalse($clearPreviousHold);
        $this->assertNotFalse($releasePreviousHold);
        $this->assertNotFalse($createHold);
        $this->assertNotFalse($saveHold);
        $this->assertNotFalse($reserveSeats);
        $this->assertLessThan($createHold, $clearPreviousHold);
        $this->assertLessThan($createHold, $releasePreviousHold);
        $this->assertLessThan($reserveSeats, $createHold);
        $this->assertLessThan($reserveSeats, $saveHold);
        $this->assertStringContainsString("'quote_validation_failed'", $controller);
        $this->assertStringNotContainsString(
            'The ticket hold expired before the seats could be reserved.',
            $controller
        );
    }
}
