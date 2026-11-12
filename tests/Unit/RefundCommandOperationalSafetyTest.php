<?php

namespace Tests\Unit;

use App\Console\Commands\ProcessPaymentRefunds;
use App\Console\Commands\ReconcilePaymentRefunds;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class RefundCommandOperationalSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id');
            $table->string('gateway');
            $table->string('gateway_refund_id')->nullable();
            $table->string('status');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function test_refund_submission_and_reconciliation_are_isolated_by_default(): void
    {
        foreach ([new ProcessPaymentRefunds(), new ReconcilePaymentRefunds()] as $command) {
            $this->assertInstanceOf(Isolatable::class, $command);
            $this->assertTrue((new ReflectionProperty($command, 'isolated'))->getValue($command));
            $this->assertSame(
                Command::FAILURE,
                (new ReflectionProperty($command, 'isolatedExitCode'))->getValue($command)
            );
            $this->assertTrue($command->isolationLockExpiresAt()->greaterThan(now()->addHour()));
        }
    }

    public function test_failed_refund_lookups_advance_fairly_and_store_diagnostics_encrypted(): void
    {
        $old = now()->subHour();
        foreach (range(1, 3) as $id) {
            DB::table('payment_refunds')->insert([
                'public_id' => '00000000-0000-4000-8000-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT),
                'gateway' => 'unsupported',
                'gateway_refund_id' => 'refund-'.$id,
                'status' => 'pending',
                'last_error' => null,
                'created_at' => $old,
                'updated_at' => $old,
            ]);
        }

        $this->artisan('payments:reconcile-refunds', ['--limit' => 2])->assertExitCode(1);
        $this->assertSame(2, DB::table('payment_refunds')->whereNotNull('last_error')->count());

        $this->artisan('payments:reconcile-refunds', ['--limit' => 2])->assertExitCode(1);
        $this->assertSame(3, DB::table('payment_refunds')->whereNotNull('last_error')->count());
        $this->assertStringNotContainsString(
            'Unsupported refund gateway',
            (string) DB::table('payment_refunds')->where('id', 1)->value('last_error')
        );
    }
}
