<?php

namespace App\Console\Commands;

use App\Services\DataExportScheduleService;
use Illuminate\Console\Command;

class RunDataExportSchedules extends Command
{
    protected $signature = 'exports:run-schedules {--limit=100}';
    protected $description = 'Queue due actor-owned recurring data exports';

    public function handle(DataExportScheduleService $schedules): int
    {
        $result = $schedules->runDue((int) $this->option('limit'));
        $this->info("Queued: {$result['queued']}; failed: {$result['failed']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
