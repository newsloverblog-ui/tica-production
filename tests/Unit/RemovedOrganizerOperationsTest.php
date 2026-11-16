<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RemovedOrganizerOperationsTest extends TestCase
{
    public function test_door_sales_sponsorships_and_plans_are_gone_from_the_codebase(): void
    {
        foreach ([
            'app/Http/Controllers/BackEnd/Organizer/OrganizerOperationsController.php',
            'app/Services/OrganizerDoorSaleService.php',
            'app/Models/TicaSponsorshipPackage.php',
            'app/Models/TicaOrganizerSubscription.php',
            'config/tica_organizer_plans.php',
            'resources/views/organizer/operations',
            'public/assets/tica-ui/css/door-sales.css',
            'public/assets/tica-ui/css/organizer-operations.css',
        ] as $path) {
            $this->assertFileDoesNotExist(base_path($path), $path);
        }

        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('organizer/operations', $route->uri(), $route->uri());
            $this->assertStringNotContainsString('organizer.operations.', (string) $route->getName());
        }
    }
}
