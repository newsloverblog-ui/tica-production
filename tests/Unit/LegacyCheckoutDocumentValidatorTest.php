<?php

namespace Tests\Unit;

use App\Services\LegacyCheckoutDocumentValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class LegacyCheckoutDocumentValidatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/legacy-document-validation-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_accepts_genuine_passive_pdf_and_image_documents(): void
    {
        $pdf = $this->root.'/invoice.pdf';
        $image = $this->root.'/evidence.png';
        File::put($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF");
        $generated = UploadedFile::fake()->image('evidence.png', 40, 40);
        File::put($image, (string) file_get_contents($generated->getRealPath()));

        $validator = new LegacyCheckoutDocumentValidator();

        $this->assertSame('pdf', $validator->validate($pdf, 'invoice'));
        $this->assertSame('png', $validator->validate($image, 'attachment'));
    }

    public function test_it_rejects_active_pdf_and_disguised_image_content(): void
    {
        $active = $this->root.'/active.pdf';
        $disguised = $this->root.'/receipt.jpg';
        File::put($active, "%PDF-1.4\n/OpenAction << /S /JavaScript >>\n%%EOF");
        File::put($disguised, '<?php echo "unsafe";');

        $validator = new LegacyCheckoutDocumentValidator();

        try {
            $validator->validate($active, 'invoice');
            $this->fail('Active PDF content must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Active or embedded content', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $validator->validate($disguised, 'receipt');
    }
}
