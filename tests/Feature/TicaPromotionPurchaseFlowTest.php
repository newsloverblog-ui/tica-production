<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicaPromotionPlan;
use App\Services\TicaPromotionService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicaPromotionPurchaseFlowTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'promotion_test',
            'database.connections.promotion_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
        ]);
        DB::purge('promotion_test');
        $this->schema();
    }

    protected function tearDown(): void
    {
        DB::purge('promotion_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_package_price_is_server_controlled_payment_is_idempotent_and_capacity_is_enforced(): void
    {
        $organizer = Organizer::query()->create(['email' => 'org@example.test', 'username' => 'org', 'password' => 'hash', 'status' => 1, 'amount' => 1000]);
        $event = Event::query()->create(['organizer_id' => $organizer->id, 'status' => 1, 'is_featured' => 'no']);
        $plan = TicaPromotionPlan::query()->create([
            'name' => 'Featured Event', 'slug' => 'featured-event', 'placement' => 'featured_event', 'type' => 'featured_event',
            'description' => 'Featured placement', 'price' => 100, 'currency' => 'NGN', 'duration_days' => 7,
            'is_active' => true, 'status' => 'published', 'approval_required' => true, 'priority' => 100, 'max_concurrent' => 1,
        ]);
        $service = app(TicaPromotionService::class);
        $start = Carbon::tomorrow()->startOfDay();
        $first = $service->createCampaign($organizer->id, $event, $plan->id, $start, ['headline' => 'First']);
        $second = $service->createCampaign($organizer->id, $event, $plan->id, $start, ['headline' => 'Second']);

        $this->assertSame('100.00', $first->price);
        $paid = $service->payFromBalance($first->id, $organizer->id);
        $retried = $service->payFromBalance($first->id, $organizer->id);
        $this->assertSame($paid->payment_transaction_id, $retried->payment_transaction_id);
        $this->assertSame(1, DB::table('transactions')->count());
        $this->assertSame(900.0, (float) $organizer->fresh()->amount);

        $this->expectException(ValidationException::class);
        $service->payFromBalance($second->id, $organizer->id);
    }

    private function schema(): void
    {
        Schema::create('organizers', function (Blueprint $t): void { $t->id(); $t->string('email'); $t->string('username'); $t->string('password'); $t->integer('status'); $t->decimal('amount', 12, 2); $t->timestamps(); });
        Schema::create('events', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->integer('status'); $t->string('is_featured')->nullable(); $t->timestamps(); });
        Schema::create('tica_promotion_plans', function (Blueprint $t): void {
            $t->id(); $t->string('name'); $t->string('slug')->nullable(); $t->string('placement'); $t->string('type')->nullable(); $t->string('description')->nullable();
            $t->decimal('price', 12, 2); $t->string('currency', 3); $t->integer('duration_days'); $t->boolean('is_active'); $t->string('status');
            $t->json('settings')->nullable(); $t->json('benefits')->nullable(); $t->json('placements')->nullable(); $t->json('eligibility')->nullable();
            $t->boolean('approval_required'); $t->integer('priority'); $t->integer('capacity')->nullable(); $t->integer('max_concurrent')->nullable();
            $t->timestamp('available_from')->nullable(); $t->timestamp('available_until')->nullable(); $t->timestamp('archived_at')->nullable(); $t->unsignedBigInteger('duplicated_from_id')->nullable(); $t->timestamps();
        });
        Schema::create('tica_promotion_campaigns', function (Blueprint $t): void {
            $t->id(); $t->string('reference')->nullable(); $t->unsignedBigInteger('organizer_id'); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('plan_id');
            $t->string('placement')->nullable(); $t->unsignedBigInteger('payment_transaction_id')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->string('status'); $t->string('payment_status'); $t->string('payment_reference')->nullable(); $t->string('headline')->nullable(); $t->text('body')->nullable();
            $t->string('destination_url')->nullable(); $t->string('creative_path')->nullable(); $t->integer('priority')->default(100);
            $t->integer('impressions')->default(0); $t->integer('clicks')->default(0); $t->integer('attributed_orders')->default(0); $t->integer('attributed_tickets')->default(0); $t->decimal('attributed_revenue', 14, 2)->default(0);
            $t->decimal('price', 12, 2); $t->string('currency', 3); $t->timestamp('paid_at')->nullable(); $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamp('paused_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('refunded_at')->nullable(); $t->text('admin_notes')->nullable(); $t->text('change_request')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('transactions', function (Blueprint $t): void { $t->id(); $t->string('currency')->nullable(); $t->string('transcation_id'); $t->unsignedBigInteger('booking_id')->nullable(); $t->integer('transcation_type'); $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('organizer_id')->nullable(); $t->integer('payment_status'); $t->string('payment_method')->nullable(); $t->decimal('grand_total', 12, 2); $t->decimal('pre_balance', 12, 2)->nullable(); $t->decimal('after_balance', 12, 2)->nullable(); $t->string('gateway_type')->nullable(); $t->string('currency_symbol')->nullable(); $t->string('currency_symbol_position')->nullable(); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t): void { $t->id(); $t->string('actor_type'); $t->unsignedBigInteger('actor_id')->nullable(); $t->string('action'); $t->string('subject_type')->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->json('before')->nullable(); $t->json('after')->nullable(); $t->json('metadata')->nullable(); $t->string('request_id')->nullable(); $t->string('ip_address')->nullable(); $t->text('user_agent')->nullable(); $t->timestamp('created_at'); });
    }
}
