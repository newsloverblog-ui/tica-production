<?php

namespace Tests\Unit;

use Tests\TestCase;

class GroupTicketTest extends TestCase
{
    public function test_builder_offers_a_group_ticket_option_and_saves_people_per_ticket(): void
    {
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $controller = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));

        $this->assertStringContainsString('Sell as a group ticket', $client);
        $this->assertStringContainsString("'tiers['+i+'][admits]'", $client);
        $this->assertStringContainsString("'tiers.*.admits' => ['nullable', 'integer', 'between:1,100']", $controller);
        $this->assertStringContainsString('private function admitsColumn(array $tier, bool $online)', $controller);
    }

    public function test_each_person_on_a_group_ticket_gets_a_pass(): void
    {
        $issuance = file_get_contents(app_path('Services/TicketIssuanceService.php'));
        $booking = file_get_contents(app_path('Http/Controllers/FrontEnd/Event/BookingController.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString('$quantity *= $admits[', $issuance);
        $this->assertStringContainsString('public static function peopleFor(', $issuance);
        $this->assertStringContainsString('TicketIssuanceService::peopleFor(', $booking);
        // Checkout asks one form per person, and spare passes can stay with the buyer.
        $this->assertStringContainsString('function slotMeta()', $client);
        $this->assertStringContainsString('data-keep', $client);
    }

    public function test_sold_counts_tickets_not_people(): void
    {
        $prefill = file_get_contents(app_path('Services/TicaUi/EventBuilderPrefill.php'));
        $this->assertStringContainsString('ceil($people / $ticket->admitsPerTicket())', $prefill);
        $this->assertFileExists(database_path('migrations/2026_11_21_190000_add_admits_to_tickets.php'));
    }
}
