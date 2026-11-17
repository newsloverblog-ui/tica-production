<?php

namespace App\Console\Commands;

use App\Services\FinancialLedgerService;
use Illuminate\Console\Command;

class ReleaseOrganizerEarnings extends Command
{
    protected $signature = 'finance:release-earnings {--limit=200}';
    protected $description = 'Move eligible organizer earnings from pending to available.';

    public function handle(FinancialLedgerService $ledger): int
    {
        $summary = $ledger->releaseDue((int) $this->option('limit'));
        $this->table(['Checked', 'Released', 'Held'], [[
            $summary['checked'], $summary['released'], $summary['held'],
        ]]);
        return self::SUCCESS;
    }
}
