<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_guest_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->text('name');
            $table->text('email');
            $table->char('email_hash', 64);
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->string('status', 24)->default('pending')->index();
            $table->string('delivery_status', 24)->default('pending')->index();
            $table->char('token_hash', 64);
            $table->string('source_attestation_version', 32)->default('guest-invite-v1');
            $table->timestamp('source_attested_at');
            $table->string('source_attested_ip_hash', 64)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('rsvp_consent_at')->nullable();
            $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email_hash'], 'event_guest_invitation_email_unique');
            $table->index(['organizer_id', 'event_id', 'status'], 'event_guest_invitation_owner_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_guest_invitations');
    }
};
