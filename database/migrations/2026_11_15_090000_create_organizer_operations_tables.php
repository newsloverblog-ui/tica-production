<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tica_sponsorship_packages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('summary', 300)->nullable();
            $table->json('deliverables');
            $table->decimal('price', 18, 2)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->unsignedInteger('available_slots')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
            $table->index(['organizer_id', 'status', 'id'], 'tica_sponsor_org_status_idx');
        });

        Schema::create('tica_organizer_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->unique();
            $table->string('plan', 24)->default('launch');
            $table->string('status', 24)->default('active')->index();
            $table->boolean('automatic_upgrade')->default(false);
            $table->string('requested_plan', 24)->nullable();
            $table->timestamp('change_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['organizer_id', 'gatewayType', 'id'], 'bookings_organizer_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex('bookings_organizer_channel_idx'));
        Schema::dropIfExists('tica_organizer_subscriptions');
        Schema::dropIfExists('tica_sponsorship_packages');
    }
};
