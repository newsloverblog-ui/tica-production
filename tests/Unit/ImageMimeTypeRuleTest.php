<?php

namespace Tests\Unit;

use App\Rules\ImageMimeTypeRule;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageMimeTypeRuleTest extends TestCase
{
    public function test_it_accepts_a_real_raster_image(): void
    {
        $image = UploadedFile::fake()->image('event-poster.png', 1200, 630);

        $this->assertTrue((new ImageMimeTypeRule())->passes('thumbnail', $image));
    }

    public function test_it_rejects_non_image_content_disguised_with_an_image_extension(): void
    {
        $image = UploadedFile::fake()->createWithContent(
            'event-poster.jpg',
            "<?php echo 'not an image';"
        );

        $this->assertFalse((new ImageMimeTypeRule())->passes('thumbnail', $image));
    }

    public function test_it_rejects_scriptable_svg_uploads(): void
    {
        $image = UploadedFile::fake()->createWithContent(
            'event-poster.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->assertFalse((new ImageMimeTypeRule())->passes('thumbnail', $image));
    }

    public function test_it_rejects_an_extension_that_does_not_match_the_detected_image_type(): void
    {
        $png = UploadedFile::fake()->image('source.png', 100, 100);
        $mismatch = UploadedFile::fake()->createWithContent('renamed.jpg', file_get_contents($png->getRealPath()));

        $this->assertFalse((new ImageMimeTypeRule())->passes('thumbnail', $mismatch));
    }
}
