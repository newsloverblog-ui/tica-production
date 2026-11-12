<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Models\Earning;
use App\Services\EventSaleAccountingService;
use App\Services\TransactionLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use stdClass;
use Tests\TestCase;

class TransactionLedgerServiceTest extends TestCase
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
        $this->createSchema();
    }

    public function test_same_gateway_reference_is_recorded_once(): void
    {
        $organizer = $this->organizer(1000);
        $booking = $this->booking(81, $organizer->id, 25000);
        $service = new TransactionLedgerService();

        $first = $service->recordGatewaySale($booking, 'Paystack', 'pay_ref_81');
        $second = $service->recordGatewaySale($booking, 'paystack', 'pay_ref_81');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('paystack', $first->gateway);
        $this->assertSame('pay_ref_81', $first->gateway_reference);
        $this->assertSame(1000.0, (float) $first->pre_balance);
        $this->assertSame(24500.0, (float) $first->after_balance);
    }

    public function test_reference_reuse_for_a_different_sale_fails_closed(): void
    {
        $organizer = $this->organizer(1000);
        $service = new TransactionLedgerService();
        $service->recordGatewaySale($this->booking(81, $organizer->id, 25000), 'paystack', 'pay_ref_81');

        $this->expectException(LogicException::class);
        $service->recordGatewaySale($this->booking(82, $organizer->id, 25000), 'paystack', 'pay_ref_81');
    }

    public function test_reference_replay_with_changed_fee_or_buyer_evidence_fails_closed(): void
    {
        $organizer = $this->organizer(1000);
        $service = new TransactionLedgerService();
        $booking = $this->booking(81, $organizer->id, 25000);
        $service->recordGatewaySale($booking, 'paystack', 'pay_ref_evidence');
        $booking->commission = 1400;
        $booking->customer_id = 99;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('different ledger sale');
        $service->recordGatewaySale($booking, 'paystack', 'pay_ref_evidence');
    }

    public function test_same_reference_from_different_gateways_has_distinct_identity(): void
    {
        $organizer = $this->organizer(1000);
        $service = new TransactionLedgerService();
        $booking = $this->booking(81, $organizer->id, 25000);

        $service->recordGatewaySale($booking, 'paystack', 'shared-ref');
        $service->recordGatewaySale($booking, 'flutterwave', 'shared-ref');

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_sale_accounting_moves_platform_and_organizer_balances_exactly_once(): void
    {
        $organizer = $this->organizer(1000);
        Earning::create(['total_revenue' => 100, 'total_earning' => 50]);
        $booking = $this->booking(81, $organizer->id, 25000);
        $service = new EventSaleAccountingService();

        $first = $service->apply($booking, 'Paystack', 'pay_ref_accounting');
        $second = $service->apply($booking, 'paystack', 'pay_ref_accounting');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(24500.0, (float) $organizer->fresh()->amount);
        $this->assertSame(25350.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertSame(1800.0, (float) Earning::firstOrFail()->total_earning);
        $this->assertSame(1000.0, (float) $first->pre_balance);
        $this->assertSame(24500.0, (float) $first->after_balance);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'event_sale.accounting_applied')->count());
    }

    public function test_conflicting_sale_identity_rolls_back_all_accounting_state(): void
    {
        $organizer = $this->organizer(1000);
        Earning::create(['total_revenue' => 0, 'total_earning' => 0]);
        $service = new EventSaleAccountingService();
        $service->apply($this->booking(81, $organizer->id, 25000), 'paystack', 'fixed-reference');

        try {
            $service->apply($this->booking(82, $organizer->id, 30000), 'paystack', 'fixed-reference');
            $this->fail('A conflicting gateway identity must fail closed.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('different ledger sale', $exception->getMessage());
        }

        $this->assertSame(24500.0, (float) $organizer->fresh()->amount);
        $this->assertSame(25250.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertSame(1750.0, (float) Earning::firstOrFail()->total_earning);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'event_sale.accounting_applied')->count());
    }

    private function organizer(float $amount): Organizer
    {
        return Organizer::create([
            'email' => uniqid('ledger-', true) . '@example.com',
            'username' => uniqid('ledger-', true),
            'password' => 'not-used',
            'status' => 1,
            'amount' => $amount,
        ]);
    }

    private function booking(int $id, int $organizerId, float $price): object
    {
        return (object) [
            'id' => $id,
            'customer_id' => 41,
            'organizer_id' => $organizerId,
            'paymentStatus' => 1,
            'paymentMethod' => 'Paystack',
            'price' => $price,
            'tax' => 250,
            'commission' => 1500,
            'gatewayType' => 'online',
            'currencySymbol' => '₦',
            'currencySymbolPosition' => 'left',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 191)->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->nullable();
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('earnings', function (Blueprint $table) {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
