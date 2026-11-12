<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Shop\OrderController;
use App\Jobs\FinalizePaidProductOrder;
use App\Jobs\SendRejectedProductOrderNotification;
use App\Models\ProductOrderDelivery;
use App\Models\ShopManagement\ProductOrder;
use App\Services\PaidProductOrderFulfillmentService;
use App\Services\ManualPaymentDecisionMailService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FinalizePaidProductOrderTest extends TestCase
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

    public function test_dispatch_is_durable_unique_and_encrypted(): void
    {
        Queue::fake();
        $order = $this->order();

        $delivery = app(PaidProductOrderFulfillmentService::class)->dispatch($order->id);

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->dispatch_attempts);
        $this->assertNotNull($delivery->dispatch_last_attempt_at);
        Queue::assertPushed(FinalizePaidProductOrder::class, function ($job) use ($order): bool {
            return $job->productOrderId === $order->id && $job instanceof ShouldBeEncrypted;
        });
    }

    public function test_job_generates_private_invoice_sends_once_and_records_delivery(): void
    {
        $order = $this->order();
        $orders = Mockery::mock(OrderController::class);
        $orders->shouldReceive('generateInvoice')->once()
            ->andReturn('private/product-orders/1/invoices/ORDER-1.pdf');
        $orders->shouldReceive('sendMail')->once()
            ->with(Mockery::type(ProductOrder::class), true)
            ->andReturnTrue();

        $job = new FinalizePaidProductOrder($order->id);
        $job->handle($orders);
        $job->handle($orders);

        $this->assertSame('private/product-orders/1/invoices/ORDER-1.pdf', $order->fresh()->invoice_number);
        $delivery = ProductOrderDelivery::where('product_order_id', $order->id)->firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, (int) $delivery->attempts);
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_queue_scheduling_failure_is_recorded_immediately(): void
    {
        $order = $this->order();
        $service = new class extends PaidProductOrderFulfillmentService {
            protected function dispatchJob(int $productOrderId): void
            {
                throw new RuntimeException('payments queue unavailable');
            }
        };

        try {
            $service->dispatch($order->id);
            $this->fail('A queue scheduling failure must remain visible to the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('payments queue unavailable', $exception->getMessage());
        }

        $delivery = ProductOrderDelivery::query()->where('product_order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->dispatch_attempts);
        $this->assertSame(
            'Product-order queue scheduling failed: payments queue unavailable',
            $delivery->dispatch_last_error
        );
    }

    public function test_mail_failure_is_persisted_and_rethrown_for_retry(): void
    {
        $order = $this->order();
        $orders = Mockery::mock(OrderController::class);
        $orders->shouldReceive('generateInvoice')->once()->andReturn('private/product-orders/1/invoices/ORDER-1.pdf');
        $orders->shouldReceive('sendMail')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        try {
            (new FinalizePaidProductOrder($order->id))->handle($orders);
            $this->fail('Delivery failure must be retried by the queue.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('product_order_deliveries', [
            'product_order_id' => $order->id,
            'status' => 'failed',
            'attempts' => 1,
        ]);
        $this->assertSame(
            'SMTP unavailable',
            ProductOrderDelivery::where('product_order_id', $order->id)->firstOrFail()->last_error
        );
    }

    public function test_rejected_order_notice_sends_once_and_records_delivery(): void
    {
        $order = $this->order();
        $order->forceFill(['payment_status' => 'rejected'])->save();
        $mail = Mockery::mock(ManualPaymentDecisionMailService::class);
        $mail->shouldReceive('sendProductOrderRejection')->once()
            ->with(Mockery::type(ProductOrder::class));

        $job = new SendRejectedProductOrderNotification($order->id);
        $job->handle($mail);
        $job->handle($mail);

        $this->assertDatabaseHas('product_order_deliveries', [
            'product_order_id' => $order->id,
            'kind' => 'product_order_rejection',
            'status' => 'sent',
            'attempts' => 1,
        ]);
    }

    public function test_rejected_order_notice_failure_is_retryable_and_recorded(): void
    {
        $order = $this->order();
        $order->forceFill(['payment_status' => 'rejected'])->save();
        $mail = Mockery::mock(ManualPaymentDecisionMailService::class);
        $mail->shouldReceive('sendProductOrderRejection')->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));

        try {
            (new SendRejectedProductOrderNotification($order->id))->handle($mail);
            $this->fail('A rejected-order mail failure must be retried by the queue.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('product_order_deliveries', [
            'product_order_id' => $order->id,
            'kind' => 'product_order_rejection',
            'status' => 'failed',
            'attempts' => 1,
        ]);
        $delivery = ProductOrderDelivery::where('product_order_id', $order->id)
            ->where('kind', 'product_order_rejection')
            ->firstOrFail();
        $this->assertSame('SMTP unavailable', $delivery->last_error);
        $this->assertNull($delivery->processing_at);
    }

    private function order(): ProductOrder
    {
        return ProductOrder::create([
            'order_number' => 'ORDER-1',
            'billing_fname' => 'Ada',
            'billing_lname' => 'Okafor',
            'billing_email' => 'ada@example.com',
            'payment_status' => 'completed',
            'order_status' => 'pending',
            'total' => 25000,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->string('billing_fname')->nullable();
            $table->string('billing_lname')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('order_status')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->string('invoice_number')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('product_order_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_order_id');
            $table->string('kind', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();
            $table->unique(['product_order_id', 'kind']);
        });
    }
}
