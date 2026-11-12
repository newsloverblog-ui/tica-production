<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_disputes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('withdrawal_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->string('category', 40);
            $table->text('description');
            $table->string('status', 24)->default('open');
            $table->unsignedTinyInteger('withdrawal_status');
            $table->decimal('amount', 18, 2);
            $table->text('resolution')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
            $table->index(['organizer_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_disputes');
    }
};
