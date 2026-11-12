<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('kind', 24);
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'kind'], 'registration_notification_unique');
            $table->index(['status', 'processing_at'], 'registration_notification_monitor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_notifications');
    }
};
