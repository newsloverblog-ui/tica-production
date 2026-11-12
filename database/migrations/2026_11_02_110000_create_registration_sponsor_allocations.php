<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_allocations', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->text('sponsor_name');
            $table->text('sponsor_email');
            $table->char('sponsor_email_hash', 64);
            $table->unsignedInteger('capacity');
            $table->string('status', 24)->default('active')->index();
            $table->char('access_token_hash', 64);
            $table->string('attestation_version', 32);
            $table->timestamp('attested_at');
            $table->char('attested_ip_hash', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('delivery_status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->string('anonymization_reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'sponsor_email_hash'], 'registration_allocation_sponsor_unique');
            $table->index(['event_id', 'status', 'expires_at'], 'registration_allocation_active');
        });

        Schema::create('event_registration_allocation_claims', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('allocation_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('form_id')->index();
            $table->text('name');
            $table->text('email');
            $table->char('email_hash', 64);
            $table->char('token_hash', 64);
            $table->string('source_attestation_version', 32);
            $table->timestamp('source_attested_at');
            $table->char('source_attested_ip_hash', 64)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('submission_id')->nullable()->unique();
            $table->timestamp('expires_at');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('delivery_status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->string('anonymization_reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['allocation_id', 'email_hash'], 'registration_allocation_claim_email_unique');
            $table->index(['allocation_id', 'status'], 'registration_allocation_claim_capacity');
        });

        Schema::table('event_registration_submissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_allocation_claim_id')->nullable()->unique()
                ->after('source_import_key');
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_submissions', function (Blueprint $table): void {
            $table->dropUnique(['source_allocation_claim_id']);
            $table->dropColumn('source_allocation_claim_id');
        });
        Schema::dropIfExists('event_registration_allocation_claims');
        Schema::dropIfExists('event_registration_allocations');
    }
};
