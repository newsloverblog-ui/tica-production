<?php

namespace App\Console\Commands;

use App\Models\EventGuestImport;
use App\Models\EventRegistrationImport;
use App\Services\EventGuestImportService;
use App\Services\EventRegistrationImportService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DispatchPendingImports extends Command
{
    protected $signature = 'imports:dispatch-pending {--limit=100} {--stale-minutes=30}';
    protected $description = 'Recover queued private guest and registration imports whose queue handoff did not start';

    public function handle(
        EventGuestImportService $guests,
        EventRegistrationImportService $registrations,
        IncidentAlertService $alerts,
    ): int {
        foreach (['event_guest_imports', 'event_registration_imports'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'dispatch_attempts')) {
                $this->error('The private-import dispatch metadata migration is not applied.');
                return self::FAILURE;
            }
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $families = [
            [EventGuestImport::class, fn (int $id): bool => $guests->dispatchQueued($id), 'guest'],
            [EventRegistrationImport::class, fn (int $id): bool => $registrations->dispatchQueued($id), 'registration'],
        ];
        foreach ($families as [$modelClass, $dispatch, $kind]) {
            $pending = $modelClass::query()
                ->where('status', 'queued')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('id')->limit($limit)->get();
            foreach ($pending as $import) {
                $attempted++;
                if (!$dispatch((int) $import->id)) {
                    $failed++;
                }
                $import->refresh();
                if ($this->alertIfStale($import, $kind, $staleMinutes, $alerts)) {
                    $alerted++;
                }
            }
        }

        $this->line(sprintf('Import dispatch attempted: %d | failed: %d | alerted: %d', $attempted, $failed, $alerted));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function alertIfStale(
        Model $import,
        string $kind,
        int $staleMinutes,
        IncidentAlertService $alerts,
    ): bool {
        if ($import->status !== 'queued'
            || !$import->created_at->lt(now()->subMinutes($staleMinutes))
            || $import->dispatch_alerted_at !== null) {
            return false;
        }
        $import->forceFill(['dispatch_alerted_at' => now()])->save();
        $alerts->alert('private-import-dispatch-pending:'.$kind.':'.$import->id,
            'Private '.$kind.' import processing did not start within the expected window.', [
                'import_id' => $import->public_id,
                'kind' => $kind,
                'event_id' => $import->event_id,
                'organizer_id' => $import->organizer_id,
                'dispatch_attempts' => $import->dispatch_attempts,
            ], 'critical');
        return true;
    }
}
