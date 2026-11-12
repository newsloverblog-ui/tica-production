<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->string('variation_name')->nullable();
            $table->string('selection_key', 64)->unique();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->text('name');
            $table->text('email');
            $table->string('email_hash', 64)->index();
            $table->string('status', 32)->default('active')->index();
            $table->string('consent_version', 32);
            $table->timestamp('consented_at');
            $table->timestamp('joined_at');
            $table->timestamp('notification_pending_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('notification_expires_at')->nullable()->index();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(
                ['ticket_id', 'status', 'joined_at'],
                'event_waitlist_ticket_status_joined_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_waitlist_entries');
    }
};
