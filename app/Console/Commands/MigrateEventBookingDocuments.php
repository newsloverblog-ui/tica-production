<?php

namespace App\Console\Commands;

use App\Models\Event\Booking;
use App\Services\AuditLogger;
use App\Services\EventBookingDocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateEventBookingDocuments extends Command
{
    protected $signature = 'event-bookings:migrate-private-documents
        {--apply : Copy legacy public files to private storage and update booking records}
        {--delete-public : Delete legacy public copies after every selected document was imported successfully}';

    protected $description = 'Audit or migrate legacy public event invoices and offline-payment evidence to private storage';

    public function handle(EventBookingDocumentStorage $documents): int
    {
        if ($this->option('delete-public') && !$this->option('apply')) {
            $this->error('--delete-public requires --apply.');
            return self::INVALID;
        }

        $candidates = Booking::query()
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNotNull('invoice')->where('invoice', 'not like', 'private/event-bookings/%');
                })->orWhere(function ($query): void {
                    $query->whereNotNull('attachmentFile')->where('attachmentFile', 'not like', 'private/event-bookings/%');
                });
            });

        $candidateCount = (clone $candidates)->count();
        if ($candidateCount === 0) {
            $this->info('No legacy public event-booking documents were found.');
            return self::SUCCESS;
        }

        $this->info(($this->option('apply') ? 'Migrating' : 'Dry run: found').' '.$candidateCount.' event bookings with legacy document references.');
        if (!$this->option('apply')) {
            $failures = [];
            $failureCount = 0;
            foreach ($candidates->lazyById(100) as $booking) {
                $duplicateEvidence = $booking->invoice
                    && $booking->attachmentFile
                    && hash_equals((string) $booking->invoice, (string) $booking->attachmentFile)
                    && strtolower(pathinfo((string) $booking->invoice, PATHINFO_EXTENSION)) !== 'pdf';
                foreach (['invoice', 'attachment'] as $type) {
                    if ($type === 'invoice' && $duplicateEvidence) {
                        continue;
                    }
                    try {
                        $documents->inspectLegacy($booking, $type);
                    } catch (Throwable $exception) {
                        $failureCount++;
                        if (count($failures) < 25) {
                            $failures[] = sprintf(
                                'Event booking %s (%s): %s',
                                $booking->getKey(),
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
                        $booking = Booking::whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
                        $changes = [];

                        // A historical checkout bug copied an image evidence filename into
                        // the invoice column. It is not an invoice and must not be imported
                        // or served from the invoice endpoint.
                        $duplicateEvidence = $booking->invoice
                            && $booking->attachmentFile
                            && hash_equals((string) $booking->invoice, (string) $booking->attachmentFile)
                            && strtolower(pathinfo((string) $booking->invoice, PATHINFO_EXTENSION)) !== 'pdf';
                        if ($duplicateEvidence) {
                            $changes['invoice'] = null;
                        } else {
                            $result = $documents->importLegacy($booking, 'invoice');
                            if ($result !== null) {
                                $changes['invoice'] = $result['destination'];
                                $currentSources[] = $result['source'];
                                $currentDestinations[] = $result['destination'];
                            }
                        }

                        $result = $documents->importLegacy($booking, 'attachment');
                        if ($result !== null) {
                            $changes['attachmentFile'] = $result['destination'];
                            $currentSources[] = $result['source'];
                            $currentDestinations[] = $result['destination'];
                        }

                        if ($changes !== []) {
                            $before = array_intersect_key($booking->getAttributes(), $changes);
                            $booking->forceFill($changes)->save();
                            AuditLogger::record('event_booking.documents_privatized', $booking, $before, $changes, [
                                'corrected_duplicate_evidence_reference' => $duplicateEvidence,
                            ]);
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
            $this->warn('No public files were deleted. Fix the missing or unreadable source and rerun this idempotent command.');
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

        $this->info('Event-booking documents were moved to private storage'.($this->option('delete-public') ? ' and legacy public copies were deleted.' : '.'));
        return self::SUCCESS;
    }
}
