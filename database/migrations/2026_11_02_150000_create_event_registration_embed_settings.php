<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_embed_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id')->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->boolean('enabled')->default(false);
            $table->json('allowed_origins')->nullable();
            $table->string('accent_color', 7)->default('#FE9B59');
            $table->string('background_color', 7)->default('#F4F6FA');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_embed_settings');
    }
};
