<?php

namespace App\Jobs;

use App\Models\Guest;
use App\Models\PushNotificationCampaignDelivery;
use App\Notifications\PushNotification;
use App\Services\PushNotificationCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendPushNotificationCampaignDelivery implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
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
        return 'push-notification-campaign-delivery:'.$this->deliveryId;
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function middleware(): array
    {
        return [
            new RateLimited('push-notification-campaign'),
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(600),
        ];
    }

    public function handle(PushNotificationCampaignService $campaigns): void
    {
        $delivery = DB::transaction(function (): ?PushNotificationCampaignDelivery {
            $locked = PushNotificationCampaignDelivery::query()
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

        $guest = Guest::query()->with('pushSubscriptions')->find($delivery->guest_id);
        if (!$guest || $guest->pushSubscriptions->isEmpty()) {
            $this->markFailed($delivery, 'The browser subscription is no longer active.');
            $campaigns->refreshStatus($delivery->campaign_id);
            return;
        }

        try {
            Notification::sendNow($guest, new PushNotification(
                (string) $delivery->campaign->title,
                (string) $delivery->campaign->message,
                (string) $delivery->campaign->button_name,
                (string) $delivery->campaign->button_url,
            ));

            PushNotificationCampaignDelivery::query()->whereKey($delivery->id)->update([
                'status' => 'sent', 'sent_at' => now(), 'processing_at' => null,
                'last_error' => null, 'updated_at' => now(),
            ]);
            $campaigns->refreshStatus($delivery->campaign_id);
        } catch (Throwable $exception) {
            $locked = PushNotificationCampaignDelivery::query()->find($delivery->id);
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
        $delivery = PushNotificationCampaignDelivery::query()->find($this->deliveryId);
        if (!$delivery || $delivery->status === 'sent') {
            return;
        }
        $this->markFailed($delivery, mb_substr($exception->getMessage(), 0, 2000));
        app(PushNotificationCampaignService::class)->refreshStatus($delivery->campaign_id);
    }

    private function markFailed(PushNotificationCampaignDelivery $delivery, string $error): void
    {
        $delivery->forceFill([
            'status' => 'failed',
            'processing_at' => null,
            'failed_at' => now(),
            'last_error' => $error,
        ])->save();
    }
}
