<?php

namespace App\Console\Commands;

use App\Models\ScannerOfflineManifest;
use App\Models\ScannerOfflineManifestItem;
use App\Models\ScannerOfflineSyncItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneScannerOfflineData extends Command
{
    protected $signature = 'scanner:prune-offline-data';

    protected $description = 'Expire offline scanner manifests and prune records after the configured audit-retention window';

    public function handle(): int
    {
        $expired = ScannerOfflineManifest::query()
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $cutoff = now()->subDays((int) config('tickets.offline_retention_days', 180));
        [$syncItems, $manifestItems, $manifests] = DB::transaction(function () use ($cutoff) {
            $manifestIds = ScannerOfflineManifest::query()
                ->where('expires_at', '<', $cutoff)
                ->pluck('id');
            if ($manifestIds->isEmpty()) {
                return [0, 0, 0];
            }

            $syncItems = ScannerOfflineSyncItem::query()
                ->whereIn('scanner_offline_manifest_id', $manifestIds)
                ->delete();
            $manifestItems = ScannerOfflineManifestItem::query()
                ->whereIn('scanner_offline_manifest_id', $manifestIds)
                ->delete();
            $manifests = ScannerOfflineManifest::query()->whereIn('id', $manifestIds)->delete();

            return [$syncItems, $manifestItems, $manifests];
        });

        $this->info("Expired {$expired} manifest(s); pruned {$manifests} manifest(s), {$manifestItems} admission hash(es), and {$syncItems} sync receipt(s).");

        return self::SUCCESS;
    }
}
