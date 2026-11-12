<?php

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'payment_refunds',
        'product_gateway_refunds',
        'payout_transfers',
        'gateway_settlements',
        'payment_reconciliation_runs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->transform($table, static fn (string $value): string => PrefixedEncryptedString::encrypt($value));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->transform($table, static fn (string $value): string => PrefixedEncryptedString::decryptForRollback($value) ?? '');
        }
    }

    /**
     * Encrypt or decrypt in bounded batches without hydrating Eloquent casts.
     * Empty and null diagnostics remain unchanged.
     */
    private function transform(string $table, callable $transform): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'last_error')) {
            return;
        }

        DB::transaction(function () use ($table, $transform): void {
            DB::table($table)
                ->select(['id', 'last_error'])
                ->whereNotNull('last_error')
                ->where('last_error', '<>', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $transform): void {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            'last_error' => $transform((string) $row->last_error),
                        ]);
                    }
                });
        });
    }
};
