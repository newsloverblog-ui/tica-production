<?php

namespace App\Services;

use App\Models\Event\Coupon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use JsonException;

final class EventCouponService
{
    public function discount(
        Coupon $coupon,
        int $eventId,
        float $subtotal,
        float $earlyBirdDiscount,
        ?CarbonInterface $now = null
    ): ?float {
        $discountableTotal = round(max(0, $subtotal - max(0, $earlyBirdDiscount)), 2);
        if ($eventId < 1 || $discountableTotal <= 0) {
            return null;
        }

        try {
            $startsAt = CarbonImmutable::parse($coupon->start_date)->startOfDay();
            $endsAt = CarbonImmutable::parse($coupon->end_date)->endOfDay();
            $current = ($now ?? CarbonImmutable::now())->toImmutable();
        } catch (\Throwable) {
            return null;
        }

        if ($endsAt->lessThan($startsAt) || $current->lessThan($startsAt) || $current->greaterThan($endsAt)) {
            return null;
        }

        $scope = trim((string) $coupon->events);
        if ($scope !== '') {
            try {
                $eventIds = json_decode($scope, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }

            if (!is_array($eventIds)) {
                return null;
            }

            $eventIds = array_values(array_unique(array_filter(
                array_map(static fn ($value): int => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : 0, $eventIds),
                static fn (int $value): bool => $value > 0
            )));
            if ($eventIds !== [] && !in_array($eventId, $eventIds, true)) {
                return null;
            }
        }

        if (isset($coupon->max_uses) && $coupon->max_uses !== null && (int) $coupon->used_count >= (int) $coupon->max_uses) {
            return null;
        }

        $value = (float) $coupon->value;
        if (!is_finite($value) || $value <= 0) {
            return null;
        }

        $amount = match ((string) $coupon->type) {
            'fixed' => min($value, $discountableTotal),
            'percentage' => $value <= 100 ? ($discountableTotal * $value) / 100 : null,
            default => null,
        };

        return $amount === null ? null : round(min($discountableTotal, max(0, $amount)), 2);
    }
}
