<?php

namespace App\Console\Commands;

use App\Services\DuplicateAccountService;
use Illuminate\Console\Command;

/** Lists emails that exist as both a customer and an organiser. Read-only. */
class ListDuplicateAccounts extends Command
{
    protected $signature = 'accounts:duplicates {--json : Print the list as JSON}';

    protected $description = 'List emails registered as both a customer and an organizer (changes nothing)';

    public function handle(DuplicateAccountService $duplicates): int
    {
        $rows = $duplicates->find();
        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        if (!$rows) {
            $this->info('No duplicate accounts. Every email belongs to one account type.');

            return self::SUCCESS;
        }

        $this->table(
            ['Email', 'Customer (created, login)', 'Orders', 'Tickets', 'Organizer (created, login)', 'Events', 'Balance', 'Payouts'],
            array_map(fn (array $r) => [
                $r['email'],
                '#' . $r['customer']['id'] . ' ' . $r['customer']['created_at'] . ', ' . $r['customer']['login'],
                $r['customer']['orders'],
                $r['customer']['tickets'],
                '#' . $r['organizer']['id'] . ' ' . $r['organizer']['created_at'] . ', ' . $r['organizer']['login'],
                $r['organizer']['events'],
                number_format($r['organizer']['balance'], 2),
                $r['organizer']['payouts'],
            ], $rows)
        );
        $this->line(count($rows) . ' duplicate ' . (count($rows) === 1 ? 'email' : 'emails') . '. Resolve them in Admin > Organizers > Duplicate accounts.');

        return self::SUCCESS;
    }
}
