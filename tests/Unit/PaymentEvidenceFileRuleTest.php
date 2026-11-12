<?php

namespace Tests\Unit;

use App\Rules\PaymentEvidenceFileRule;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentEvidenceFileRuleTest extends TestCase
{
    public function test_it_accepts_real_jpg_and_png_receipts(): void
    {
        $rule = new PaymentEvidenceFileRule();

        $this->assertTrue($rule->passes('attachment', UploadedFile::fake()->image('receipt.jpg', 800, 600)));
        $this->assertTrue($rule->passes('attachment', UploadedFile::fake()->image('receipt.png', 800, 600)));
    }

    public function test_it_rejects_disguised_script_content_and_extension_mismatch(): void
    {
        $rule = new PaymentEvidenceFileRule();
        $script = UploadedFile::fake()->createWithContent('receipt.png', '<?php echo "unsafe";');
        $png = UploadedFile::fake()->image('source.png', 100, 100);
        $mismatch = UploadedFile::fake()->createWithContent('receipt.jpg', file_get_contents($png->getRealPath()));

        $this->assertFalse($rule->passes('attachment', $script));
        $this->assertFalse($rule->passes('attachment', $mismatch));
    }

    public function test_it_rejects_oversized_files(): void
    {
        $file = UploadedFile::fake()->create('receipt.png', 5121, 'image/png');

        $this->assertFalse((new PaymentEvidenceFileRule())->passes('attachment', $file));
    }
}
