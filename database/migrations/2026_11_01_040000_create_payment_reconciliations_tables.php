<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_reconciliation_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('gateway', 40);
            $table->date('reconciliation_date');
            $table->string('status', 24)->default('running');
            $table->unsignedInteger('gateway_count')->default(0);
            $table->unsignedInteger('local_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'reconciliation_date']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('payment_reconciliation_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('gateway', 40);
            $table->string('reference', 191);
            $table->string('result', 40);
            $table->decimal('expected_amount', 18, 2)->nullable();
            $table->decimal('gateway_amount', 18, 2)->nullable();
            $table->string('expected_currency', 8)->nullable();
            $table->string('gateway_currency', 8)->nullable();
            $table->string('local_status', 32)->nullable();
            $table->string('gateway_status', 32)->nullable();
            $table->longText('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['run_id', 'reference']);
            $table->index(['result', 'checked_at']);
            $table->index('payment_intent_id');
            $table->index('booking_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_reconciliation_entries');
        Schema::dropIfExists('payment_reconciliation_runs');
    }
};
