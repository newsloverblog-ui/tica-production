<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_export_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('requested_by_type', 20);
            $table->unsignedBigInteger('requested_by_id');
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('export_type', 40)->default('bookings');
            $table->string('format', 8);
            $table->longText('filters')->nullable();
            $table->unsignedBigInteger('language_id');
            $table->string('frequency', 16);
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('date_window_days')->default(7);
            $table->boolean('active')->default(true);
            $table->timestamp('next_run_at')->index();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedBigInteger('last_export_id')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(
                ['requested_by_type', 'requested_by_id', 'active'],
                'data_export_schedules_actor_active_idx'
            );
        });

        Schema::table('data_exports', function (Blueprint $table): void {
            $table->unsignedBigInteger('schedule_id')->nullable()->after('organizer_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('data_exports', function (Blueprint $table): void {
            $table->dropColumn('schedule_id');
        });
        Schema::dropIfExists('data_export_schedules');
    }
};
