<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class ServerlessFilesystemSafetyTest extends TestCase
{
    public function test_web_controllers_do_not_write_to_bundled_public_or_resource_paths(): void
    {
        $files = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Http/Controllers'))),
            '/\.php$/'
        );
        $violations = [];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file->getPathname());
            foreach ([
                '/file_put_contents\s*\(\s*(public_path|resource_path)\s*\(/',
                '/->move\s*\(\s*public_path\s*\(/',
            ] as $pattern) {
                if (preg_match($pattern, $source)) {
                    $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
                    break;
                }
            }
        }

        $this->assertSame([], $violations, 'Read-only serverless writes found: ' . implode(', ', $violations));
    }

    public function test_dynamic_uploaded_images_are_not_hidden_by_local_file_checks(): void
    {
        $paths = [
            app_path('Services/TicaUi/EventCatalog.php'),
            resource_path('views/frontend/event/event.blade.php'),
            resource_path('views/frontend/event/event-details.blade.php'),
            resource_path('views/frontend/home/tica-front.blade.php'),
            resource_path('views/frontend/home/index-v1.blade.php'),
            resource_path('views/frontend/partials/event-layer-card.blade.php'),
            resource_path('views/frontend/partials/tica-front-event-card.blade.php'),
        ];

        foreach ($paths as $path) {
            $source = (string) file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression(
                '/(?:file_exists|is_file)\s*\(\s*public_path\([^\n]*assets\/admin\/img/i',
                $source,
                $path . ' still assumes a durable upload exists on the local filesystem.'
            );
        }
    }
}
