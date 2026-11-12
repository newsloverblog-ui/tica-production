<?php

namespace App\Jobs;

use App\Models\SubscriberCampaignDelivery;
use App\Services\ConfiguredMailer;
use App\Services\SubscriberCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendSubscriberCampaignDelivery implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'subscriber-campaign-delivery:'.$this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [
            new RateLimited('subscriber-campaign-mail'),
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(900),
        ];
    }

    public function handle(ConfiguredMailer $mailer, SubscriberCampaignService $campaigns): void
    {
        $delivery = DB::transaction(function (): ?SubscriberCampaignDelivery {
            $locked = SubscriberCampaignDelivery::query()
                ->with('campaign')
                ->lockForUpdate()
                ->findOrFail($this->deliveryId);
            if (in_array($locked->status, ['sent', 'failed'], true)) {
                return null;
            }
            $locked->forceFill([
                'status' => 'processing',
                'attempts' => (int) $locked->attempts + 1,
                'processing_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked;
        }, 3);

        if (!$delivery) {
            return;
        }

        try {
            $mailer->sendHtml(
                (string) $delivery->recipient_email,
                (string) $delivery->campaign->subject,
                (string) $delivery->campaign->body
            );

            SubscriberCampaignDelivery::query()->whereKey($delivery->id)->update([
                'status' => 'sent', 'sent_at' => now(), 'processing_at' => null,
                'last_error' => null, 'updated_at' => now(),
            ]);
            $campaigns->refreshStatus($delivery->campaign_id);
        } catch (Throwable $exception) {
            $locked = SubscriberCampaignDelivery::query()->find($delivery->id);
            if ($locked && $locked->status !== 'sent') {
                $locked->forceFill([
                    'status' => 'pending',
                    'processing_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->save();
            }
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $delivery = SubscriberCampaignDelivery::query()->find($this->deliveryId);
        if (!$delivery || $delivery->status === 'sent') {
            return;
        }
        $delivery->forceFill([
            'status' => 'failed',
            'processing_at' => null,
            'failed_at' => now(),
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ])->save();
        app(SubscriberCampaignService::class)->refreshStatus($delivery->campaign_id);
    }
}
