<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_programme_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('kind', 24)->default('speaker');
            $table->string('name', 120);
            $table->string('role', 160)->nullable();
            $table->string('topic', 240)->nullable();
            $table->text('bio')->nullable();
            $table->string('image', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['event_id', 'is_visible', 'sort_order'], 'event_programme_people_event_order_idx');
        });

        Schema::create('event_agenda_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('programme_person_id')->nullable()->index();
            $table->string('title', 180);
            $table->string('location', 160)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['event_id', 'is_visible', 'starts_at', 'sort_order'], 'event_agenda_items_event_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_agenda_items');
        Schema::dropIfExists('event_programme_people');
    }
};
