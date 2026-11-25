<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class MonthlyReport
{
    public static function year(Request $request): int
    {
        $input = $request->validate(['year' => ['nullable', 'integer', 'min:1000', 'max:9998']]);
        return (int) ($input['year'] ?? now()->year);
    }

    public static function query(Builder $query, int $year): Builder
    {
        $month = match ($query->getConnection()->getDriverName()) {
            'pgsql' => 'CAST(EXTRACT(MONTH FROM created_at) AS integer)',
            'sqlite' => "CAST(strftime('%m', created_at) AS integer)",
            default => 'MONTH(created_at)',
        };

        return $query
            ->where('created_at', '>=', sprintf('%04d-01-01 00:00:00', $year))
            ->where('created_at', '<', sprintf('%04d-01-01 00:00:00', $year + 1))
            ->selectRaw("{$month} AS month")
            ->groupByRaw($month);
    }

    public static function series(Collection $rows, array $columns, string $format = 'M'): array
    {
        $rows = $rows->keyBy('month');
        $result = ['months' => []];
        for ($month = 1; $month <= 12; $month++) {
            $result['months'][] = DateTimeImmutable::createFromFormat('!m', (string) $month)->format($format);
            foreach ($columns as $column) {
                $result[$column][] = $rows->get($month)?->{$column} ?? 0;
            }
        }

        return $result;
    }
}
