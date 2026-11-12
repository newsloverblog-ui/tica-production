<?php

namespace Tests\Unit;

use App\Casts\PrefixedEncryptedString;
use App\Models\GatewaySettlement;
use App\Models\PaymentReconciliationRun;
use App\Models\PaymentRefund;
use App\Models\PayoutTransfer;
use App\Models\ProductGatewayRefund;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceDiagnosticFieldSecurityTest extends TestCase
{
    private const MODELS = [
        'payment_refunds' => PaymentRefund::class,
        'product_gateway_refunds' => ProductGatewayRefund::class,
        'payout_transfers' => PayoutTransfer::class,
        'gateway_settlements' => GatewaySettlement::class,
        'payment_reconciliation_runs' => PaymentReconciliationRun::class,
    ];

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

        foreach (array_keys(self::MODELS) as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->text('last_error')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    public function test_migration_and_models_encrypt_finance_provider_diagnostics(): void
    {
        foreach (array_keys(self::MODELS) as $table) {
            DB::table($table)->insert([
                'last_error' => "private provider diagnostic for {$table}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path(
            'migrations/2026_11_06_180000_encrypt_finance_diagnostic_fields.php'
        );
        $migration->up();

        foreach (self::MODELS as $table => $modelClass) {
            $raw = DB::table($table)->value('last_error');
            $this->assertStringStartsWith(PrefixedEncryptedString::PREFIX, $raw, $table);
            $this->assertStringNotContainsString('private provider diagnostic', $raw, $table);
            $this->assertSame(
                "private provider diagnostic for {$table}",
                $modelClass::query()->firstOrFail()->last_error,
                $table
            );
        }

        $refund = PaymentRefund::query()->firstOrFail();
        $refund->last_error = 'replacement private refund diagnostic';
        $refund->save();
        $this->assertStringStartsWith(
            PrefixedEncryptedString::PREFIX,
            DB::table('payment_refunds')->value('last_error')
        );

        $migration->down();
        $this->assertSame(
            'replacement private refund diagnostic',
            DB::table('payment_refunds')->value('last_error')
        );
        foreach (array_keys(self::MODELS) as $table) {
            $this->assertFalse(
                str_starts_with(
                    (string) DB::table($table)->value('last_error'),
                    PrefixedEncryptedString::PREFIX
                ),
                $table
            );
        }
    }
}
