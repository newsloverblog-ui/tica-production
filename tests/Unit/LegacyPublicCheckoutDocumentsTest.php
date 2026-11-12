<?php

namespace Tests\Unit;

use App\Services\LegacyPublicCheckoutDocuments;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class LegacyPublicCheckoutDocumentsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/legacy-public-documents-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->root.'/event/invoices');
        File::ensureDirectoryExists($this->root.'/event/attachments');
        File::ensureDirectoryExists($this->root.'/product/invoices');
        File::ensureDirectoryExists($this->root.'/product/attachments');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_finds_nested_event_and_product_files(): void
    {
        File::put($this->root.'/event/invoices/event.pdf', 'event');
        File::ensureDirectoryExists($this->root.'/product/attachments/nested');
        File::put($this->root.'/product/attachments/nested/receipt.png', 'product');

        $documents = $this->documents();

        $this->assertSame([$this->root.'/event/invoices/event.pdf'], $documents->eventFiles());
        $this->assertSame([$this->root.'/product/attachments/nested/receipt.png'], $documents->productFiles());
    }

    public function test_it_deletes_only_a_scanned_legacy_public_file(): void
    {
        $legacy = $this->root.'/event/attachments/evidence.jpg';
        $outside = $this->root.'/outside.txt';
        File::put($legacy, 'legacy');
        File::put($outside, 'outside');

        $documents = $this->documents();
        $documents->delete($legacy);

        $this->assertFileDoesNotExist($legacy);
        $this->assertFileExists($outside);

        $this->expectException(RuntimeException::class);
        $documents->delete($outside);
    }

    private function documents(): LegacyPublicCheckoutDocuments
    {
        return new LegacyPublicCheckoutDocuments([
            'event' => [$this->root.'/event/invoices', $this->root.'/event/attachments'],
            'product' => [$this->root.'/product/invoices', $this->root.'/product/attachments'],
        ]);
    }
}
