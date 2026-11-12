<?php

namespace Tests\Unit;

use App\Models\ShopManagement\ProductOrder;
use App\Services\ProductOrderDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductOrderDocumentStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.product_order_documents_disk' => 'local']);
        Storage::fake('local');
    }

    public function test_new_receipts_and_invoices_are_written_to_private_order_scoped_paths(): void
    {
        $order = new ProductOrder(['order_number' => 'TICA-ORDER-42']);
        $order->id = 42;
        $storage = app(ProductOrderDocumentStorage::class);

        $receipt = $storage->storeReceipt(
            $order,
            UploadedFile::fake()->image('receipt.png', 800, 600)
        );
        $invoice = $storage->storeInvoice($order, '%PDF-private-invoice');

        $this->assertStringStartsWith('private/product-orders/42/receipts/', $receipt);
        $this->assertSame('private/product-orders/42/invoices/TICA-ORDER-42.pdf', $invoice);
        Storage::disk('local')->assertExists($receipt);
        Storage::disk('local')->assertExists($invoice);
        $this->assertSame('%PDF-private-invoice', Storage::disk('local')->get($invoice));
    }

    public function test_private_path_detection_does_not_accept_arbitrary_or_legacy_values(): void
    {
        $storage = app(ProductOrderDocumentStorage::class);
        $order = new ProductOrder(['order_number' => 'TICA-ORDER-42']);
        $order->id = 42;

        $this->assertTrue($storage->isPrivatePath('private/product-orders/1/invoices/order.pdf'));
        $this->assertTrue($storage->isPrivatePathForOrder($order, 'private/product-orders/42/invoices/order.pdf'));
        $this->assertFalse($storage->isPrivatePathForOrder($order, 'private/product-orders/99/invoices/order.pdf'));
        $this->assertFalse($storage->isPrivatePath('order.pdf'));
        $this->assertFalse($storage->isPrivatePath('../private/product-orders/1/invoices/order.pdf'));
    }

    public function test_cross_order_invoice_reference_is_rejected(): void
    {
        $order = new ProductOrder([
            'order_number' => 'TICA-ORDER-42',
            'invoice_number' => 'private/product-orders/99/invoices/other.pdf',
        ]);
        $order->id = 42;

        $this->expectException(RuntimeException::class);
        app(ProductOrderDocumentStorage::class)->attachmentPath($order);
    }

    public function test_private_cleanup_cannot_delete_another_orders_document(): void
    {
        $order = new ProductOrder(['order_number' => 'TICA-ORDER-42']);
        $order->id = 42;

        $this->expectException(RuntimeException::class);
        app(ProductOrderDocumentStorage::class)->deletePrivatePath(
            $order,
            'private/product-orders/99/receipts/evidence.png'
        );
    }
}
