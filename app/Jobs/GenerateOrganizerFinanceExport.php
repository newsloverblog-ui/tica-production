<?php

namespace App\Jobs;

use App\Exports\OrganizerFinanceQueryExport;
use App\Models\DataExport;
use App\Services\AuditLogger;
use App\Services\OrganizerFinanceStatementService;
use Carbon\CarbonImmutable;
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

class GenerateOrganizerFinanceExport implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
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
        return 'data-export-'.$this->exportId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter($this->timeout + 60)];
    }

    public function handle(OrganizerFinanceStatementService $statements): void
    {
        $export = DataExport::findOrFail($this->exportId);
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
            $from = CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['from_date']);
            $to = CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['to_date']);
            $query = $statements->query(
                (int) $export->organizer_id,
                $from,
                $to,
                (int) $filters['max_transaction_id']
            )->orderBy('created_at')->orderBy('id');
            $writerType = $export->format === 'xlsx' ? ExcelWriter::XLSX : ExcelWriter::CSV;
            $stored = Excel::store(
                new OrganizerFinanceQueryExport($query),
                $export->path,
                $export->disk,
                $writerType
            );
            if (!$stored || !Storage::disk($export->disk)->exists($export->path)) {
                throw new RuntimeException('The finance export writer did not produce a file.');
            }

            DB::transaction(function () use ($export): void {
                $locked = DataExport::whereKey($export->id)->lockForUpdate()->firstOrFail();
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
        $export = DataExport::find($this->exportId);
        if (!$export || $export->status === 'ready') {
            return;
        }
        $export->forceFill([
            'status' => 'failed',
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ])->save();
    }
}
