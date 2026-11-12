<?php

namespace App\Services;

use App\Rules\PaymentEvidenceFileRule;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class LegacyCheckoutDocumentValidator
{
    private const MAX_PDF_BYTES = 10 * 1024 * 1024;

    public function validate(string $path, string $type): string
    {
        if ($type === 'invoice') {
            return $this->validateInvoice($path);
        }

        if (! in_array($type, ['attachment', 'receipt'], true)) {
            throw new RuntimeException('Unsupported legacy checkout document type.');
        }

        $file = new UploadedFile($path, basename($path), null, UPLOAD_ERR_OK, true);
        $rule = new PaymentEvidenceFileRule();
        if (! $rule->passes($type, $file)) {
            throw new RuntimeException($rule->message());
        }

        return PaymentEvidenceFileRule::extension($file);
    }

    private function validateInvoice(string $path): string
    {
        $size = is_file($path) ? filesize($path) : false;
        if ($size === false || $size < 1 || $size > self::MAX_PDF_BYTES) {
            throw new RuntimeException('Legacy invoice must be a non-empty PDF no larger than 10 MB.');
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $file = new UploadedFile($path, basename($path), null, UPLOAD_ERR_OK, true);
        if ($extension !== 'pdf' || strtolower((string) $file->getMimeType()) !== 'application/pdf') {
            throw new RuntimeException('Legacy invoice must be a genuine PDF whose extension matches its contents.');
        }

        $contents = file_get_contents($path);
        if (! is_string($contents) || ! str_starts_with(ltrim(substr($contents, 0, 1024)), '%PDF-')) {
            throw new RuntimeException('Legacy invoice does not have a valid PDF header.');
        }
        if (preg_match('/\/(JavaScript|JS|Launch|OpenAction|EmbeddedFile)\b/i', $contents)) {
            throw new RuntimeException('Active or embedded content is not permitted in a legacy invoice.');
        }

        return 'pdf';
    }
}
