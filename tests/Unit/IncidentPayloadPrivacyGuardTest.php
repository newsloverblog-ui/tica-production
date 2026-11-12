<?php

namespace Tests\Unit;

use Tests\TestCase;

class IncidentPayloadPrivacyGuardTest extends TestCase
{
    public function test_operational_alert_paths_do_not_embed_raw_failure_diagnostics(): void
    {
        $deliveryMonitor = $this->source('Console/Commands/MonitorBookingDeliveries.php');
        $this->assertStringNotContainsString("'last_error' => $", $deliveryMonitor);
        $this->assertSame(14, substr_count($deliveryMonitor, "'failure_recorded' =>"));

        foreach ([
            'Services/GatewayLedgerBackfillService.php',
            'Services/OfflineLedgerBackfillService.php',
            'Services/OfflineProductLedgerBackfillService.php',
        ] as $path) {
            $source = $this->source($path);
            $this->assertStringNotContainsString("'error' => \$exception->getMessage()", $source);
            $this->assertStringContainsString("'exception_type' => \$exception::class", $source);
            $this->assertStringContainsString("'exception_code' => \$exception->getCode()", $source);
        }

        $cancellation = $this->source('Services/EventCancellationService.php');
        $this->assertStringNotContainsString("'reason' => \$exception->getMessage()", $cancellation);
        $this->assertStringContainsString("'exception_type' => \$exception::class", $cancellation);

        $refund = $this->source('Services/PaymentRefundService.php');
        $this->assertStringNotContainsString("'reason' => \$message", $refund);
        $this->assertStringContainsString("'failure_recorded' => filled(\$message)", $refund);

        $productRefund = $this->source('Services/ProductGatewayRefundService.php');
        $this->assertStringNotContainsString("'reason' => \$message", $productRefund);
        $this->assertStringContainsString("'failure_recorded' => filled(\$message)", $productRefund);

        foreach ([
            'Http/Middleware/TrackWebhookReceipt.php',
            'Services/FunnelAnalyticsService.php',
        ] as $path) {
            $source = $this->source($path);
            $this->assertStringNotContainsString("\$exception->getMessage()", $source);
            $this->assertStringContainsString("'exception_type' => \$exception::class", $source);
            $this->assertStringContainsString("'exception_code' => \$exception->getCode()", $source);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(app_path($path));
        $this->assertIsString($source);

        return $source;
    }
}
