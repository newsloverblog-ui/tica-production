<?php

namespace App\Console\Commands;

use App\Models\EventGuestImport;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneEventGuestImports extends Command
{
    protected $signature = 'guests:prune-imports';
    protected $description = 'Delete expired private guest-import sources while retaining non-PII results';

    public function handle(): int
    {
        $count = 0;
        EventGuestImport::query()->where('expires_at', '<=', now())->whereNull('source_deleted_at')
            ->orderBy('id')->chunkById(100, function ($imports) use (&$count): void {
                foreach ($imports as $import) {
                    Storage::disk($import->disk)->delete($import->path);
                    $before = $import->only(['status', 'source_deleted_at']);
                    $updates = ['source_deleted_at' => now()];
                    if (in_array($import->status, ['queued', 'processing', 'failed'], true)) $updates['status'] = 'expired';
                    $import->forceFill($updates)->save();
                    AuditLogger::record('event.guest_import_source_pruned', $import, $before,
                        ['status' => $import->status, 'source_deleted' => true], ['event_id' => $import->event_id]);
                    $count++;
                }
            });
        $this->info('Expired guest import sources: '.$count);
        return self::SUCCESS;
    }
}
