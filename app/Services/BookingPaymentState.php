<?php

namespace App\Services;

class BookingPaymentState
{
    private const FINANCIAL_STATUSES = ['completed', 'free', '1'];

    public static function canManuallyTransition($from, $to): bool
    {
        return (string) $from === 'pending'
            && in_array((string) $to, ['completed', 'rejected'], true);
    }

    public static function canDelete($status): bool
    {
        return !in_array((string) $status, self::FINANCIAL_STATUSES, true);
    }
}

