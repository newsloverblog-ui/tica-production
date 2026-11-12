<?php

namespace App\Services;

use App\Jobs\DispatchPushNotificationCampaign;
use App\Models\Guest;
use App\Models\PushNotificationCampaign;
use App\Models\PushNotificationCampaignDelivery;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PushNotificationCampaignService
{
    public function create(
        string $title,
        ?string $message,
        string $buttonName,
        string $buttonUrl,
        ?int $adminId
    ): PushNotificationCampaign {
        $title = trim($title);
        $message = trim((string) $message);
        $buttonName = trim($buttonName);
        $buttonUrl = trim($buttonUrl);

        if ($title === '' || mb_strlen($title) > 255
            || mb_strlen($message) > 1000
            || $buttonName === '' || mb_strlen($buttonName) > 80
            || filter_var($buttonUrl, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($buttonUrl, PHP_URL_SCHEME)), ['https', 'http'], true)) {
            throw new DomainException('Valid push-notification content and an HTTP(S) action URL are required.');
        }

        if (!Guest::query()->whereHas('pushSubscriptions')->exists()) {
            throw new DomainException('There are no active browser subscriptions to receive this notification.');
        }

        return DB::transaction(function () use ($title, $message, $buttonName, $buttonUrl, $adminId): PushNotificationCampaign {
            $campaign = PushNotificationCampaign::create([
                'public_id' => (string) Str::uuid(),
                'created_by_admin_id' => $adminId,
                'title' => $title,
                'message' => $message,
                'button_name' => $buttonName,
                'button_url' => $buttonUrl,
                'status' => 'pending',
            ]);

            $total = 0;
            Guest::query()
                ->whereHas('pushSubscriptions')
                ->select('id')
                ->orderBy('id')
                ->chunkById(500, function ($guests) use ($campaign, &$total): void {
                    $now = now();
                    $rows = [];
                    foreach ($guests as $guest) {
                        $rows[] = [
                            'campaign_id' => $campaign->id,
                            'guest_id' => $guest->id,
                            'status' => 'pending',
                            'attempts' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    PushNotificationCampaignDelivery::query()->insert($rows);
                    $total += count($rows);
                });

            $campaign->forceFill(['total_count' => $total])->save();
            AuditLogger::record('push_notification_campaign.queued', $campaign, [], [
                'status' => 'pending', 'recipient_count' => $total,
            ]);

            DB::afterCommit(fn () => $this->dispatchCampaign($campaign->id));

            return $campaign;
        }, 3);
    }

    protected function dispatchCampaign(int $campaignId): void
    {
        $campaign = PushNotificationCampaign::query()->find($campaignId);
        if (!$campaign || $campaign->status !== 'pending') {
            return;
        }

        $campaign->forceFill([
            'dispatch_attempts' => (int) $campaign->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->pushCampaignJob($campaign->id);
        } catch (Throwable $exception) {
            $campaign->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
        }
    }

    protected function pushCampaignJob(int $campaignId): void
    {
        DispatchPushNotificationCampaign::dispatch($campaignId);
    }

    public function refreshStatus(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId): void {
            $campaign = PushNotificationCampaign::query()->lockForUpdate()->find($campaignId);
            if (!$campaign) {
                return;
            }

            $counts = PushNotificationCampaignDelivery::query()
                ->where('campaign_id', $campaign->id)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
            $sent = (int) ($counts['sent'] ?? 0);
            $failed = (int) ($counts['failed'] ?? 0);
            $active = (int) ($counts['pending'] ?? 0) + (int) ($counts['processing'] ?? 0);

            $campaign->forceFill([
                'status' => $active > 0 ? 'processing' : ($failed > 0 ? 'partial_failed' : 'completed'),
                'sent_count' => $sent,
                'failed_count' => $failed,
                'started_at' => $campaign->started_at ?: now(),
                'completed_at' => $active === 0 ? now() : null,
            ])->save();
        }, 3);
    }
}
