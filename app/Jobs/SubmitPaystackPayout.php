<?php

namespace App\Jobs;

use App\Services\PayoutTransferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitPaystackPayout implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $uniqueFor = 1800;

    public function __construct(public int $transferId)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return 'paystack-payout:' . $this->transferId;
    }

    public function handle(PayoutTransferService $service): void
    {
        $service->submit($this->transferId);
    }
}
