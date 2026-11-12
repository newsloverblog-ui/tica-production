<?php

namespace App\Jobs;

use App\Services\QueueHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueHeartbeat implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $uniqueFor = 120;

    public function __construct(public string $queueName)
    {
        $this->onQueue($queueName);
    }

    public function uniqueId(): string
    {
        return (string) config('queue.default') . ':' . $this->queueName;
    }

    public function handle(QueueHealthService $health): void
    {
        $health->record((string) config('queue.default'), $this->queueName);
    }
}
