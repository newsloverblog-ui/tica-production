<?php

namespace Tests\Unit;

use App\Console\Commands\ReconcileFlutterwavePayments;
use App\Console\Commands\ReconcilePaystackPayments;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use ReflectionProperty;
use Tests\TestCase;

class PaymentReconciliationCommandOperationalSafetyTest extends TestCase
{
    public function test_charge_reconciliation_commands_are_isolated_by_default(): void
    {
        foreach ([new ReconcilePaystackPayments(), new ReconcileFlutterwavePayments()] as $command) {
            $this->assertInstanceOf(Isolatable::class, $command);
            $this->assertTrue((new ReflectionProperty($command, 'isolated'))->getValue($command));
            $this->assertSame(
                Command::FAILURE,
                (new ReflectionProperty($command, 'isolatedExitCode'))->getValue($command)
            );
            $this->assertTrue($command->isolationLockExpiresAt()->greaterThan(now()->addHour()));
        }
    }

    public function test_charge_reconciliation_commands_reject_invalid_dates_before_provider_access(): void
    {
        $this->artisan('payments:reconcile-paystack', ['--date' => '2026-13-40'])
            ->assertExitCode(Command::INVALID);
        $this->artisan('payments:reconcile-flutterwave', ['--date' => 'not-a-date'])
            ->assertExitCode(Command::INVALID);
    }
}
