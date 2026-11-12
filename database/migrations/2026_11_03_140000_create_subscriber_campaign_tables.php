<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriber_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->string('subject', 255);
            $table->longText('body');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriber_campaign_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('subscriber_id')->nullable()->index();
            $table->char('recipient_hash', 64);
            $table->text('recipient_email');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'recipient_hash'], 'subscriber_campaign_recipient_unique');
            $table->foreign('campaign_id')->references('id')->on('subscriber_campaigns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_campaign_deliveries');
        Schema::dropIfExists('subscriber_campaigns');
    }
};
