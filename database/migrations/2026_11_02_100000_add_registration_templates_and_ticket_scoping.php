<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('definition');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['organizer_id', 'name'], 'registration_template_owner_name');
        });

        Schema::table('event_registration_questions', function (Blueprint $table): void {
            $table->unsignedBigInteger('ticket_id')->nullable()->after('form_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_questions', function (Blueprint $table): void {
            $table->dropColumn('ticket_id');
        });
        Schema::dropIfExists('event_registration_form_templates');
    }
};
