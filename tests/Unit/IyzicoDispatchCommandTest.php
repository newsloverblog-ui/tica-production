<?php

namespace Tests\Unit;

use App\Jobs\IyzicoEventPendingPayment;
use App\Jobs\IyzicoProductOrderPendingPayment;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IyzicoDispatchCommandTest extends TestCase
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

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('paymentStatus')->nullable();
            $table->string('paymentMethod')->nullable();
            $table->string('conversation_id')->nullable();
            $table->timestamps();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_status')->nullable();
            $table->string('method')->nullable();
            $table->string('conversation_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_command_dispatches_only_verifiable_pending_iyzico_records(): void
    {
        Queue::fake();
        DB::table('bookings')->insert([
            ['paymentStatus' => 'pending', 'paymentMethod' => 'Iyzico', 'conversation_id' => 'event-eligible'],
            ['paymentStatus' => 'completed', 'paymentMethod' => 'Iyzico', 'conversation_id' => 'event-complete'],
            ['paymentStatus' => 'pending', 'paymentMethod' => 'Paystack', 'conversation_id' => 'event-other'],
            ['paymentStatus' => 'pending', 'paymentMethod' => 'Iyzico', 'conversation_id' => null],
        ]);
        DB::table('product_orders')->insert([
            ['payment_status' => 'pending', 'method' => 'Iyzico', 'conversation_id' => 'product-eligible'],
            ['payment_status' => 'completed', 'method' => 'Iyzico', 'conversation_id' => 'product-complete'],
        ]);

        $this->assertSame(1, DB::table('product_orders')->where('payment_status', 'pending')
            ->where('method', 'Iyzico')->whereNotNull('conversation_id')->count());
        $this->assertSame(0, Artisan::call('payments:dispatch-iyzico-pending', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('"event_bookings":1', $output);
        $this->assertStringContainsString('"product_orders":1', $output);

        Queue::assertPushed(IyzicoEventPendingPayment::class, 1);
        Queue::assertPushed(IyzicoProductOrderPendingPayment::class, 1);
    }

    public function test_verification_jobs_are_unique_encrypted_and_use_payments_queue(): void
    {
        $eventJob = new IyzicoEventPendingPayment(10);
        $productJob = new IyzicoProductOrderPendingPayment(20);

        $this->assertInstanceOf(ShouldBeUnique::class, $eventJob);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $eventJob);
        $this->assertSame('payments', $eventJob->queue);
        $this->assertSame('iyzico-event-10', $eventJob->uniqueId());

        $this->assertInstanceOf(ShouldBeUnique::class, $productJob);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $productJob);
        $this->assertSame('payments', $productJob->queue);
        $this->assertSame('iyzico-product-20', $productJob->uniqueId());
    }

    public function test_legacy_payment_polling_is_not_publicly_routable(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName('cron.check.payment'));
        foreach (app('router')->getRoutes() as $route) {
            $this->assertFalse($route->uri() === 'check-payment' && in_array('GET', $route->methods(), true));
        }
    }

    public function test_product_verification_job_uses_shared_exact_once_accounting_boundary(): void
    {
        $source = file_get_contents(app_path('Jobs/IyzicoProductOrderPendingPayment.php'));

        $this->assertStringContainsString('ProductOrderAccountingService::class', $source);
        $this->assertStringContainsString("recordCompleted(\$order, 'iyzico', \$reference)", $source);
        $this->assertStringNotContainsString('Earning::', $source);
        $this->assertStringNotContainsString('Transaction::create', $source);
    }
}
