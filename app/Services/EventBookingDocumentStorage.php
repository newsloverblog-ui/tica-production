<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Rules\PaymentEvidenceFileRule;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EventBookingDocumentStorage
{
    public function __construct(private readonly LegacyCheckoutDocumentValidator $legacyValidator)
    {
    }

    public function storeAttachment(Booking $booking, UploadedFile $file): string
    {
        $rule = new PaymentEvidenceFileRule();
        if (!$rule->passes('attachment', $file)) {
            throw new RuntimeException($rule->message());
        }

        $extension = PaymentEvidenceFileRule::extension($file);
        $path = $this->directory($booking, 'attachments').'/'.Str::uuid().'.'.$extension;

        if (!$this->disk()->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private'])) {
            throw new RuntimeException('The payment evidence could not be stored securely.');
        }

        return $path;
    }

    public function storeInvoice(Booking $booking, string $contents): string
    {
        $path = $this->directory($booking, 'invoices').'/'.$this->safeBookingNumber($booking).'.pdf';
        if (!$this->disk()->put($path, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException('The event invoice could not be stored securely.');
        }

        return $path;
    }

    public function storeCurrentTickets(Booking $booking, string $contents): string
    {
        $path = $this->directory($booking, 'tickets').'/'.$this->safeBookingNumber($booking).'-tickets.pdf';
        if (!$this->disk()->put($path, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException('The current ticket document could not be stored securely.');
        }

        return $path;
    }

    public function response(Booking $booking, string $type, string $disposition = 'attachment'): Response
    {
        [$value, $downloadName] = $this->documentValue($booking, $type);
        if ($value === '') {
            abort(404);
        }

        abort_if($this->isPrivatePath($value) && !$this->isPrivatePathForBooking($booking, $value), 404);

        if ($this->isPrivatePathForBooking($booking, $value)) {
            abort_unless($this->disk()->exists($value), 404);
            return $this->disk()->response($value, $downloadName, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ], $disposition);
        }

        $legacyPath = $this->legacyPath($type, $value);
        abort_unless(is_file($legacyPath), 404);

        return response()->file($legacyPath, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => $disposition.'; filename="'.$downloadName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function invoiceAttachmentPath(Booking $booking): string
    {
        $value = (string) $booking->invoice;
        if ($value === '') {
            throw new RuntimeException('The booking does not have an invoice.');
        }

        if ($this->isPrivatePath($value) && !$this->isPrivatePathForBooking($booking, $value)) {
            throw new RuntimeException('The booking invoice reference is invalid.');
        }

        if ($this->isPrivatePathForBooking($booking, $value)) {
            if (!$this->disk()->exists($value)) {
                throw new RuntimeException('The booking invoice file is missing.');
            }
            return $this->disk()->path($value);
        }

        $path = $this->legacyPath('invoice', $value);
        if (!is_file($path)) {
            throw new RuntimeException('The booking invoice file is missing.');
        }

        return $path;
    }

    public function isPrivatePath(string $value): bool
    {
        return Str::startsWith($value, 'private/event-bookings/');
    }

    public function isPrivatePathForBooking(Booking $booking, string $value): bool
    {
        return Str::startsWith($value, 'private/event-bookings/'.$booking->getKey().'/');
    }

    public function delete(Booking $booking, string $type): void
    {
        [$value] = $this->documentValue($booking, $type);
        if ($value === '') {
            return;
        }

        if ($this->isPrivatePath($value) && !$this->isPrivatePathForBooking($booking, $value)) {
            throw new RuntimeException('The booking document reference is invalid.');
        }

        if ($this->isPrivatePathForBooking($booking, $value)) {
            $this->disk()->delete($value);
            return;
        }

        $legacyPath = $this->legacyPath($type, $value);
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }
    }

    public function deletePrivatePath(Booking $booking, string $path): void
    {
        if (!$this->isPrivatePathForBooking($booking, $path)) {
            throw new RuntimeException('The booking document path is not owned by this booking.');
        }

        $this->disk()->delete($path);
    }

    public function importLegacy(Booking $booking, string $type): ?array
    {
        $inspection = $this->inspectLegacy($booking, $type);
        if ($inspection === null) {
            return null;
        }

        $source = $inspection['source'];
        $extension = $inspection['extension'];
        $filename = $type === 'invoice' ? $this->safeBookingNumber($booking).'.pdf' : Str::uuid().'.'.$extension;
        $destination = $this->directory($booking, $type === 'invoice' ? 'invoices' : 'attachments').'/'.$filename;
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Legacy {$type} file could not be opened for event booking {$booking->getKey()}.");
        }

        try {
            if (!$this->disk()->put($destination, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException("Legacy {$type} file could not be imported for event booking {$booking->getKey()}.");
            }
        } finally {
            fclose($stream);
        }

        return ['destination' => $destination, 'source' => $source];
    }

    /** @return array{source: string, extension: string}|null */
    public function inspectLegacy(Booking $booking, string $type): ?array
    {
        [$value] = $this->documentValue($booking, $type);
        if ($value === '' || $this->isPrivatePathForBooking($booking, $value)) {
            return null;
        }

        if ($this->isPrivatePath($value)) {
            throw new RuntimeException("Private {$type} path is not owned by event booking {$booking->getKey()}.");
        }

        $source = $this->legacyPath($type, $value);
        if (!is_file($source)) {
            throw new RuntimeException("Legacy {$type} file is missing for event booking {$booking->getKey()}.");
        }

        $extension = $this->legacyValidator->validate($source, $type);

        return ['source' => $source, 'extension' => $extension];
    }

    private function documentValue(Booking $booking, string $type): array
    {
        if ($type === 'invoice') {
            return [(string) $booking->invoice, $this->safeBookingNumber($booking).'.pdf'];
        }
        if ($type === 'attachment') {
            $extension = pathinfo((string) $booking->attachmentFile, PATHINFO_EXTENSION) ?: 'jpg';
            return [(string) $booking->attachmentFile, 'payment-evidence-'.$this->safeBookingNumber($booking).'.'.$extension];
        }
        if ($type === 'tickets') {
            return [
                $this->directory($booking, 'tickets').'/'.$this->safeBookingNumber($booking).'-tickets.pdf',
                $this->safeBookingNumber($booking).'-tickets.pdf',
            ];
        }

        abort(404);
    }

    private function legacyPath(string $type, string $value): string
    {
        $file = basename($value);
        $directory = $type === 'invoice'
            ? 'assets/admin/file/invoices'
            : 'assets/admin/file/attachments';

        return public_path($directory.'/'.$file);
    }

    private function safeBookingNumber(Booking $booking): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $booking->booking_id);
        return $value !== '' ? $value : 'booking-'.$booking->getKey();
    }

    private function directory(Booking $booking, string $type): string
    {
        return 'private/event-bookings/'.$booking->getKey().'/'.$type;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('filesystems.event_booking_documents_disk', 'local'));
    }
}
