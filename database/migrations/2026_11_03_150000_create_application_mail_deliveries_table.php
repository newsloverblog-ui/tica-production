<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_mail_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('kind', 80)->index();
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('created_by_type', 32)->default('system');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->char('recipient_hash', 64);
            $table->text('recipient_email');
            $table->text('subject');
            $table->longText('body');
            $table->longText('attachments')->nullable();
            $table->text('reply_to')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['created_by_type', 'created_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_mail_deliveries');
    }
};
