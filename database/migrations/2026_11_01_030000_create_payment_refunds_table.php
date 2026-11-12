<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('payment_intent_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_admin_id');
            $table->string('gateway', 40);
            $table->string('gateway_reference', 191);
            $table->string('gateway_refund_id', 191)->nullable();
            $table->string('gateway_status', 40)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8);
            $table->text('reason');
            $table->string('status', 40)->default('requested');
            $table->longText('gateway_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('financial_applied_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_reference']);
            $table->index(['status', 'created_at']);
            $table->index('booking_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('refunded_amount', 18, 2)->default(0)->after('paymentStatus');
            $table->string('refund_status', 32)->nullable()->after('refunded_amount');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount', 'refund_status']);
        });
        Schema::dropIfExists('payment_refunds');
    }
};
