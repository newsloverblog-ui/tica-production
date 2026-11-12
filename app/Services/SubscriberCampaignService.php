<?php

namespace App\Services;

use App\Jobs\DispatchSubscriberCampaign;
use App\Models\Subscriber;
use App\Models\SubscriberCampaign;
use App\Models\SubscriberCampaignDelivery;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SubscriberCampaignService
{
    public function create(string $subject, string $body, ?int $adminId): SubscriberCampaign
    {
        $subject = trim($subject);
        if ($subject === '' || mb_strlen($subject) > 255 || trim(strip_tags($body)) === '') {
            throw new DomainException('A valid campaign subject and message are required.');
        }

        if (!Subscriber::query()->exists()) {
            throw new DomainException('There are no subscribers to receive this campaign.');
        }

        return DB::transaction(function () use ($subject, $body, $adminId): SubscriberCampaign {
            $campaign = SubscriberCampaign::create([
                'public_id' => (string) Str::uuid(),
                'created_by_admin_id' => $adminId,
                'subject' => $subject,
                'body' => $body,
                'status' => 'pending',
            ]);

            $total = 0;
            Subscriber::query()
                ->select(['id', 'email_id'])
                ->orderBy('id')
                ->chunkById(500, function ($subscribers) use ($campaign, &$total): void {
                    $now = now();
                    $rows = [];
                    foreach ($subscribers as $subscriber) {
                        $email = mb_strtolower(trim((string) $subscriber->email_id));
                        $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
                        $rows[] = [
                            'campaign_id' => $campaign->id,
                            'subscriber_id' => $subscriber->id,
                            'recipient_hash' => hash_hmac('sha256', $email, (string) config('app.key')),
                            'recipient_email' => Crypt::encryptString($email),
                            'status' => $valid ? 'pending' : 'failed',
                            'attempts' => 0,
                            'last_error' => $valid ? null : Crypt::encryptString('The subscriber email address is invalid.'),
                            'failed_at' => $valid ? null : $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    SubscriberCampaignDelivery::query()->insert($rows);
                    $total += count($rows);
                });

            $campaign->forceFill(['total_count' => $total])->save();
            AuditLogger::record('subscriber_campaign.queued', $campaign, [], [
                'status' => 'pending', 'recipient_count' => $total,
            ]);

            DB::afterCommit(fn () => $this->dispatchCampaign($campaign->id));

            return $campaign;
        }, 3);
    }

    protected function dispatchCampaign(int $campaignId): void
    {
        $campaign = SubscriberCampaign::query()->find($campaignId);
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
        DispatchSubscriberCampaign::dispatch($campaignId);
    }

    public function refreshStatus(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId): void {
            $campaign = SubscriberCampaign::query()->lockForUpdate()->find($campaignId);
            if (!$campaign) {
                return;
            }

            $counts = SubscriberCampaignDelivery::query()
                ->where('campaign_id', $campaign->id)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
            $sent = (int) ($counts['sent'] ?? 0);
            $failed = (int) ($counts['failed'] ?? 0);
            $active = (int) ($counts['pending'] ?? 0) + (int) ($counts['processing'] ?? 0);
            $status = $active > 0 ? 'processing' : ($failed > 0 ? 'partial_failed' : 'completed');

            $campaign->forceFill([
                'status' => $status,
                'sent_count' => $sent,
                'failed_count' => $failed,
                'started_at' => $campaign->started_at ?: now(),
                'completed_at' => $active === 0 ? now() : null,
            ])->save();
        }, 3);
    }
}
