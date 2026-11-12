<?php

namespace Tests\Unit;

use App\Rules\KycDocumentRule;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KycDocumentRuleTest extends TestCase
{
    public function test_it_accepts_real_images_and_passive_pdfs(): void
    {
        $this->assertTrue((new KycDocumentRule())->passes('identity', UploadedFile::fake()->image('identity.png', 800, 800)));
        $this->assertTrue((new KycDocumentRule())->passes('identity', UploadedFile::fake()->createWithContent(
            'identity.pdf',
            "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n"
        )));
    }

    public function test_it_rejects_disguised_files_and_active_pdf_actions(): void
    {
        $this->assertFalse((new KycDocumentRule())->passes('identity', UploadedFile::fake()->createWithContent(
            'identity.jpg',
            '<?php echo "not an image";'
        )));
        $this->assertFalse((new KycDocumentRule())->passes('identity', UploadedFile::fake()->createWithContent(
            'identity.pdf',
            "%PDF-1.4\n1 0 obj<</OpenAction<</S/JavaScript/JS(alert(1))>>>>endobj\n%%EOF\n"
        )));
    }

    public function test_it_rejects_incomplete_pdf_structure(): void
    {
        $this->assertFalse((new KycDocumentRule())->passes('identity', UploadedFile::fake()->createWithContent(
            'identity.pdf',
            "%PDF-1.4\nmissing eof"
        )));
    }
}
