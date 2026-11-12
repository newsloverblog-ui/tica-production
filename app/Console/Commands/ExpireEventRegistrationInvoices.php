<?php

namespace App\Console\Commands;

use App\Services\EventRegistrationInvoiceService;
use Illuminate\Console\Command;

class ExpireEventRegistrationInvoices extends Command
{
    protected $signature = 'registrations:expire-invoices {--limit=200}';
    protected $description = 'Expire overdue corporate registration invoices and release their ticket reservations';

    public function handle(EventRegistrationInvoiceService $invoices): int
    {
        $count = $invoices->expireOverdue(max(1, (int) $this->option('limit')));
        $this->info("Expired {$count} corporate registration invoice(s).");
        return self::SUCCESS;
    }
}
