<?php

namespace App\Console\Commands;

use App\Models\TicaPromotionCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTicaPromotions extends Command
{
    protected $signature = 'promotions:sync';
    protected $description = 'Activate and expire admin-approved TICA promotions.';

    public function handle(): int
    {
        TicaPromotionCampaign::query()
            ->whereIn('status', ['approved', 'scheduled'])
            ->whereNotNull('approved_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    DB::transaction(function () use ($campaign): void {
                        $locked = TicaPromotionCampaign::query()->with('plan')->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                        $locked->status = 'active';
                        $locked->save();
                        if (in_array(optional($locked->plan)->placement, ['featured_event', 'sponsored_event'], true)) {
                            $locked->event()->update(['is_featured' => 'yes']);
                        }
                    }, 3);
                }
            });

        TicaPromotionCampaign::query()
            ->where(function ($query): void {
                $query->where('status', 'active')->where('ends_at', '<=', now())
                    ->orWhere('status', 'cancellation_requested');
            })
            ->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    DB::transaction(function () use ($campaign): void {
                        $locked = TicaPromotionCampaign::query()->with('plan')->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                        $locked->status = $locked->status === 'cancellation_requested' ? 'cancelled' : 'completed';
                        $locked->save();
                        if (in_array(optional($locked->plan)->placement, ['featured_event', 'sponsored_event'], true)) {
                            $hasOther = TicaPromotionCampaign::query()->where('event_id', $locked->event_id)->where('status', 'active')->where('id', '!=', $locked->id)
                                ->whereHas('plan', fn ($query) => $query->whereIn('placement', ['featured_event', 'sponsored_event']))->exists();
                            if (!$hasOther) {
                                $locked->event()->update(['is_featured' => 'no']);
                            }
                        }
                    }, 3);
                }
            });

        return self::SUCCESS;
    }
}
