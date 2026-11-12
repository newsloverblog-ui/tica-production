<?php

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class LegacyPublicCheckoutDocuments
{
    /** @var array{event: list<string>, product: list<string>} */
    private array $directories;

    /**
     * @param  array{event?: list<string>, product?: list<string>}|null  $directories
     */
    public function __construct(?array $directories = null)
    {
        $this->directories = $directories ?? [
            'event' => [
                public_path('assets/admin/file/invoices'),
                public_path('assets/admin/file/attachments'),
            ],
            'product' => [
                public_path('assets/admin/file/order/invoices'),
                public_path('assets/admin/file/order/attachments'),
            ],
        ];
    }

    /** @return list<string> */
    public function eventFiles(): array
    {
        return $this->files('event');
    }

    /** @return list<string> */
    public function productFiles(): array
    {
        return $this->files('product');
    }

    public function delete(string $path): void
    {
        $allowed = array_fill_keys([...$this->eventFiles(), ...$this->productFiles()], true);
        if (! isset($allowed[$path])) {
            throw new RuntimeException('Refusing to delete a file outside the legacy public checkout-document directories.');
        }

        if ((is_file($path) || is_link($path)) && ! unlink($path)) {
            throw new RuntimeException('A legacy public checkout document could not be deleted.');
        }
    }

    /** @return list<string> */
    private function files(string $kind): array
    {
        $files = [];

        foreach ($this->directories[$kind] ?? [] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() || $file->isLink()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files, SORT_STRING);

        return array_values(array_unique($files));
    }
}
