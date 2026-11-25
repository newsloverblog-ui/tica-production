<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paystack_disputes', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_dispute_id', 32)->unique();
            $table->string('reference', 191)->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('status', 24);
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->string('exception', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->string('review_reference', 100)->nullable();
            $table->timestamps();
            $table->index(['organizer_id', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE paystack_disputes ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_disputes');
    }
};
