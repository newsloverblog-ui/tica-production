<?php

namespace App\Console\Commands;

use App\Models\WebhookReceipt;
use Illuminate\Console\Command;

class PruneWebhookReceipts extends Command
{
    protected $signature = 'ops:prune-webhook-receipts {--json : Emit machine-readable JSON}';
    protected $description = 'Delete privacy-safe webhook receipt metadata after its retention window';

    public function handle(): int
    {
        $retentionDays = max(30, (int) config('operations.webhook_receipt_retention_days', 90));
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        WebhookReceipt::query()
            ->where('received_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($receipts) use (&$deleted): void {
                $ids = $receipts->pluck('id');
                $deleted += WebhookReceipt::query()->whereIn('id', $ids)->delete();
            });

        $result = [
            'deleted' => $deleted,
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Webhook receipts deleted: {$deleted} (retention: {$retentionDays} days)");
        }

        return self::SUCCESS;
    }
}
