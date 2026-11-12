<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('requested_by_type', 20)->index();
            $table->unsignedBigInteger('requested_by_id')->index();
            $table->unsignedBigInteger('organizer_id')->nullable()->index();
            $table->string('export_type', 40)->index();
            $table->string('format', 8);
            $table->string('status', 20)->index();
            $table->longText('filters')->nullable();
            $table->string('disk', 40);
            $table->string('path', 500)->nullable();
            $table->string('filename', 191);
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by_type', 'requested_by_id', 'created_at'], 'data_exports_actor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
