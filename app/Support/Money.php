<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toMinor(int|float|string $amount): int
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }

        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function fromMinor(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
