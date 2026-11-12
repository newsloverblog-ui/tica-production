<?php

namespace Tests\Unit;

use App\Console\Commands\ReconcilePaystackSettlements;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use ReflectionProperty;
use Tests\TestCase;

class PaystackSettlementCommandIsolationTest extends TestCase
{
    public function test_settlement_reconciliation_is_isolated_by_default_and_overlap_fails(): void
    {
        $command = new ReconcilePaystackSettlements();

        $this->assertInstanceOf(Isolatable::class, $command);
        $this->assertTrue((new ReflectionProperty($command, 'isolated'))->getValue($command));
        $this->assertSame(
            Command::FAILURE,
            (new ReflectionProperty($command, 'isolatedExitCode'))->getValue($command)
        );
        $this->assertTrue($command->isolationLockExpiresAt()->greaterThan(now()->addHours(5)));
    }
}
