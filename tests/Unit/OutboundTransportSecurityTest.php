<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class OutboundTransportSecurityTest extends TestCase
{
    public function test_application_php_never_disables_tls_certificate_verification(): void
    {
        foreach ($this->applicationPhpFiles() as $file) {
            $source = (string) file_get_contents($file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                '/CURLOPT_SSL_VERIFYPEER\s*,\s*false\b/i',
                $source,
                $file->getPathname() . ' disables TLS peer verification.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/CURLOPT_SSL_VERIFYHOST\s*,\s*(?:0|false)\b/i',
                $source,
                $file->getPathname() . ' disables TLS hostname verification.'
            );
            $this->assertStringNotContainsString(
                'withoutVerifying()',
                $source,
                $file->getPathname() . ' disables HTTP client certificate verification.'
            );
        }
    }

    public function test_legacy_flutterwave_callback_requires_a_matching_reference_and_strict_success(): void
    {
        $source = (string) file_get_contents(app_path(
            'Http/Controllers/FrontEnd/Shop/PaymentGateway/FlutterwaveController.php'
        ));

        $this->assertStringContainsString('hash_equals($ref, $callbackReference)', $source);
        $this->assertStringContainsString("(\$resp['status'] ?? null) === 'success'", $source);
        $this->assertStringNotContainsString("\$resp['status'] = \"success\"", $source);
    }

    public function test_every_raw_curl_integration_declares_bounded_timeouts(): void
    {
        foreach ($this->applicationPhpFiles() as $file) {
            $source = (string) file_get_contents($file->getPathname());

            if (!str_contains($source, 'curl_exec(')) {
                continue;
            }

            $this->assertStringContainsString(
                'CURLOPT_CONNECTTIMEOUT',
                $source,
                $file->getPathname() . ' has an unbounded cURL connection deadline.'
            );
            $this->assertStringContainsString(
                'CURLOPT_TIMEOUT',
                $source,
                $file->getPathname() . ' has an unbounded cURL request deadline.'
            );
        }
    }

    /** @return iterable<SplFileInfo> */
    private function applicationPhpFiles(): iterable
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
