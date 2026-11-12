<?php

namespace App\Console\Commands;

use App\Models\Event\Booking;
use App\Models\ShopManagement\ProductOrder;
use App\Services\AuditLogger;
use App\Services\LegacyPublicCheckoutDocuments;
use Illuminate\Console\Command;
use Throwable;

class PurgeLegacyPublicCheckoutDocuments extends Command
{
    protected $signature = 'documents:purge-legacy-public
        {--apply : Permanently remove orphaned files from the legacy public checkout-document directories}';

    protected $description = 'Audit or purge public event and product checkout documents after private-storage migration';

    public function handle(LegacyPublicCheckoutDocuments $documents): int
    {
        $eventReferences = Booking::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereNotNull('invoice')->where('invoice', 'not like', 'private/event-bookings/%'))
                ->orWhere(fn ($query) => $query->whereNotNull('attachmentFile')->where('attachmentFile', 'not like', 'private/event-bookings/%')))
            ->count();
        $productReferences = ProductOrder::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereNotNull('receipt')->where('receipt', 'not like', 'private/product-orders/%'))
                ->orWhere(fn ($query) => $query->whereNotNull('invoice_number')->where('invoice_number', 'not like', 'private/product-orders/%')))
            ->count();

        if ($eventReferences > 0 || $productReferences > 0) {
            $this->error("Refusing to purge while {$eventReferences} event booking(s) and {$productReferences} product order(s) still reference public documents.");
            $this->warn('Run both private-document migration commands and reconcile every failure before purging public copies.');

            return self::FAILURE;
        }

        $eventFiles = $documents->eventFiles();
        $productFiles = $documents->productFiles();
        $files = [...$eventFiles, ...$productFiles];

        if ($files === []) {
            $this->info('No legacy public checkout documents were found.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn(sprintf(
                'Dry run: %d event and %d product checkout document(s) remain publicly accessible. Re-run with --apply only after verifying private copies and backups.',
                count($eventFiles),
                count($productFiles),
            ));

            return self::SUCCESS;
        }

        AuditLogger::record('checkout_documents.legacy_public_purge_started', null, [], [], [
            'event_file_count' => count($eventFiles),
            'product_file_count' => count($productFiles),
        ]);

        try {
            foreach ($files as $file) {
                $documents->delete($file);
            }

            $remaining = count($documents->eventFiles()) + count($documents->productFiles());
            if ($remaining > 0) {
                throw new \RuntimeException('Legacy public checkout documents remain after the purge attempt.');
            }

            AuditLogger::record('checkout_documents.legacy_public_purge_completed', null, [], [], [
                'event_file_count' => count($eventFiles),
                'product_file_count' => count($productFiles),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The purge did not complete. Review protected logs, restore or reconcile the affected file, and rerun the idempotent command.');

            return self::FAILURE;
        }

        $this->info(sprintf('Removed %d legacy public checkout document(s).', count($files)));

        return self::SUCCESS;
    }
}
