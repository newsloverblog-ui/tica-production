<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps read-only traffic available during a rolling deployment where the
 * application code reaches a host just before the soft-delete migration.
 *
 * Mutating a ticket still requires the migration: the standard SoftDeletes
 * delete/restore operations continue to write the deleted_at column and fail
 * closed if the schema is incomplete. ReleaseReadinessService separately
 * rejects a production release until that column exists.
 */
class SchemaAwareSoftDeletingScope extends SoftDeletingScope
{
    /** @var array<string, bool> */
    private array $columnAvailability = [];

    public function apply(Builder $builder, Model $model): void
    {
        $connection = $model->getConnectionName() ?: (string) config('database.default');
        $cacheKey = $connection . ':' . $model->getTable() . ':' . $model->getDeletedAtColumn();
        $hasDeletedAt = $this->columnAvailability[$cacheKey]
            ??= Schema::connection($connection)->hasColumn($model->getTable(), $model->getDeletedAtColumn());

        if (!$hasDeletedAt) {
            return;
        }

        parent::apply($builder, $model);
    }
}
