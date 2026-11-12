<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('request_type', 32)->index();
            $table->string('status', 24)->index();
            $table->text('customer_email');
            $table->text('customer_name')->nullable();
            $table->text('reason')->nullable();
            $table->string('policy_version', 120);
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->index();
            $table->text('resolution')->nullable();
            $table->string('evidence_reference', 500)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status'], 'customer_privacy_requests_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_privacy_requests');
    }
};
