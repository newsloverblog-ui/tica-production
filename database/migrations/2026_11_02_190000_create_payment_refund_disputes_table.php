<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refund_disputes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('payment_intent_id')->index();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('organizer_id')->nullable()->index();
            $table->unsignedBigInteger('requested_by_organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->unsignedBigInteger('payment_refund_id')->nullable()->unique();
            $table->string('category', 40);
            $table->text('description');
            $table->string('status', 24)->default('open');
            $table->decimal('requested_amount', 18, 2);
            $table->string('currency', 10);
            $table->unsignedInteger('checked_in_ticket_count')->default(0);
            $table->text('resolution')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
            $table->index(['organizer_id', 'status', 'created_at']);
            $table->index(['payment_intent_id', 'status']);
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('payment_refund_dispute_id')
                ->nullable()
                ->after('event_cancellation_request_id')
                ->unique('payment_refunds_dispute_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropUnique('payment_refunds_dispute_unique');
            $table->dropColumn('payment_refund_dispute_id');
        });
        Schema::dropIfExists('payment_refund_disputes');
    }
};
