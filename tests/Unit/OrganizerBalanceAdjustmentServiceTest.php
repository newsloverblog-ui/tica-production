<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Services\OrganizerBalanceAdjustmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizerBalanceAdjustmentServiceTest extends TestCase
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

    public function test_credit_and_debit_create_unique_audited_ledger_rows(): void
    {
        $organizer = $this->organizer(1000);
        $service = new OrganizerBalanceAdjustmentService();

        [, $credit] = $service->adjust($organizer->id, 'credit', 500, 'Correct missed settlement.', 7, '₦', 'left');
        [, $debit] = $service->adjust($organizer->id, 'debit', 200, 'Reverse duplicate credit.', 7, '₦', 'left');

        $this->assertSame(1300.0, (float) $organizer->fresh()->amount);
        $this->assertSame(4, (int) $credit->transcation_type);
        $this->assertSame(5, (int) $debit->transcation_type);
        $this->assertNotSame($credit->transcation_id, $debit->transcation_id);
        $this->assertSame(1000.0, (float) $credit->pre_balance);
        $this->assertSame(1500.0, (float) $credit->after_balance);
        $this->assertSame(1500.0, (float) $debit->pre_balance);
        $this->assertSame(1300.0, (float) $debit->after_balance);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'organizer.balance_adjusted')->count());
    }

    public function test_debit_cannot_make_balance_negative_and_creates_no_partial_records(): void
    {
        $organizer = $this->organizer(1000);

        try {
            (new OrganizerBalanceAdjustmentService())->adjust(
                $organizer->id, 'debit', 1000.01, 'Invalid debit.', 7, '₦', 'left'
            );
            $this->fail('Overdraft must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame(1000.0, (float) $organizer->fresh()->amount);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reason_is_mandatory_for_every_adjustment(): void
    {
        $organizer = $this->organizer(1000);
        $this->expectException(ValidationException::class);
        (new OrganizerBalanceAdjustmentService())->adjust(
            $organizer->id, 'credit', 100, ' ', 7, '₦', 'left'
        );
    }

    private function organizer(float $amount): Organizer
    {
        return Organizer::create([
            'email' => uniqid('balance-', true) . '@example.com',
            'username' => uniqid('balance-', true),
            'password' => 'not-used',
            'status' => 1,
            'amount' => $amount,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->string('transcation_id')->unique(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->default(0);
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
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
