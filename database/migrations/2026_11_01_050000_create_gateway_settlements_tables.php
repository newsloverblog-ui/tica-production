<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gateway_settlements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('gateway', 40);
            $table->string('gateway_settlement_id', 191);
            $table->string('status', 32);
            $table->string('currency', 8)->nullable();
            $table->decimal('total_processed', 18, 2)->default(0);
            $table->decimal('total_fees', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('effective_amount', 18, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->string('reconciliation_status', 32)->default('pending');
            $table->longText('gateway_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('settlement_date')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_settlement_id']);
            $table->index(['reconciliation_status', 'settlement_date']);
        });

        Schema::create('gateway_settlement_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('gateway_settlement_id');
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('reference', 191);
            $table->string('result', 40);
            $table->decimal('expected_amount', 18, 2)->nullable();
            $table->decimal('gateway_amount', 18, 2);
            $table->decimal('gateway_fee', 18, 2)->default(0);
            $table->string('expected_currency', 8)->nullable();
            $table->string('gateway_currency', 8)->nullable();
            $table->string('local_status', 32)->nullable();
            $table->string('gateway_status', 32)->nullable();
            $table->longText('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(
                ['gateway_settlement_id', 'reference'],
                'settlement_entries_settlement_reference_unique'
            );
            $table->index(['result', 'checked_at']);
            $table->index('payment_intent_id');
            $table->index('booking_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gateway_settlement_entries');
        Schema::dropIfExists('gateway_settlements');
    }
};
