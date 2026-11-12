<?php

namespace App\Jobs;

use App\Models\SubscriberCampaign;
use App\Models\SubscriberCampaignDelivery;
use App\Services\SubscriberCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchSubscriberCampaign implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $uniqueFor = 3600;

    public function __construct(public int $campaignId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'subscriber-campaign-dispatch:'.$this->campaignId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(SubscriberCampaignService $campaigns): void
    {
        $campaign = SubscriberCampaign::query()->findOrFail($this->campaignId);
        if (in_array($campaign->status, ['completed', 'partial_failed'], true)) {
            return;
        }

        $campaign->forceFill([
            'status' => 'processing',
            'started_at' => $campaign->started_at ?: now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            SubscriberCampaignDelivery::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->select('id')
                ->orderBy('id')
                ->chunkById(500, function ($deliveries): void {
                    foreach ($deliveries as $delivery) {
                        SendSubscriberCampaignDelivery::dispatch($delivery->id);
                    }
                });

            $campaigns->refreshStatus($campaign->id);
        } catch (Throwable $exception) {
            $this->restorePendingCampaign($exception);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->restorePendingCampaign($exception);
    }

    private function restorePendingCampaign(Throwable $exception): void
    {
        $campaign = SubscriberCampaign::query()->find($this->campaignId);
        if (!$campaign || in_array($campaign->status, ['completed', 'partial_failed'], true)) {
            return;
        }

        $campaign->forceFill([
            'status' => 'pending',
            'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            'completed_at' => null,
        ])->save();
    }
}
