<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tica_promotion_plans', function (Blueprint $table): void {
            $table->string('slug', 140)->nullable()->unique()->after('name');
            $table->string('type', 40)->nullable()->after('placement');
            $table->string('status', 24)->default('published')->after('is_active');
            $table->json('benefits')->nullable()->after('settings');
            $table->json('placements')->nullable()->after('benefits');
            $table->json('eligibility')->nullable()->after('placements');
            $table->boolean('approval_required')->default(true)->after('eligibility');
            $table->unsignedSmallInteger('priority')->default(100)->after('approval_required');
            $table->unsignedSmallInteger('capacity')->nullable()->after('priority');
            $table->unsignedSmallInteger('max_concurrent')->nullable()->after('capacity');
            $table->timestamp('available_from')->nullable()->after('max_concurrent');
            $table->timestamp('available_until')->nullable()->after('available_from');
            $table->timestamp('archived_at')->nullable()->after('available_until');
            $table->unsignedBigInteger('duplicated_from_id')->nullable()->after('archived_at');
        });

        foreach (DB::table('tica_promotion_plans')->orderBy('id')->get() as $plan) {
            $base = Str::slug((string) $plan->name) ?: 'promotion-plan';
            $slug = $base;
            $suffix = 2;
            while (DB::table('tica_promotion_plans')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $suffix++;
            }
            $type = $plan->placement === 'event_popup' ? 'pop_event' : (string) $plan->placement;
            DB::table('tica_promotion_plans')->where('id', $plan->id)->update([
                'slug' => $slug,
                'type' => $type,
                'benefits' => json_encode($this->defaultBenefits((string) $plan->placement)),
                'placements' => json_encode([(string) $plan->placement]),
                'eligibility' => json_encode(['published_event' => true]),
            ]);
        }

        Schema::table('tica_promotion_campaigns', function (Blueprint $table): void {
            $table->string('reference', 32)->nullable()->unique()->after('id');
            $table->string('placement', 40)->nullable()->after('plan_id');
            $table->string('payment_status', 24)->default('pending')->after('status');
            $table->string('payment_reference', 100)->nullable()->unique()->after('payment_status');
            $table->unsignedSmallInteger('priority')->default(100)->after('creative_path');
            $table->unsignedBigInteger('impressions')->default(0)->after('priority');
            $table->unsignedBigInteger('clicks')->default(0)->after('impressions');
            $table->unsignedBigInteger('attributed_orders')->default(0)->after('clicks');
            $table->unsignedBigInteger('attributed_tickets')->default(0)->after('attributed_orders');
            $table->decimal('attributed_revenue', 14, 2)->default(0)->after('attributed_tickets');
            $table->text('change_request')->nullable()->after('admin_notes');
            $table->timestamp('paused_at')->nullable()->after('approved_at');
            $table->timestamp('cancelled_at')->nullable()->after('paused_at');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            $table->json('metadata')->nullable()->after('refunded_at');
        });

        foreach (DB::table('tica_promotion_campaigns')->orderBy('id')->get() as $campaign) {
            $placement = DB::table('tica_promotion_plans')->where('id', $campaign->plan_id)->value('placement');
            DB::table('tica_promotion_campaigns')->where('id', $campaign->id)->update([
                'reference' => 'TP-' . str_pad((string) $campaign->id, 8, '0', STR_PAD_LEFT),
                'placement' => $placement,
                'payment_status' => $campaign->paid_at ? 'paid' : 'pending',
                'payment_reference' => $campaign->payment_transaction_id ? 'BAL-' . $campaign->payment_transaction_id : null,
            ]);
        }

        Schema::create('tica_promotion_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('tica_promotion_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->string('event_type', 24);
            $table->char('visitor_hash', 64)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['campaign_id', 'event_type', 'visitor_hash'], 'tica_promotion_event_visitor_unique');
            $table->unique(['campaign_id', 'event_type', 'booking_id'], 'tica_promotion_event_booking_unique');
            $table->index(['event_type', 'occurred_at']);
        });

        Schema::table('tica_tracking_links', function (Blueprint $table): void {
            $table->string('channel', 80)->nullable()->after('name');
            $table->string('influencer', 120)->nullable()->after('channel');
            $table->boolean('is_active')->default(true)->after('clicks');
            $table->timestamp('last_clicked_at')->nullable()->after('is_active');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->decimal('minimum_purchase', 12, 2)->default(0)->after('used_count');
            $table->boolean('is_active')->default(true)->after('minimum_purchase');
            $table->timestamp('paused_at')->nullable()->after('is_active');
        });

        if (!Schema::hasColumn('bookings', 'promotion_campaign_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unsignedBigInteger('promotion_campaign_id')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('bookings', 'coupon_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unsignedBigInteger('coupon_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'promotion_campaign_id')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('promotion_campaign_id'));
        }
        Schema::table('coupons', fn (Blueprint $table) => $table->dropColumn(['minimum_purchase', 'is_active', 'paused_at']));
        Schema::table('tica_tracking_links', fn (Blueprint $table) => $table->dropColumn(['channel', 'influencer', 'is_active', 'last_clicked_at']));
        Schema::dropIfExists('tica_promotion_events');
        Schema::table('tica_promotion_campaigns', fn (Blueprint $table) => $table->dropColumn([
            'reference', 'placement', 'payment_status', 'payment_reference', 'priority', 'impressions', 'clicks',
            'attributed_orders', 'attributed_tickets', 'attributed_revenue', 'change_request', 'paused_at',
            'cancelled_at', 'refunded_at', 'metadata',
        ]));
        Schema::table('tica_promotion_plans', fn (Blueprint $table) => $table->dropColumn([
            'slug', 'type', 'status', 'benefits', 'placements', 'eligibility', 'approval_required', 'priority',
            'capacity', 'max_concurrent', 'available_from', 'available_until', 'archived_at', 'duplicated_from_id',
        ]));
    }

    private function defaultBenefits(string $placement): array
    {
        return match ($placement) {
            'featured_event' => ['Priority discovery placement', 'Featured badge', 'Performance reporting'],
            'event_popup' => ['Rotating public-site placement', 'Frequency-controlled display', 'Performance reporting'],
            default => ['Sponsored placement', 'Performance reporting'],
        };
    }
};
