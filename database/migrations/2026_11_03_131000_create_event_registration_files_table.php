<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_files', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('answer_id')->unique();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('attendee_id')->nullable()->index();
            $table->unsignedBigInteger('question_id')->index();
            $table->string('disk', 64);
            $table->string('path', 500)->unique();
            $table->text('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->index(['submission_id', 'question_id'], 'registration_file_submission_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_files');
    }
};
