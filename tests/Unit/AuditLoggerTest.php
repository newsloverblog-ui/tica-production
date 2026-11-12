<?php

namespace Tests\Unit;

use App\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

class AuditLoggerTest extends TestCase
{
    public function test_sensitive_values_are_redacted_recursively(): void
    {
        $sanitized = AuditLogger::sanitize([
            'status' => 'completed',
            'token' => 'private-token',
            'nested' => [
                'account_number' => '0123456789',
                'amount' => 5000,
            ],
        ]);

        $this->assertSame('completed', $sanitized['status']);
        $this->assertSame('[REDACTED]', $sanitized['token']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['account_number']);
        $this->assertSame(5000, $sanitized['nested']['amount']);
    }
}

