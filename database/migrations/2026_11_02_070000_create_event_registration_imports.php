<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->string('status', 32)->default('queued')->index();
            $table->string('disk', 64);
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('attestation_version', 64);
            $table->timestamp('attested_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('source_deleted_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['organizer_id', 'event_id', 'created_at'], 'registration_import_owner_event');
        });

        Schema::create('event_registration_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->index();
            $table->unsignedInteger('row_number');
            $table->string('registration_key', 120);
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('submission_id')->nullable()->index();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->char('row_fingerprint', 64);
            $table->timestamps();
            $table->unique(['import_id', 'row_number']);
            $table->index(['import_id', 'registration_key'], 'registration_import_group');
        });

        Schema::table('event_registration_submissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_import_id')->nullable()->after('source_ip_hash')->index();
            $table->string('source_import_key', 120)->nullable()->after('source_import_id');
            $table->unique(['source_import_id', 'source_import_key'], 'registration_submission_import_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_submissions', function (Blueprint $table): void {
            $table->dropUnique('registration_submission_import_unique');
            $table->dropColumn(['source_import_id', 'source_import_key']);
        });
        Schema::dropIfExists('event_registration_import_rows');
        Schema::dropIfExists('event_registration_imports');
    }
};
