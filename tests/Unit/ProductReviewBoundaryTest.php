<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Shop\ShopController;
use App\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductReviewBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status')->default(true);
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('payment_status');
            $table->string('refund_status')->nullable();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedTinyInteger('review')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function test_verified_purchaser_can_create_and_update_one_review(): void
    {
        $this->authenticateCustomer(17);
        $this->seedProductPurchase(productId: 31, customerId: 17);

        $response = $this->submitReview([
            'product_id' => 31,
            'review' => 5,
            'comment' => 'A genuinely useful product.',
        ]);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Review submitted successfully.', session('success'));
        $this->assertDatabaseHas('product_reviews', [
            'user_id' => 17,
            'product_id' => 31,
            'review' => 5,
            'comment' => 'A genuinely useful product.',
        ]);

        $this->submitReview([
            'product_id' => 31,
            'review' => 4,
            'comment' => 'Updated after longer use.',
        ]);

        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertDatabaseHas('product_reviews', [
            'user_id' => 17,
            'product_id' => 31,
            'review' => 4,
            'comment' => 'Updated after longer use.',
        ]);
    }

    public function test_non_purchaser_and_refunded_purchaser_cannot_review(): void
    {
        $this->authenticateCustomer(18);
        DB::table('products')->insert(['id' => 32, 'status' => 1]);

        $response = $this->submitReview([
            'product_id' => 32,
            'review' => 5,
        ]);

        $this->assertSame('Only verified purchasers can review this product.', session('error'));
        $this->assertDatabaseCount('product_reviews', 0);

        $this->seedProductPurchase(productId: 32, customerId: 18, refundStatus: 'processed');
        $response = $this->submitReview([
            'product_id' => 32,
            'comment' => 'This must not be accepted.',
        ]);

        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_review_payload_is_bounded_and_requires_content(): void
    {
        $this->authenticateCustomer(19);
        $this->seedProductPurchase(productId: 33, customerId: 19);

        foreach ([
            ['product_id' => 33],
            ['product_id' => 33, 'review' => 6],
            ['product_id' => 33, 'comment' => str_repeat('x', 2001)],
        ] as $payload) {
            try {
                $this->submitReview($payload);
                $this->fail('Invalid review payload unexpectedly passed validation.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('product_reviews', 0);
            }
        }
    }

    private function authenticateCustomer(int $customerId): void
    {
        $customer = new Customer();
        $customer->forceFill(['id' => $customerId, 'status' => 1]);
        $customer->exists = true;
        Auth::guard('customer')->setUser($customer);
    }

    private function seedProductPurchase(int $productId, int $customerId, ?string $refundStatus = null): void
    {
        DB::table('products')->insertOrIgnore(['id' => $productId, 'status' => 1]);
        $orderId = DB::table('product_orders')->insertGetId([
            'user_id' => $customerId,
            'payment_status' => 'completed',
            'refund_status' => $refundStatus,
        ]);
        DB::table('order_items')->insert([
            'product_order_id' => $orderId,
            'product_id' => $productId,
            'user_id' => $customerId,
        ]);
    }

    private function submitReview(array $payload)
    {
        $request = Request::create('/shop/review-submit', 'POST', $payload, [], [], [
            'HTTP_REFERER' => 'https://tica.test/shop/details/example/1',
        ]);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);

        return app(ShopController::class)->review($request);
    }
}
