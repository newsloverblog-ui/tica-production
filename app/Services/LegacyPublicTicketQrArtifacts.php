<?php

namespace App\Services;

use RuntimeException;

class LegacyPublicTicketQrArtifacts
{
    public function paths(): array
    {
        $directory = rtrim((string) config(
            'tickets.legacy_public_qr_directory',
            public_path('assets/admin/qrcodes')
        ), DIRECTORY_SEPARATOR);
        if ($directory === '' || !is_dir($directory)) {
            return [];
        }

        $paths = glob($directory.DIRECTORY_SEPARATOR.'*.svg') ?: [];
        return array_values(array_filter($paths, fn (string $path): bool => is_file($path) || is_link($path)));
    }

    public function count(): int
    {
        return count($this->paths());
    }

    public function purge(): int
    {
        $paths = $this->paths();
        foreach ($paths as $path) {
            if (!@unlink($path)) {
                throw new RuntimeException('Could not delete legacy public ticket QR artifact: '.$path);
            }
        }

        return count($paths);
    }
}
