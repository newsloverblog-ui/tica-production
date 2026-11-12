<?php

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['payment_intents', 'product_payment_intents'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'email')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->text('email')->change();
            });

            DB::transaction(function () use ($table): void {
                DB::table($table)
                    ->select(['id', 'email', 'last_error'])
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table): void {
                        foreach ($rows as $row) {
                            DB::table($table)->where('id', $row->id)->update([
                                'email' => PrefixedEncryptedString::encrypt((string) $row->email),
                                'last_error' => $row->last_error === null
                                    ? null
                                    : PrefixedEncryptedString::encrypt((string) $row->last_error),
                            ]);
                        }
                    });
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'email')) {
                continue;
            }

            DB::transaction(function () use ($table): void {
                DB::table($table)
                    ->select(['id', 'email', 'last_error'])
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table): void {
                        foreach ($rows as $row) {
                            DB::table($table)->where('id', $row->id)->update([
                                'email' => PrefixedEncryptedString::decryptForRollback($row->email),
                                'last_error' => PrefixedEncryptedString::decryptForRollback($row->last_error),
                            ]);
                        }
                    });
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('email')->change();
            });
        }
    }
};
