<?php

namespace App\Services;

use App\Models\EventRegistrationInvoice;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EventRegistrationInvoiceDocumentService
{
    public function store(EventRegistrationInvoice $invoice, string $contents): string
    {
        $path = $this->path($invoice);
        if (!$this->disk()->put($path, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException('The corporate registration invoice could not be stored securely.');
        }

        return $path;
    }

    public function response(EventRegistrationInvoice $invoice, string $disposition = 'inline'): Response
    {
        $path = (string) $invoice->invoice_path;
        abort_if($path === '' || $path !== $this->path($invoice), 404);
        abort_unless($this->disk()->exists($path), 404);

        return $this->disk()->response($path, $invoice->invoice_number.'.pdf', [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }

    public function delete(EventRegistrationInvoice $invoice): void
    {
        $path = (string) $invoice->invoice_path;
        if ($path !== '' && $path === $this->path($invoice)) {
            $this->disk()->delete($path);
        }
    }

    private function path(EventRegistrationInvoice $invoice): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]/', '', $invoice->invoice_number) ?: 'invoice-'.$invoice->id;
        return 'private/event-registration-invoices/'.$invoice->id.'/'.$number.'.pdf';
    }

    private function disk(): Filesystem
    {
        $name = (string) config('registration.invoice_disk', 'local');
        $configuration = (array) config('filesystems.disks.'.$name, []);
        if ($name === 'public' || $configuration === [] || ($configuration['visibility'] ?? 'private') === 'public') {
            throw new RuntimeException('Corporate registration invoice storage must be private.');
        }

        return Storage::disk($name);
    }
}
