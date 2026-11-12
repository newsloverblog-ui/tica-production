<?php

namespace App\Console\Commands;

use App\Services\LegacyPublicTicketQrArtifacts;
use Illuminate\Console\Command;

class PurgeLegacyPublicTicketQrArtifacts extends Command
{
    protected $signature = 'tickets:purge-public-qr-artifacts
        {--apply : Permanently delete legacy public SVG ticket QR artifacts}';

    protected $description = 'Audit or remove obsolete reusable ticket QR SVG files from the public web root';

    public function handle(LegacyPublicTicketQrArtifacts $artifacts): int
    {
        $count = $artifacts->count();
        if ($count === 0) {
            $this->info('No legacy public ticket QR artifacts were found.');
            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->warn("Dry run: found {$count} legacy public ticket QR artifact(s). Run again with --apply after confirming private invoices are available.");
            return self::SUCCESS;
        }

        $purged = $artifacts->purge();
        $this->info("Deleted {$purged} legacy public ticket QR artifact(s).");

        return self::SUCCESS;
    }
}
