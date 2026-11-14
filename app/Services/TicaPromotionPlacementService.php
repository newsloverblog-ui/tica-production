<?php

namespace App\Services;

use App\Models\TicaPromotionCampaign;
use Illuminate\Database\Eloquent\Collection;

class TicaPromotionPlacementService
{
    public function active(string $placement): Collection
    {
        return TicaPromotionCampaign::query()->with(['event.information', 'plan'])
            ->where('status', 'active')->where('payment_status', 'paid')->whereNotNull('approved_at')
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn ($query) => $query->where('placement', $placement)->orWhereHas('plan', fn ($plan) => $plan->where('placement', $placement)))
            ->whereHas('event', fn ($query) => $query->where('status', 1))
            ->orderByDesc('priority')->orderBy('id')->get();
    }

    public function rotating(string $placement): ?TicaPromotionCampaign
    {
        $campaigns = $this->active($placement);
        if ($campaigns->isEmpty()) return null;
        $interval = max(10, (int) data_get($campaigns->first()->plan?->settings, 'rotation_interval_seconds', 30));
        $index = (int) floor(now()->timestamp / $interval) % $campaigns->count();
        return $campaigns->values()->get($index);
    }
}
