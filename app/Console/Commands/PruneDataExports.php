<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDataExports extends Command
{
    protected $signature = 'exports:prune';
    protected $description = 'Delete expired private export files while retaining their history records';

    public function handle(): int
    {
        $count = 0;
        DataExport::query()
            ->where('expires_at', '<=', now())
            ->where('status', '!=', 'expired')
            ->orderBy('id')
            ->chunkById(100, function ($exports) use (&$count): void {
                foreach ($exports as $export) {
                    if ($export->path) {
                        Storage::disk($export->disk)->delete($export->path);
                    }
                    $before = $export->only(['status', 'expires_at']);
                    $export->forceFill(['status' => 'expired'])->save();
                    AuditLogger::record('data_export.expired', $export, $before, ['status' => 'expired']);
                    $count++;
                }
            });

        $this->info('Expired exports: ' . $count);
        return self::SUCCESS;
    }
}
