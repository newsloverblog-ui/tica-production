<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\EventContent;
use Carbon\Carbon;
use GdImage;
use RuntimeException;

class EventShareCardService
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    public function render(Event $event, EventContent $content): string
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to render event share cards.');
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if (!$canvas instanceof GdImage) {
            throw new RuntimeException('Unable to allocate the event share card canvas.');
        }

        imagealphablending($canvas, true);
        $background = $this->loadBackground($event);
        if ($background) {
            $this->copyCover($canvas, $background);
            imagedestroy($background);
        } else {
            $this->paintFallback($canvas);
        }

        $this->paintOverlays($canvas);
        $this->paintBrand($canvas);
        $this->paintCopy($canvas, $event, $content);

        ob_start();
        imagepng($canvas, null, 6);
        $png = ob_get_clean();
        imagedestroy($canvas);

        if (!is_string($png) || !str_starts_with($png, "\x89PNG")) {
            throw new RuntimeException('Unable to encode the event share card.');
        }

        return $png;
    }

    private function loadBackground(Event $event): ?GdImage
    {
        $thumbnail = basename((string) $event->thumbnail);
        $candidates = [];
        if ($thumbnail !== '') {
            $candidates[] = public_path('assets/admin/img/event/thumbnail/' . $thumbnail);
        }
        $candidates[] = public_path('assets/front/images/home/organizer-live-event-unsplash.jpg');

        foreach ($candidates as $path) {
            $image = $this->loadRaster($path);
            if ($image) {
                return $image;
            }
        }

        return null;
    }

    private function loadRaster(string $path): ?GdImage
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) > 15 * 1024 * 1024) {
            return null;
        }

        $info = @getimagesize($path);
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $mime = (string) ($info['mime'] ?? '');
        if ($width < 1 || $height < 1 || ($width * $height) > 40_000_000) {
            return null;
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        $bytes = @file_get_contents($path);
        if (!is_string($bytes)) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        return $image instanceof GdImage ? $image : null;
    }

    private function copyCover(GdImage $canvas, GdImage $source): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);
        $sourceX = max(0, (int) floor(($sourceWidth - $cropWidth) / 2));
        $sourceY = max(0, (int) floor(($sourceHeight - $cropHeight) / 2));

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            self::WIDTH,
            self::HEIGHT,
            $cropWidth,
            $cropHeight
        );
    }

    private function paintFallback(GdImage $canvas): void
    {
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $progress = $y / self::HEIGHT;
            $color = imagecolorallocate(
                $canvas,
                (int) round(8 + (24 * $progress)),
                (int) round(17 + (22 * $progress)),
                (int) round(38 + (28 * $progress))
            );
            imageline($canvas, 0, $y, self::WIDTH, $y, $color);
        }
    }

    private function paintOverlays(GdImage $canvas): void
    {
        for ($x = 0; $x < self::WIDTH; $x++) {
            $alpha = (int) round(16 + (104 * ($x / self::WIDTH)));
            $overlay = imagecolorallocatealpha($canvas, 3, 9, 22, min(120, $alpha));
            imageline($canvas, $x, 0, $x, self::HEIGHT, $overlay);
        }
        for ($y = 300; $y < self::HEIGHT; $y++) {
            $alpha = (int) round(112 - (68 * (($y - 300) / (self::HEIGHT - 300))));
            $overlay = imagecolorallocatealpha($canvas, 3, 9, 22, max(35, $alpha));
            imageline($canvas, 0, $y, self::WIDTH, $y, $overlay);
        }
    }

    private function paintBrand(GdImage $canvas): void
    {
        $accent = imagecolorallocate($canvas, 254, 155, 89);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 70, 67, 118, 75, $accent);
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        if (!is_file($font)) {
            throw new RuntimeException('The bundled share-card font is unavailable.');
        }
        imagettftext($canvas, 38, 0, 68, 129, $white, $font, 'tica');
        imagefilledellipse($canvas, 172, 119, 10, 10, $accent);
    }

    private function paintCopy(GdImage $canvas, Event $event, EventContent $content): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        $boldFont = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        if (!is_file($font) || !is_file($boldFont)) {
            throw new RuntimeException('The bundled share-card fonts are unavailable.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 220, 225, 235);
        $accent = imagecolorallocate($canvas, 254, 155, 89);
        $title = trim((string) $content->title) ?: 'Discover this event on Tica';
        $lines = $this->wrap($title, $boldFont, 52, 940, 3);
        $y = 330;
        foreach ($lines as $line) {
            imagettftext($canvas, 52, 0, 70, $y, $white, $boldFont, $line);
            $y += 66;
        }

        $date = $this->formatDate($event);
        $location = collect([$content->city, $content->country])->filter()->implode(', ');
        $metadata = collect([$date, $location])->filter()->implode('  •  ');
        if ($metadata !== '') {
            imagettftext($canvas, 22, 0, 72, min(570, $y + 18), $muted, $font, $metadata);
        }
        imagettftext($canvas, 18, 0, 935, 570, $accent, $boldFont, 'tica.ng');
    }

    private function wrap(string $text, string $font, int $size, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line . ' ' . $word);
            $box = imagettfbbox($size, 0, $font, $candidate);
            $width = $box ? abs($box[2] - $box[0]) : 0;
            if ($line !== '' && $width > $maxWidth) {
                $lines[] = $line;
                $line = $word;
                if (count($lines) === $maxLines - 1) {
                    break;
                }
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }

        $consumed = implode(' ', $lines);
        if (mb_strlen($consumed) < mb_strlen(trim($text)) && $lines !== []) {
            $last = array_key_last($lines);
            $lines[$last] = rtrim($lines[$last], '.,;:!? ') . '…';
        }

        return $lines ?: ['Discover this event on Tica'];
    }

    private function formatDate(Event $event): string
    {
        try {
            $value = trim((string) $event->start_date . ' ' . (string) $event->start_time);
            return $value !== '' ? Carbon::parse($value)->format('D, j M Y · g:i A') : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
