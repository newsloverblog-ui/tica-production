<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PlatformEarningMutationBoundaryTest extends TestCase
{
    public function test_application_services_cannot_mutate_earnings_outside_the_singleton_boundary(): void
    {
        $violations = [];

        foreach (File::allFiles(app_path('Services')) as $file) {
            if ($file->getFilename() === 'PlatformEarningService.php') {
                continue;
            }

            $source = File::get($file->getPathname());
            if (str_contains($source, 'App\\Models\\Earning')
                || str_contains($source, 'Earning::')
                || str_contains($source, 'new Earning')) {
                $violations[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $violations, 'Earnings access must go through PlatformEarningService: '.implode(', ', $violations));
    }

    public function test_every_authoritative_finance_mutation_locks_the_singleton(): void
    {
        $services = [
            'EventSaleAccountingService.php',
            'ManualBookingPaymentService.php',
            'ProductOrderAccountingService.php',
            'ProductOrderRefundService.php',
            'PaymentRefundService.php',
            'EventCancellationCaseService.php',
        ];

        foreach ($services as $service) {
            $source = File::get(app_path('Services/'.$service));

            $this->assertStringContainsString(
                'PlatformEarningService::class)->lock()',
                $source,
                "{$service} must lock the authoritative platform earnings row."
            );
        }
    }

    public function test_certified_gateway_controllers_do_not_write_earnings_directly(): void
    {
        $controllers = [
            app_path('Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php'),
            app_path('Http/Controllers/FrontEnd/PaymentGateway/FlutterwaveController.php'),
            app_path('Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php'),
        ];

        foreach ($controllers as $controller) {
            $source = File::get($controller);

            $this->assertStringNotContainsString('App\\Models\\Earning', $source);
            $this->assertStringNotContainsString('Earning::', $source);
            $this->assertStringNotContainsString('new Earning', $source);
        }
    }
}
