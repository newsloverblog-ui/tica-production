<?php

namespace App\Services;

use App\Models\Earning;
use Illuminate\Support\Facades\Schema;
use LogicException;

class PlatformEarningService
{
    /**
     * Return the one authoritative platform-earnings row under a write lock.
     *
     * Financial paths must fail closed when this invariant is missing or
     * ambiguous; silently creating or selecting an arbitrary row can split the
     * platform ledger during concurrent payment/refund processing.
     */
    public function lock(): Earning
    {
        $rows = Earning::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(2)
            ->get();

        if ($rows->count() !== 1) {
            throw new LogicException('Platform earnings must contain exactly one authoritative row.');
        }

        $earning = $rows->first();
        if (Schema::hasColumn('earnings', 'singleton_key') && (int) $earning->singleton_key !== 1) {
            throw new LogicException('The platform earnings row has an invalid singleton identity.');
        }

        return $earning;
    }
}
