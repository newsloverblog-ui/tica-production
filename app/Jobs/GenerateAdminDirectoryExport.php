<?php

namespace App\Jobs;

use App\Exports\AdminDirectoryQueryExport;
use App\Models\Customer;
use App\Models\DataExport;
use App\Models\Organizer;
use App\Services\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

class GenerateAdminDirectoryExport implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 900;
    public int $uniqueFor = 3600;

    public function __construct(public int $exportId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'admin-directory-export-'.$this->exportId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter($this->timeout + 60)];
    }

    public function handle(): void
    {
        $export = DataExport::query()->findOrFail($this->exportId);
        if ($export->status === 'ready' && $export->path && Storage::disk($export->disk)->exists($export->path)) {
            return;
        }
        if ($export->expires_at && $export->expires_at->isPast()) {
            $export->update(['status' => 'expired']);
            return;
        }

        $export->forceFill([
            'status' => 'processing',
            'started_at' => $export->started_at ?: now(),
            'attempts' => (int) $export->attempts + 1,
            'last_error' => null,
        ])->save();

        try {
            $filters = $export->filters ?: [];
            $query = $this->query($export->export_type, $filters);
            $writer = $export->format === 'xlsx' ? ExcelWriter::XLSX : ExcelWriter::CSV;
            $stored = Excel::store(
                new AdminDirectoryQueryExport($query, $export->export_type),
                $export->path,
                $export->disk,
                $writer
            );
            if (!$stored || !Storage::disk($export->disk)->exists($export->path)) {
                throw new RuntimeException('The directory export writer did not produce a file.');
            }

            DB::transaction(function () use ($export): void {
                $locked = DataExport::query()->whereKey($export->id)->lockForUpdate()->firstOrFail();
                $before = $locked->only(['status', 'attempts']);
                $locked->forceFill([
                    'status' => 'ready',
                    'completed_at' => now(),
                    'last_error' => null,
                ])->save();
                AuditLogger::record('data_export.ready', $locked, $before, $locked->only([
                    'status', 'attempts', 'row_count', 'completed_at', 'expires_at',
                ]));
            });
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $export = DataExport::query()->find($this->exportId);
        if (!$export || $export->status === 'ready') {
            return;
        }
        $export->forceFill([
            'status' => 'failed',
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ])->save();
    }

    private function query(string $directory, array $filters)
    {
        $model = $directory === 'organizers' ? Organizer::query() : Customer::query();
        $term = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $verified = $filters['verified'] ?? null;

        return $model
            ->when($term !== '', function ($query) use ($directory, $term): void {
                $query->where(function ($nested) use ($directory, $term): void {
                    $nested->where('email', 'like', '%'.$term.'%')
                        ->orWhere('username', 'like', '%'.$term.'%');
                    if ($directory === 'customers') {
                        $nested->orWhere('fname', 'like', '%'.$term.'%')
                            ->orWhere('lname', 'like', '%'.$term.'%');
                    }
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($verified !== null, fn ($query) => $verified
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at'))
            ->where('id', '<=', (int) ($filters['max_id'] ?? 0))
            ->orderBy('id');
    }
}
