<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->string('kyc_status', 24)->default('unverified')->index();
            $table->string('verified_legal_name')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
        });

        Schema::create('organizer_kyc_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('organizer_id');
            $table->string('entity_type', 24);
            $table->string('legal_name');
            $table->string('business_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('id_type', 40);
            $table->string('status', 24)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('organizer_kyc_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('submission_id');
            $table->string('document_type', 40);
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->index(['submission_id', 'document_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('organizer_kyc_documents');
        Schema::dropIfExists('organizer_kyc_submissions');
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropIndex(['kyc_status']);
            $table->dropColumn(['kyc_status', 'verified_legal_name', 'kyc_verified_at']);
        });
    }
};
