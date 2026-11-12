<?php

namespace Tests\Unit;

use App\Models\Earning;
use App\Models\ShopManagement\OrderItem;
use App\Models\ShopManagement\Product;
use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use App\Models\ProductPaymentIntent;
use App\Services\ProductOrderRefundService;
use App\Services\ProductGatewayRefundService;
use App\Services\PaystackGatewayClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProductOrderRefundServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_full_refund_reverses_finance_and_restores_physical_inventory_once(): void
    {
        [$order, $product] = $this->paidOrder('processing', 50, true);

        $refund = app(ProductOrderRefundService::class)->recordFullManualRefund(
            $order->id,
            ' bank-ref-001 ',
            'Customer cancellation confirmed by finance.',
            9,
            true
        );

        $this->assertSame('processed', $refund->status);
        $this->assertSame('BANK-REF-001', $refund->external_reference);
        $this->assertNotNull($refund->financial_applied_at);
        $this->assertNotNull($refund->inventory_restored_at);
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(0.0, (float) Earning::first()->total_revenue);
        $this->assertSame(0.0, (float) Earning::first()->total_earning);
        $this->assertDatabaseHas('product_orders', [
            'id' => $order->id,
            'payment_status' => 'refunded',
            'order_status' => 'cancelled',
            'refund_status' => 'refunded',
            'refunded_amount' => 50,
        ]);
        $this->assertDatabaseHas('transactions', [
            'booking_id' => (string) $order->id,
            'transcation_type' => 7,
            'payment_status' => 'processed',
            'grand_total' => 50,
        ]);
        $this->assertDatabaseCount('product_order_refunds', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product_refund.processed']);
    }

    public function test_repeat_refund_cannot_reverse_finance_or_inventory_twice(): void
    {
        [$order, $product] = $this->paidOrder('processing', 50, true);
        $service = app(ProductOrderRefundService::class);
        $service->recordFullManualRefund($order->id, 'BANK-REF-002', 'First refund.', 9, true);

        try {
            $service->recordFullManualRefund($order->id, 'BANK-REF-003', 'Repeated refund.', 9, true);
            $this->fail('Expected a repeated refund to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('completed product payment', $exception->getMessage());
        }

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(0.0, (float) Earning::first()->total_revenue);
        $this->assertDatabaseCount('product_order_refunds', 1);
        $this->assertSame(1, Transaction::where('transcation_type', 7)->count());
    }

    public function test_unfulfilled_refund_requires_inventory_restoration(): void
    {
        [$order, $product] = $this->paidOrder('processing', 50, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must restore');

        try {
            app(ProductOrderRefundService::class)->recordFullManualRefund(
                $order->id,
                'BANK-REF-004',
                'Refund without return.',
                9,
                false
            );
        } finally {
            $this->assertSame(3, (int) $product->fresh()->stock);
            $this->assertSame(50.0, (float) Earning::first()->total_revenue);
            $this->assertDatabaseCount('product_order_refunds', 0);
        }
    }

    public function test_missing_mismatched_or_ambiguous_sale_ledger_fails_atomically(): void
    {
        [$order, $product] = $this->paidOrder('completed', 50, true);
        Transaction::where('transcation_type', 2)->update(['grand_total' => 49]);

        try {
            app(ProductOrderRefundService::class)->recordFullManualRefund(
                $order->id,
                'BANK-REF-005',
                'Ledger mismatch test.',
                9,
                true
            );
            $this->fail('Expected a mismatched sale ledger to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not reconcile', $exception->getMessage());
        }

        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(50.0, (float) Earning::first()->total_revenue);
        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertDatabaseCount('product_order_refunds', 0);

        Transaction::where('transcation_type', 2)->update(['grand_total' => 50]);
        $sale = Transaction::where('transcation_type', 2)->first();
        Transaction::create(array_merge($sale->only([
            'booking_id', 'customer_id', 'organizer_id', 'payment_status', 'payment_method',
            'grand_total', 'commission', 'tax', 'gateway_type', 'currency_symbol',
            'currency_symbol_position',
        ]), [
            'transcation_id' => 'duplicate-sale',
            'transcation_type' => 2,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or ambiguous');
        app(ProductOrderRefundService::class)->recordFullManualRefund(
            $order->id,
            'BANK-REF-006',
            'Ambiguous ledger test.',
            9,
            true
        );
    }

    public function test_already_released_inventory_cannot_be_restored_again(): void
    {
        [$order, $product] = $this->paidOrder('completed', 50, true);
        $order->forceFill(['inventory_released_at' => now()])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already released');

        try {
            app(ProductOrderRefundService::class)->recordFullManualRefund(
                $order->id,
                'BANK-REF-007',
                'Invalid second inventory release.',
                9,
                true
            );
        } finally {
            $this->assertSame(3, (int) $product->fresh()->stock);
            $this->assertSame(50.0, (float) Earning::first()->total_revenue);
            $this->assertDatabaseCount('product_order_refunds', 0);
        }
    }

    public function test_paystack_refund_changes_finance_only_after_provider_confirms_processed(): void
    {
        [$order, $product, $refund, $service] = $this->pendingPaystackRefund();

        $this->assertSame('processing', $refund->status);
        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertSame(50.0, (float) Earning::first()->total_revenue);
        $this->assertSame(3, (int) $product->fresh()->stock);

        $service->recordGatewayStatus($refund, 'processed', [
            'id' => 901, 'status' => 'processed',
            'transaction_reference' => 'tica-shop-refund', 'amount' => 5000,
        ]);
        $service->recordGatewayStatus($refund->fresh(), 'processed', [
            'id' => 901, 'status' => 'processed',
            'transaction_reference' => 'tica-shop-refund', 'amount' => 5000,
        ]);

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(0.0, (float) Earning::first()->total_revenue);
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('product_order_refunds', 1);
        $this->assertSame(1, Transaction::where('transcation_type', 7)->count());
        $this->assertNotNull($refund->fresh()->financial_applied_at);
    }

    public function test_paystack_refund_identity_mismatch_cannot_change_finance_or_inventory(): void
    {
        [$order, $product, $refund, $service] = $this->pendingPaystackRefund();

        $result = $service->recordGatewayStatus($refund, 'processed', [
            'id' => 901,
            'status' => 'processed',
            'transaction_reference' => 'different-transaction',
            'amount' => 9900,
        ]);

        $this->assertSame('verification_required', $result->status);
        $this->assertSame('Provider refund identity mismatch.', $result->last_error);
        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertSame(50.0, (float) Earning::first()->total_revenue);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('product_order_refunds', 0);
        $this->assertSame(0, Transaction::where('transcation_type', 7)->count());
    }

    public function test_provider_confirmed_refund_stays_retryable_when_local_finance_application_fails(): void
    {
        [$order, $product, $refund, $service] = $this->pendingPaystackRefund();
        Transaction::where('transcation_type', 2)->delete();

        $result = $service->recordGatewayStatus($refund, 'processed', [
            'id' => 901,
            'status' => 'processed',
            'transaction_reference' => 'tica-shop-refund',
            'amount' => 5000,
        ]);

        $this->assertSame('needs_attention', $result->status);
        $this->assertNull($result->financial_applied_at);
        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertSame(50.0, (float) Earning::first()->total_revenue);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('product_order_refunds', 0);
    }

    public function test_unknown_paystack_refund_submission_is_recovered_by_provider_transaction_identity(): void
    {
        [$order, $product, $refund, $service] = $this->pendingPaystackRefund();
        ProductPaymentIntent::whereKey($refund->product_payment_intent_id)
            ->update(['gateway_transaction_id' => '778899']);
        $refund->update([
            'status' => 'verification_required',
            'gateway_refund_id' => null,
            'last_error' => 'Submission outcome was unknown.',
        ]);
        Http::fake([
            'https://api.paystack.co/refund*' => Http::response([
                'status' => true,
                'data' => [[
                    'id' => 902,
                    'transaction' => 778899,
                    'amount' => 5000,
                    'currency' => 'NGN',
                    'status' => 'processed',
                ]],
            ]),
        ]);

        $result = $service->recoverUnknownSubmission(
            $refund->fresh(),
            new PaystackGatewayClient('test-secret')
        );

        $this->assertSame('processed', $result->status);
        $this->assertSame('902', $result->gateway_refund_id);
        $this->assertNotNull($result->financial_applied_at);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(0.0, (float) Earning::first()->total_revenue);
        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_ambiguous_paystack_refund_recovery_fails_to_manual_attention(): void
    {
        [$order, $product, $refund, $service] = $this->pendingPaystackRefund();
        ProductPaymentIntent::whereKey($refund->product_payment_intent_id)
            ->update(['gateway_transaction_id' => '778899']);
        $refund->update(['status' => 'verification_required', 'gateway_refund_id' => null]);
        $candidate = [
            'transaction' => 778899,
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'processed',
        ];
        Http::fake([
            'https://api.paystack.co/refund*' => Http::response([
                'status' => true,
                'data' => [array_merge($candidate, ['id' => 902]), array_merge($candidate, ['id' => 903])],
            ]),
        ]);

        $result = $service->recoverUnknownSubmission(
            $refund->fresh(),
            new PaystackGatewayClient('test-secret')
        );

        $this->assertSame('needs_attention', $result->status);
        $this->assertNull($result->financial_applied_at);
        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertSame(50.0, (float) Earning::first()->total_revenue);
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    private function pendingPaystackRefund(): array
    {
        [$order, $product] = $this->paidOrder('processing', 50, true);
        $order->forceFill(['method' => 'Paystack', 'gateway_type' => 'online', 'tnxid' => 'tica-shop-refund'])->save();
        Transaction::where('transcation_type', 2)->update([
            'transcation_id' => 'tica-shop-refund',
            'gateway' => 'paystack',
            'gateway_reference' => 'tica-shop-refund',
            'payment_method' => 'Paystack',
            'gateway_type' => 'online',
        ]);
        ProductPaymentIntent::create([
            'gateway' => 'paystack', 'reference' => 'tica-shop-refund',
            'product_order_id' => $order->id, 'customer_id' => 41,
            'status' => 'completed', 'amount' => 50, 'currency' => 'NGN',
            'email' => 'buyer@example.com', 'processed_at' => now(),
        ]);
        Http::fake([
            'https://api.paystack.co/refund' => Http::response([
                'status' => true, 'data' => ['id' => 901, 'status' => 'processing'],
            ]),
        ]);
        $service = app(ProductGatewayRefundService::class);
        $refund = $service->requestFull($order->id, 'Customer cancellation.', 9, true);
        $refund = $service->submit($refund, new PaystackGatewayClient('test-secret'));

        return [$order, $product, $refund, $service];
    }

    private function paidOrder(string $orderStatus, float $total, bool $withInventory): array
    {
        $product = Product::create([
            'stock' => 3,
            'sku' => 'REFUND-SKU',
            'feature_image' => 'product.jpg',
            'current_price' => 25,
            'status' => 1,
            'type' => 'physical',
        ]);
        $order = ProductOrder::create([
            'user_id' => 41,
            'cart_total' => $total,
            'total' => $total,
            'method' => 'Bank transfer',
            'gateway_type' => 'offline',
            'payment_status' => 'completed',
            'refunded_amount' => 0,
            'order_status' => $orderStatus,
            'order_number' => uniqid('paid-order-', true),
            'tnxid' => uniqid('sale-', true),
            'currency_text' => 'NGN',
            'currency_symbol' => '₦',
            'currency_symbol_position' => 'left',
            'inventory_reserved_at' => $withInventory ? now() : null,
        ]);
        OrderItem::create([
            'product_order_id' => $order->id,
            'product_id' => $product->id,
            'user_id' => 41,
            'title' => 'Refundable product',
            'sku' => 'REFUND-SKU',
            'qty' => 2,
            'price' => 25,
        ]);
        Transaction::create([
            'transcation_id' => $order->tnxid,
            'booking_id' => $order->id,
            'transcation_type' => 2,
            'customer_id' => 41,
            'payment_status' => 1,
            'payment_method' => 'Bank transfer',
            'grand_total' => $total,
            'commission' => $total,
            'tax' => 0,
            'gateway_type' => 'offline',
            'currency_symbol' => '₦',
            'currency_symbol_position' => 'left',
        ]);
        Earning::create(['total_revenue' => $total, 'total_earning' => $total]);

        return [$order, $product];
    }

    private function createSchema(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id(); $table->string('stock')->nullable(); $table->string('sku')->nullable();
            $table->string('feature_image')->nullable(); $table->decimal('current_price', 18, 2);
            $table->decimal('previous_price', 18, 2)->nullable(); $table->string('is_feature')->nullable();
            $table->integer('status'); $table->string('type'); $table->string('file_type')->nullable();
            $table->string('download_file')->nullable(); $table->text('download_link')->nullable(); $table->timestamps();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->decimal('cart_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2); $table->string('method')->nullable(); $table->string('gateway_type')->nullable();
            $table->string('payment_status'); $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->string('refund_status')->nullable(); $table->timestamp('refunded_at')->nullable();
            $table->string('order_status'); $table->string('order_number')->unique(); $table->string('tnxid')->nullable();
            $table->string('currency_text')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamp('inventory_reserved_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable(); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable(); $table->string('title'); $table->string('sku')->nullable();
            $table->string('qty'); $table->string('category')->nullable(); $table->string('image')->nullable();
            $table->text('summery')->nullable(); $table->text('description')->nullable();
            $table->decimal('price', 18, 2); $table->decimal('previous_price', 18, 2)->nullable(); $table->timestamps();
        });
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2)->nullable();
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->default(0);
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
        });
        Schema::create('product_order_refunds', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('product_order_id')->unique();
            $table->unsignedBigInteger('sale_transaction_id'); $table->unsignedBigInteger('requested_by_admin_id');
            $table->string('external_reference', 191)->unique(); $table->decimal('amount', 18, 2);
            $table->string('currency', 8)->nullable(); $table->text('reason');
            $table->boolean('restore_inventory')->default(false); $table->string('status', 32);
            $table->timestamp('financial_applied_at')->nullable(); $table->timestamp('inventory_restored_at')->nullable();
            $table->timestamp('processed_at'); $table->timestamps();
        });
        Schema::create('product_payment_intents', function (Blueprint $table): void {
            $table->id(); $table->string('gateway'); $table->string('reference');
            $table->unsignedBigInteger('product_order_id'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status'); $table->decimal('amount', 18, 2); $table->string('currency');
            $table->string('email'); $table->text('authorization_url')->nullable();
            $table->string('gateway_transaction_id')->nullable(); $table->unsignedInteger('verification_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable(); $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
        });
        Schema::create('product_gateway_refunds', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('product_payment_intent_id');
            $table->unsignedBigInteger('product_order_id')->unique(); $table->unsignedBigInteger('sale_transaction_id');
            $table->unsignedBigInteger('requested_by_admin_id'); $table->string('gateway');
            $table->string('gateway_reference'); $table->string('gateway_refund_id')->nullable();
            $table->string('gateway_status')->nullable(); $table->decimal('amount', 18, 2); $table->string('currency');
            $table->text('reason'); $table->boolean('restore_inventory'); $table->string('status');
            $table->text('gateway_response')->nullable(); $table->text('last_error')->nullable();
            $table->timestamp('requested_at'); $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamp('financial_applied_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type')->nullable(); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->longText('before')->nullable(); $table->longText('after')->nullable(); $table->longText('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->string('user_agent', 1000)->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
