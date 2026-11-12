<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_images') && !Schema::hasColumn('event_images', 'organizer_id')) {
            Schema::table('event_images', function (Blueprint $table): void {
                $table->foreignId('organizer_id')->nullable()->after('event_id')->constrained('organizers')->nullOnDelete()->index();
            });
        }

        Schema::create('tica_promotion_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('placement', 40)->unique();
            $table->string('description', 500)->nullable();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->unsignedSmallInteger('duration_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('tica_promotion_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('tica_promotion_plans')->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status', 32)->default('pending_payment');
            $table->string('headline', 160)->nullable();
            $table->text('body')->nullable();
            $table->string('destination_url', 2048)->nullable();
            $table->string('creative_path', 255)->nullable();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['organizer_id', 'status']);
        });

        Schema::create('tica_tracking_pixels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('pixel_identifier', 100);
            $table->string('label', 100);
            $table->string('status', 24)->default('pending_review');
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['organizer_id', 'provider', 'pixel_identifier'], 'tica_pixels_owner_provider_identifier_unique');
        });

        DB::table('tica_promotion_plans')->insert([
            ['name' => 'Featured event', 'placement' => 'featured_event', 'description' => 'Priority placement in TICA event discovery.', 'price' => 25000, 'currency' => 'NGN', 'duration_days' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sponsored event', 'placement' => 'sponsored_event', 'description' => 'Sponsored placement with a clear advertising label.', 'price' => 50000, 'currency' => 'NGN', 'duration_days' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Event popup', 'placement' => 'event_popup', 'description' => 'Admin-reviewed promotional popup with frequency limits.', 'price' => 75000, 'currency' => 'NGN', 'duration_days' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tica_tracking_pixels');
        Schema::dropIfExists('tica_promotion_campaigns');
        Schema::dropIfExists('tica_promotion_plans');
        if (Schema::hasTable('event_images') && Schema::hasColumn('event_images', 'organizer_id')) {
            Schema::table('event_images', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('organizer_id');
            });
        }
    }
};
