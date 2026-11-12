<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_badge_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id')->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->string('badge_title', 100)->nullable();
            $table->string('company_question_key', 80)->nullable();
            $table->string('role_question_key', 80)->nullable();
            $table->string('accent_color', 7)->default('#FE9B59');
            $table->string('paper_size', 16)->default('a6');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_badge_settings');
    }
};
