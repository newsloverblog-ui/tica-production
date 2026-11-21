<?php

namespace Tests\Unit;

use App\Services\CheckoutRegistrationService;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class CheckoutRegistrationFormTest extends TestCase
{
    public function test_ticket_order_expands_each_selection_by_quantity(): void
    {
        $service = app(CheckoutRegistrationService::class);

        $this->assertSame([4, 4, 9], $service->ticketOrder([
            ['ticket_id' => 4, 'qty' => 2],
            ['ticket_id' => 9, 'qty' => 1],
            ['ticket_id' => 7, 'qty' => 0],
        ], 1));
    }

    public function test_checkout_checks_answers_before_payment_and_saves_them_with_the_booking(): void
    {
        $api = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaApiController.php'));
        $this->assertStringContainsString("'attendees.*.answers'", $api);
        $this->assertStringContainsString('CheckoutRegistrationService::class', $api);
        $this->assertLessThan(
            strpos($api, "dispatchTo('ticket.booking'"),
            strpos($api, '$registration->check('),
            'Answers must be checked before the booking and payment start.'
        );

        $booking = file_get_contents(app_path('Http/Controllers/FrontEnd/Event/BookingController.php'));
        $this->assertStringContainsString('CheckoutRegistrationService::class)->record($booking', $booking);

        $flutterwave = file_get_contents(app_path('Http/Controllers/FrontEnd/PaymentGateway/FlutterwaveController.php'));
        $this->assertStringContainsString("'attendees' => Session::get('tica_attendees')", $flutterwave);
    }

    public function test_registration_form_is_its_own_step_after_checkout_details(): void
    {
        $js = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $this->assertStringContainsString("'Registration form','Pay'", $js);
        $this->assertStringContainsString('if(nxt===-1&&RF){toForm();return}', $js);
        $this->assertStringContainsString("'Person '+(i+1)+' of '+n", $js);
        $this->assertStringContainsString('function qsFor(i)', $js);
        $this->assertStringContainsString('consent:RF?!!S.consent:null', $js);
    }
}
