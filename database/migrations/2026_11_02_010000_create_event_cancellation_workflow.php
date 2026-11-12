<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('lifecycle_status', 32)->default('scheduled')->after('status')->index();
            $table->timestamp('sales_closed_at')->nullable()->after('lifecycle_status');
            $table->timestamp('cancelled_at')->nullable()->after('sales_closed_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });

        Schema::create('event_cancellation_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->nullable()->index();
            $table->unsignedBigInteger('requested_by_organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->string('status', 32)->default('approval_pending')->index();
            $table->boolean('event_status_before_request')->default(true);
            $table->text('reason');
            $table->text('customer_message')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('eligible_refund_count')->default(0);
            $table->decimal('eligible_refund_amount', 18, 2)->default(0);
            $table->unsignedInteger('queued_refund_count')->default(0);
            $table->decimal('queued_refund_amount', 18, 2)->default(0);
            $table->unsignedInteger('manual_review_count')->default(0);
            $table->decimal('manual_review_amount', 18, 2)->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'event_cancellation_event_status_index');
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_cancellation_request_id')
                ->nullable()
                ->after('payment_intent_id')
                ->index('payment_refunds_cancellation_index');
        });

        Schema::create('event_cancellation_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_cancellation_request_id')
                ->index('event_cancel_notice_request_index');
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(
                ['event_cancellation_request_id', 'booking_id'],
                'event_cancellation_notification_unique'
            );
        });

        Schema::create('event_cancellation_cases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_cancellation_request_id')->index();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('payment_refund_id')->nullable();
            $table->string('status', 32)->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(
                ['event_cancellation_request_id', 'booking_id'],
                'event_cancellation_case_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_cancellation_cases');
        Schema::dropIfExists('event_cancellation_notifications');
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropIndex('payment_refunds_cancellation_index');
            $table->dropColumn('event_cancellation_request_id');
        });
        Schema::dropIfExists('event_cancellation_requests');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['lifecycle_status']);
            $table->dropColumn(['lifecycle_status', 'sales_closed_at', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
