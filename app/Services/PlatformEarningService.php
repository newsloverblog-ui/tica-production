<?php

namespace App\Services;

use App\Models\Earning;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use LogicException;

class PlatformEarningService
{
    /**
     * Return the one authoritative platform-earnings row under a write lock.
     *
     * A schema with the singleton constraint can safely repair a missing row.
     * Ambiguous legacy rows still fail closed so concurrent financial paths can
     * never select an arbitrary platform ledger.
     */
    public function lock(): Earning
    {
        $rows = $this->lockedRows();

        if ($rows->isEmpty() && Schema::hasColumn('earnings', 'singleton_key')) {
            try {
                Earning::query()->firstOrCreate(
                    ['singleton_key' => 1],
                    ['total_revenue' => 0, 'total_earning' => 0]
                );
            } catch (QueryException $exception) {
                // A concurrent transaction may have created the unique row.
                // Only suppress that race when the authoritative row now exists.
                if (!Earning::query()->where('singleton_key', 1)->exists()) {
                    throw $exception;
                }
            }

            $rows = $this->lockedRows();
        }

        if ($rows->count() !== 1) {
            throw new LogicException('Platform earnings must contain exactly one authoritative row.');
        }

        $earning = $rows->first();
        if (Schema::hasColumn('earnings', 'singleton_key') && (int) $earning->singleton_key !== 1) {
            throw new LogicException('The platform earnings row has an invalid singleton identity.');
        }

        return $earning;
    }

    private function lockedRows()
    {
        return Earning::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(2)
            ->get();
    }
}
