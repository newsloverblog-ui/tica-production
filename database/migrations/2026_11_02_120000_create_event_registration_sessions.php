<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('status', 24)->default('published')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'starts_at'], 'registration_session_agenda');
        });

        Schema::create('event_registration_session_selections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('attendee_id')->index();
            $table->timestamp('selected_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'attendee_id'], 'registration_session_attendee_unique');
            $table->index(['session_id', 'cancelled_at'], 'registration_session_capacity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_session_selections');
        Schema::dropIfExists('event_registration_sessions');
    }
};
