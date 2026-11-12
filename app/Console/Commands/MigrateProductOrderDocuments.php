<?php

namespace App\Console\Commands;

use App\Models\ShopManagement\ProductOrder;
use App\Services\AuditLogger;
use App\Services\ProductOrderDocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateProductOrderDocuments extends Command
{
    protected $signature = 'product-orders:migrate-private-documents
        {--apply : Copy legacy public files to private storage and update their order records}
        {--delete-public : Delete legacy public copies after every selected document was imported successfully}';

    protected $description = 'Audit or migrate legacy public product-order receipts and invoices to private storage';

    public function handle(ProductOrderDocumentStorage $documents): int
    {
        if ($this->option('delete-public') && !$this->option('apply')) {
            $this->error('--delete-public requires --apply.');
            return self::INVALID;
        }

        $candidates = ProductOrder::query()
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNotNull('receipt')->where('receipt', 'not like', 'private/product-orders/%');
                })->orWhere(function ($query): void {
                    $query->whereNotNull('invoice_number')->where('invoice_number', 'not like', 'private/product-orders/%');
                });
            });

        $candidateCount = (clone $candidates)->count();
        if ($candidateCount === 0) {
            $this->info('No legacy public product-order documents were found.');
            return self::SUCCESS;
        }

        $this->info(($this->option('apply') ? 'Migrating' : 'Dry run: found').' '.$candidateCount.' product orders with legacy document references.');
        if (!$this->option('apply')) {
            $failures = [];
            $failureCount = 0;
            foreach ($candidates->lazyById(100) as $order) {
                foreach (['invoice', 'receipt'] as $type) {
                    try {
                        $documents->inspectLegacy($order, $type);
                    } catch (Throwable $exception) {
                        $failureCount++;
                        if (count($failures) < 25) {
                            $failures[] = sprintf(
                                'Product order %s (%s): %s',
                                $order->getKey(),
                                $type,
                                mb_substr($exception->getMessage(), 0, 300),
                            );
                        }
                    }
                }
            }

            if ($failureCount > 0) {
                foreach ($failures as $failure) {
                    $this->error($failure);
                }
                if ($failureCount > count($failures)) {
                    $this->error(sprintf('%d additional invalid legacy document(s) were omitted from console output.', $failureCount - count($failures)));
                }
                $this->warn('Dry run failed. Reconcile every missing, disguised, oversized or active-content file before applying the migration.');

                return self::FAILURE;
            }

            $this->warn('Dry run validated every selected file. Run again with --apply; add --delete-public only after a successful import and backup verification.');
            return self::SUCCESS;
        }

        $legacySources = [];
        try {
            foreach ($candidates->lazyById(100) as $candidate) {
                $currentSources = [];
                $currentDestinations = [];
                try {
                    DB::transaction(function () use ($candidate, $documents, &$currentSources, &$currentDestinations): void {
                        $order = ProductOrder::whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
                        $changes = [];

                        foreach (['invoice', 'receipt'] as $type) {
                            $result = $documents->importLegacy($order, $type);
                            if ($result === null) {
                                continue;
                            }
                            $field = $type === 'invoice' ? 'invoice_number' : 'receipt';
                            $changes[$field] = $result['destination'];
                            $currentSources[] = $result['source'];
                            $currentDestinations[] = $result['destination'];
                        }

                        if ($changes !== []) {
                            $before = array_intersect_key($order->getAttributes(), $changes);
                            $order->forceFill($changes)->save();
                            AuditLogger::record('product_order.documents_privatized', $order, $before, $changes);
                        }
                    }, 3);
                } catch (Throwable $exception) {
                    foreach ($currentDestinations as $destination) {
                        try {
                            $documents->deletePrivatePath($candidate, $destination);
                        } catch (Throwable $cleanupException) {
                            report($cleanupException);
                        }
                    }

                    throw $exception;
                }

                array_push($legacySources, ...$currentSources);
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->warn('No public files were deleted. Fix the missing/unreadable source and safely rerun the idempotent command.');
            return self::FAILURE;
        }

        if ($this->option('delete-public')) {
            foreach (array_unique($legacySources) as $source) {
                if (is_file($source) && !@unlink($source)) {
                    $this->error('Could not delete legacy public file: '.$source);
                    return self::FAILURE;
                }
            }
        }

        $this->info('Product-order documents were moved to private storage'.($this->option('delete-public') ? ' and legacy public copies were deleted.' : '.'));
        return self::SUCCESS;
    }
}
