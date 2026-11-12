<?php

namespace App\Services;

use App\Jobs\GenerateBookingExport;
use App\Jobs\GenerateAuditLogExport;
use App\Jobs\GenerateRegistrationExport;
use App\Jobs\GenerateGuestListExport;
use App\Jobs\GenerateOrganizerFinanceExport;
use App\Jobs\GenerateCustomerDataExport;
use App\Jobs\GenerateProductOrderExport;
use App\Jobs\GenerateWithdrawalExport;
use App\Jobs\GenerateTransactionExport;
use App\Jobs\GenerateReconciliationExport;
use App\Jobs\GenerateOperationsEvidenceExport;
use App\Jobs\GenerateEventCancellationCaseExport;
use App\Jobs\GenerateCheckInLedgerExport;
use App\Models\DataExport;
use App\Models\Event;
use App\Models\EventRegistrationSubmission;
use App\Models\EventGuestInvitation;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Customer;
use App\Models\ShopManagement\ProductOrder;
use App\Models\Withdraw;
use App\Models\Transaction;
use App\Models\GatewaySettlement;
use App\Models\PaymentReconciliationRun;
use App\Models\PaymentReconciliationEntry;
use App\Models\GatewaySettlementEntry;
use App\Models\OperationalIncident;
use App\Models\WebhookReceipt;
use App\Models\EventCancellationRequest;
use App\Models\EventCancellationCase;
use App\Models\ScannerCheckInConflict;
use DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DataExportService
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function queueCheckInLedger(
        string $actorType,
        int $actorId,
        int $organizerId,
        int $eventId,
        string $format
    ): DataExport {
        if (!in_array($actorType, ['organizer', 'scanner'], true) || $actorId < 1 || $organizerId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if ($actorType === 'organizer' && $actorId !== $organizerId) {
            throw new DomainException('Organizer exports must be scoped to the requesting organizer.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Check-in ledger exports support CSV or XLSX only.');
        }
        $event = Event::query()->whereKey($eventId)->where('organizer_id', $organizerId)->first();
        if (!$event) {
            throw new DomainException('The selected event is unavailable.');
        }

        $filters = [
            'organizer_id' => $organizerId,
            'event_id' => $eventId,
            'max_ticket_id' => (int) BookingTicket::query()
                ->where('organizer_id', $organizerId)->where('event_id', $eventId)->max('id'),
            'max_conflict_id' => (int) ScannerCheckInConflict::query()
                ->where('organizer_id', $organizerId)->where('event_id', $eventId)->max('id'),
            'snapshot_at' => now()->toIso8601String(),
        ];
        $query = app(CheckInLedgerExportQueryService::class)->ledger($filters);
        $rowCount = (clone $query)->count();
        if ($rowCount < 1) {
            throw new DomainException('There are no check-in records for this event yet.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This check-in ledger is too large for one file.');
        }

        return DB::transaction(function () use (
            $actorType, $actorId, $organizerId, $eventId, $format, $filters, $rowCount
        ): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId,
                'requested_by_type' => $actorType,
                'requested_by_id' => $actorId,
                'organizer_id' => $organizerId,
                'export_type' => 'check_in_ledger',
                'format' => $format,
                'status' => 'queued',
                'filters' => $filters,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/check-in-ledgers/'.$publicId.'.'.$format,
                'filename' => sprintf(
                    'tica_check_in_ledger_event_%d_%s_%s.%s',
                    $eventId, now()->format('Y-m-d'), Str::lower(Str::random(6)), $format
                ),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'event_id' => $eventId,
                'snapshot_max_ticket_id' => $filters['max_ticket_id'],
                'snapshot_max_conflict_id' => $filters['max_conflict_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueEventCancellationCases(
        int $adminId,
        int $cancellationRequestId,
        array $filters,
        string $format
    ): DataExport {
        if ($adminId < 1 || $cancellationRequestId < 1) {
            throw new DomainException('The export requester or cancellation request is invalid.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Cancellation case exports support CSV or XLSX only.');
        }

        EventCancellationRequest::query()->findOrFail($cancellationRequestId);
        $clean = [
            'cancellation_request_id' => $cancellationRequestId,
            'max_case_id' => (int) EventCancellationCase::query()
                ->where('event_cancellation_request_id', $cancellationRequestId)
                ->max('id'),
            'snapshot_at' => now()->toIso8601String(),
        ];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if (!in_array($status, [
                'refund_queued', 'manual_review', 'dispute_open',
                'external_refund_recorded', 'resolved_no_refund',
            ], true)) {
                throw new DomainException('The selected cancellation case status is invalid.');
            }
            $clean['status'] = $status;
        }

        $query = app(EventCancellationCaseExportQueryService::class)->cases($clean);
        $rowCount = $query->count();
        if ($rowCount < 1) {
            throw new DomainException('There are no cancellation cases matching these filters.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This cancellation case export is too large for one file. Narrow the filters.');
        }

        return DB::transaction(function () use (
            $adminId, $cancellationRequestId, $clean, $format, $rowCount
        ): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId,
                'requested_by_type' => 'admin',
                'requested_by_id' => $adminId,
                'organizer_id' => null,
                'export_type' => 'event_cancellation_cases',
                'format' => $format,
                'status' => 'queued',
                'filters' => $clean,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/event-cancellation-cases/'.$publicId.'.'.$format,
                'filename' => sprintf(
                    'tica_event_cancellation_cases_%d_%s_%s.%s',
                    $cancellationRequestId,
                    now()->format('Y-m-d'),
                    Str::lower(Str::random(6)),
                    $format
                ),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'cancellation_request_id' => $cancellationRequestId,
                'status_filter' => $clean['status'] ?? null,
                'snapshot_max_case_id' => $clean['max_case_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueOperationsEvidence(
        int $adminId,
        string $type,
        array $filters,
        string $format
    ): DataExport {
        if ($adminId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if (!in_array($type, ['operational_incidents', 'webhook_receipts'], true)) {
            throw new DomainException('The operations evidence export type is invalid.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Operations evidence exports support CSV or XLSX only.');
        }

        $clean = ['snapshot_at' => now()->toIso8601String()];
        if ($type === 'operational_incidents') {
            $status = trim((string) ($filters['status'] ?? ''));
            if ($status !== '') {
                if (!in_array($status, ['open', 'resolved'], true)) {
                    throw new DomainException('The selected incident status is invalid.');
                }
                $clean['status'] = $status;
            }
            $severity = trim((string) ($filters['severity'] ?? ''));
            if ($severity !== '') {
                if (!in_array($severity, ['critical', 'error', 'warning'], true)) {
                    throw new DomainException('The selected incident severity is invalid.');
                }
                $clean['severity'] = $severity;
            }
            $clean['max_incident_id'] = (int) OperationalIncident::query()->max('id');
            $query = app(OperationsEvidenceExportQueryService::class)->incidents($clean);
            $slug = 'operational-incidents';
        } else {
            $from = trim((string) ($filters['from'] ?? ''));
            $to = trim((string) ($filters['to'] ?? ''));
            if (!CarbonImmutable::hasFormat($from, 'Y-m-d') || !CarbonImmutable::hasFormat($to, 'Y-m-d')) {
                throw new DomainException('Choose a valid webhook export start and end date.');
            }
            $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $from)->startOfDay();
            $toDate = CarbonImmutable::createFromFormat('Y-m-d', $to)->endOfDay();
            if ($fromDate->gt($toDate) || $fromDate->diffInDays($toDate) > 31) {
                throw new DomainException('Choose a webhook export range of 31 days or less.');
            }
            $clean['from'] = $from;
            $clean['to'] = $to;
            $clean['from_at'] = $fromDate->toDateTimeString();
            $clean['to_at'] = $toDate->toDateTimeString();
            $gateway = trim((string) ($filters['gateway'] ?? ''));
            if ($gateway !== '') {
                if (!in_array($gateway, ['paystack', 'flutterwave'], true)) {
                    throw new DomainException('The selected webhook gateway is invalid.');
                }
                $clean['gateway'] = $gateway;
            }
            $outcome = trim((string) ($filters['outcome'] ?? ''));
            if ($outcome !== '') {
                if (!in_array($outcome, ['received', 'processed', 'duplicate', 'ignored', 'rejected', 'failed'], true)) {
                    throw new DomainException('The selected webhook outcome is invalid.');
                }
                $clean['outcome'] = $outcome;
            }
            $clean['max_receipt_id'] = (int) WebhookReceipt::query()->max('id');
            $query = app(OperationsEvidenceExportQueryService::class)->webhookReceipts($clean);
            $slug = 'webhook-receipts';
        }

        $rowCount = $query->count();
        if ($rowCount < 1) {
            throw new DomainException('There are no operations records matching these filters.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This operations evidence export is too large for one file. Narrow the filters.');
        }

        return DB::transaction(function () use ($adminId, $type, $clean, $format, $rowCount, $slug): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id'=>$publicId, 'requested_by_type'=>'admin', 'requested_by_id'=>$adminId,
                'organizer_id'=>null, 'export_type'=>$type, 'format'=>$format, 'status'=>'queued',
                'filters'=>$clean, 'disk'=>(string) config('exports.disk', 'local'),
                'path'=>'exports/'.$slug.'/'.$publicId.'.'.$format,
                'filename'=>sprintf('tica_%s_%s_%s.%s', str_replace('-', '_', $slug), now()->format('Y-m-d'), Str::lower(Str::random(6)), $format),
                'row_count'=>$rowCount,
                'expires_at'=>now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'status_filter'=>$clean['status'] ?? null,
                'severity_filter'=>$clean['severity'] ?? null,
                'gateway_filter'=>$clean['gateway'] ?? null,
                'outcome_filter'=>$clean['outcome'] ?? null,
                'from_date'=>$clean['from'] ?? null,
                'to_date'=>$clean['to'] ?? null,
                'snapshot_max_id'=>$clean['max_incident_id'] ?? $clean['max_receipt_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueReconciliation(
        int $adminId,
        string $type,
        array $filters,
        string $format
    ): DataExport {
        if ($adminId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if (!in_array($type, ['payment_reconciliation_runs', 'gateway_settlements'], true)) {
            throw new DomainException('The reconciliation export type is invalid.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Reconciliation exports support CSV or XLSX only.');
        }

        $clean = [];
        $date = trim((string) ($filters['date'] ?? ''));
        if ($date !== '') {
            if (!CarbonImmutable::hasFormat($date, 'Y-m-d')) {
                throw new DomainException('The reconciliation date is invalid.');
            }
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date);
            if ($parsed->format('Y-m-d') !== $date) {
                throw new DomainException('The reconciliation date is invalid.');
            }
            $clean['date'] = $date;
        }

        if ($type === 'payment_reconciliation_runs') {
            $gateway = trim((string) ($filters['gateway'] ?? ''));
            if ($gateway !== '') {
                if (!in_array($gateway, ['paystack', 'flutterwave'], true)) {
                    throw new DomainException('The selected reconciliation gateway is invalid.');
                }
                $clean['gateway'] = $gateway;
            }
            $allowedStatuses = ['matched', 'mismatched', 'failed', 'running'];
            $clean['max_run_id'] = (int) PaymentReconciliationRun::query()->max('id');
        } else {
            $allowedStatuses = ['matched', 'mismatched', 'failed', 'pending'];
            $clean['max_settlement_id'] = (int) GatewaySettlement::query()->max('id');
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new DomainException('The selected reconciliation status is invalid.');
            }
            $clean['status'] = $status;
        }
        $clean['snapshot_at'] = now()->toIso8601String();

        $queries = app(ReconciliationExportQueryService::class);
        $rowCount = $type === 'payment_reconciliation_runs'
            ? $queries->runs($clean)->count()
            : $queries->settlements($clean)->count();
        if ($rowCount < 1) {
            throw new DomainException('There are no reconciliation records matching these filters.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This reconciliation export is too large for one file. Narrow the filters.');
        }

        return DB::transaction(function () use ($adminId, $type, $clean, $format, $rowCount): DataExport {
            $publicId = (string) Str::uuid();
            $slug = $type === 'payment_reconciliation_runs' ? 'payment-reconciliation' : 'gateway-settlements';
            $export = DataExport::create([
                'public_id' => $publicId, 'requested_by_type' => 'admin',
                'requested_by_id' => $adminId, 'organizer_id' => null,
                'export_type' => $type, 'format' => $format, 'status' => 'queued',
                'filters' => $clean, 'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/'.$slug.'/'.$publicId.'.'.$format,
                'filename' => sprintf('tica_%s_%s_%s.%s', str_replace('-', '_', $slug), now()->format('Y-m-d'), Str::lower(Str::random(6)), $format),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'gateway' => $clean['gateway'] ?? null,
                'status_filter' => $clean['status'] ?? null,
                'date' => $clean['date'] ?? null,
                'snapshot_max_id' => $clean['max_run_id'] ?? $clean['max_settlement_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueReconciliationEntries(
        int $adminId,
        string $type,
        int $parentId,
        array $filters,
        string $format
    ): DataExport {
        if ($adminId < 1 || $parentId < 1) {
            throw new DomainException('The export requester or reconciliation parent is invalid.');
        }
        if (!in_array($type, ['payment_reconciliation_entries', 'gateway_settlement_entries'], true)) {
            throw new DomainException('The reconciliation detail export type is invalid.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Reconciliation detail exports support CSV or XLSX only.');
        }

        $clean = ['snapshot_at'=>now()->toIso8601String()];
        $result = trim((string) ($filters['result'] ?? ''));
        if ($type === 'payment_reconciliation_entries') {
            PaymentReconciliationRun::query()->findOrFail($parentId);
            $allowed = ['matched','gateway_only','paid_without_booking','local_status_not_completed','amount_mismatch','currency_mismatch','local_only'];
            $clean['run_id'] = $parentId;
            $clean['max_entry_id'] = (int) PaymentReconciliationEntry::query()->where('run_id',$parentId)->max('id');
            $query = app(ReconciliationExportQueryService::class)->runEntries($clean);
            $slug = 'payment-reconciliation-entries';
        } else {
            GatewaySettlement::query()->findOrFail($parentId);
            $allowed = ['matched','settled_charge_missing_locally','settled_without_booking','local_status_not_completed','amount_mismatch','currency_mismatch'];
            $clean['settlement_id'] = $parentId;
            $clean['max_entry_id'] = (int) GatewaySettlementEntry::query()->where('gateway_settlement_id',$parentId)->max('id');
            $query = app(ReconciliationExportQueryService::class)->settlementEntries($clean);
            $slug = 'gateway-settlement-entries';
        }
        if ($result !== '') {
            if (!in_array($result, $allowed, true)) {
                throw new DomainException('The selected reconciliation detail result is invalid.');
            }
            $clean['result'] = $result;
            $query->where('result', $result);
        }

        $rowCount = $query->count();
        if ($rowCount < 1) {
            throw new DomainException('There are no reconciliation detail rows matching these filters.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows',250000))) {
            throw new DomainException('This reconciliation detail export is too large for one file. Narrow the filters.');
        }

        return DB::transaction(function () use ($adminId,$type,$clean,$format,$rowCount,$slug,$parentId): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id'=>$publicId,'requested_by_type'=>'admin','requested_by_id'=>$adminId,
                'organizer_id'=>null,'export_type'=>$type,'format'=>$format,'status'=>'queued',
                'filters'=>$clean,'disk'=>(string) config('exports.disk','local'),
                'path'=>'exports/'.$slug.'/'.$publicId.'.'.$format,
                'filename'=>sprintf('tica_%s_%d_%s_%s.%s',str_replace('-','_',$slug),$parentId,now()->format('Y-m-d'),Str::lower(Str::random(6)),$format),
                'row_count'=>$rowCount,
                'expires_at'=>now()->addDays(max(1,(int) config('exports.retention_days',7))),
            ]);
            AuditLogger::record('data_export.queued',$export,[],$export->only([
                'export_type','format','status','row_count','expires_at',
            ]),[
                'parent_id'=>$parentId,'result_filter'=>$clean['result'] ?? null,
                'snapshot_max_entry_id'=>$clean['max_entry_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));
            return $export;
        });
    }

    public function queueTransactions(
        string $actorType,
        int $actorId,
        ?int $organizerId,
        array $filters,
        string $format
    ): DataExport {
        if (!in_array($actorType, ['admin', 'organizer'], true) || $actorId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if ($actorType === 'organizer' && (!$organizerId || $organizerId !== $actorId)) {
            throw new DomainException('Organizer transaction exports must be scoped to the requesting organizer.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Transaction exports support CSV or XLSX only.');
        }

        $clean = [];
        foreach (['from_date', 'to_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') $clean[$key] = $value;
        }
        if (!empty($clean['from_date']) || !empty($clean['to_date'])) {
            if (empty($clean['from_date']) || empty($clean['to_date'])) {
                throw new DomainException('Choose both a start date and an end date.');
            }
            $from = CarbonImmutable::createFromFormat('Y-m-d', $clean['from_date']);
            $to = CarbonImmutable::createFromFormat('Y-m-d', $clean['to_date']);
            if ($to->lt($from) || $from->diffInDays($to) > 366) {
                throw new DomainException('Choose a transaction export range of 366 days or less.');
            }
        }

        $reference = trim(strip_tags((string) ($filters['transaction_reference'] ?? '')));
        if ($reference !== '') $clean['transaction_reference'] = mb_substr($reference, 0, 100);
        foreach (['transaction_type' => range(1, 7), 'payment_status' => [0, 1, 2]] as $key => $allowed) {
            if (($filters[$key] ?? '') === '') continue;
            $value = filter_var($filters[$key], FILTER_VALIDATE_INT);
            if ($value === false || !in_array($value, $allowed, true)) {
                throw new DomainException('The selected transaction filter is invalid.');
            }
            $clean[$key] = $value;
        }

        $clean['max_transaction_id'] = (int) Transaction::query()
            ->when($organizerId, fn ($query, $id) => $query->where('organizer_id', $id))
            ->max('id');
        $clean['snapshot_at'] = now()->toIso8601String();
        $rowCount = app(TransactionExportQueryService::class)->query($clean, $organizerId)->count();
        if ($rowCount < 1) throw new DomainException('There are no transactions matching these filters.');
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This transaction export is too large for one file. Narrow the filters.');
        }

        return DB::transaction(function () use ($actorType, $actorId, $organizerId, $clean, $format, $rowCount): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId, 'requested_by_type' => $actorType,
                'requested_by_id' => $actorId, 'organizer_id' => $organizerId,
                'export_type' => 'transactions', 'format' => $format, 'status' => 'queued',
                'filters' => $clean, 'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/transactions/'.$publicId.'.'.$format,
                'filename' => sprintf('%s_transactions_%s_%s.%s', $actorType, now()->format('Y-m-d'), Str::lower(Str::random(6)), $format),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'organizer_id' => $organizerId,
                'transaction_type_filter' => $clean['transaction_type'] ?? null,
                'payment_status_filter' => $clean['payment_status'] ?? null,
                'from_date' => $clean['from_date'] ?? null,
                'to_date' => $clean['to_date'] ?? null,
                'has_reference_filter' => !empty($clean['transaction_reference']),
                'snapshot_max_transaction_id' => $clean['max_transaction_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueWithdrawals(
        string $actorType,
        int $actorId,
        ?int $organizerId,
        array $filters,
        string $format
    ): DataExport {
        if (!in_array($actorType, ['admin', 'organizer'], true) || $actorId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if ($actorType === 'organizer' && (!$organizerId || $organizerId !== $actorId)) {
            throw new DomainException('Organizer withdrawal exports must be scoped to the requesting organizer.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Withdrawal exports support CSV or XLSX only.');
        }

        $clean = [];
        foreach (['from_date', 'to_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') $clean[$key] = $value;
        }
        if (!empty($clean['from_date']) && !empty($clean['to_date'])) {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $clean['from_date']);
            $to = CarbonImmutable::createFromFormat('Y-m-d', $clean['to_date']);
            if ($to->lt($from) || $from->diffInDays($to) > 366) {
                throw new DomainException('Choose a withdrawal export range of 366 days or less.');
            }
        } elseif (!empty($clean['from_date']) || !empty($clean['to_date'])) {
            throw new DomainException('Choose both a start date and an end date.');
        }

        $reference = trim(strip_tags((string) ($filters['withdrawal_reference'] ?? '')));
        if ($reference !== '') $clean['withdrawal_reference'] = mb_substr($reference, 0, 100);
        if (($filters['status'] ?? '') !== '') {
            $status = filter_var($filters['status'], FILTER_VALIDATE_INT);
            if ($status === false || !in_array($status, [0, 1, 2, 3, 4], true)) {
                throw new DomainException('The selected withdrawal status is invalid.');
            }
            $clean['status'] = $status;
        }

        $clean['max_withdrawal_id'] = (int) Withdraw::query()
            ->when($organizerId, fn ($query, $id) => $query->where('organizer_id', $id))
            ->max('id');
        $clean['snapshot_at'] = now()->toIso8601String();
        $rowCount = app(WithdrawalExportQueryService::class)->query($clean, $organizerId)->count();
        if ($rowCount < 1) throw new DomainException('There are no withdrawals matching these filters.');
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This withdrawal export is too large for one file. Narrow the filters.');
        }

        return DB::transaction(function () use ($actorType, $actorId, $organizerId, $clean, $format, $rowCount): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId, 'requested_by_type' => $actorType,
                'requested_by_id' => $actorId, 'organizer_id' => $organizerId,
                'export_type' => 'withdrawals', 'format' => $format, 'status' => 'queued',
                'filters' => $clean, 'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/withdrawals/'.$publicId.'.'.$format,
                'filename' => sprintf('%s_withdrawals_%s_%s.%s', $actorType, now()->format('Y-m-d'), Str::lower(Str::random(6)), $format),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'organizer_id' => $organizerId,
                'status_filter' => $clean['status'] ?? null,
                'from_date' => $clean['from_date'] ?? null,
                'to_date' => $clean['to_date'] ?? null,
                'has_reference_filter' => !empty($clean['withdrawal_reference']),
                'snapshot_max_withdrawal_id' => $clean['max_withdrawal_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueProductOrders(int $adminId, array $filters, string $format): DataExport
    {
        if ($adminId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Product-order exports support CSV or XLSX only.');
        }

        $clean = [];
        foreach (['from_date', 'to_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }
        if (empty($clean['from_date']) || empty($clean['to_date'])) {
            throw new DomainException('Choose both a start date and an end date.');
        }
        $from = CarbonImmutable::createFromFormat('Y-m-d', $clean['from_date']);
        $to = CarbonImmutable::createFromFormat('Y-m-d', $clean['to_date']);
        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            throw new DomainException('Choose a product-order export range of 366 days or less.');
        }

        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));
        if ($paymentStatus !== '') {
            if (!in_array($paymentStatus, ['pending', 'completed', 'refunded', 'rejected'], true)) {
                throw new DomainException('The selected payment status is invalid.');
            }
            $clean['payment_status'] = $paymentStatus;
        }
        $orderStatus = trim((string) ($filters['order_status'] ?? ''));
        if ($orderStatus !== '') {
            if (!in_array($orderStatus, ['pending', 'processing', 'completed', 'rejected'], true)) {
                throw new DomainException('The selected order status is invalid.');
            }
            $clean['order_status'] = $orderStatus;
        }
        $paymentMethod = trim(strip_tags((string) ($filters['payment_method'] ?? '')));
        if ($paymentMethod !== '') {
            $clean['payment_method'] = mb_substr($paymentMethod, 0, 100);
        }

        $clean['max_order_id'] = (int) ProductOrder::query()->whereNull('archived_at')->max('id');
        $clean['snapshot_at'] = now()->toIso8601String();
        $rowCount = app(ProductOrderExportQueryService::class)->query($clean)->count();
        if ($rowCount < 1) {
            throw new DomainException('There are no product orders matching these filters.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This product-order export is too large for one file. Narrow the date filters.');
        }

        return DB::transaction(function () use ($adminId, $clean, $format, $rowCount): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId,
                'requested_by_type' => 'admin',
                'requested_by_id' => $adminId,
                'organizer_id' => null,
                'export_type' => 'product_orders',
                'format' => $format,
                'status' => 'queued',
                'filters' => $clean,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/product-orders/'.$publicId.'.'.$format,
                'filename' => sprintf('tica_product_orders_%s_to_%s_%s.%s',
                    $clean['from_date'], $clean['to_date'], Str::lower(Str::random(6)), $format),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'from_date' => $clean['from_date'],
                'to_date' => $clean['to_date'],
                'payment_method' => $clean['payment_method'] ?? null,
                'payment_status' => $clean['payment_status'] ?? null,
                'order_status' => $clean['order_status'] ?? null,
                'snapshot_max_order_id' => $clean['max_order_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueCustomerData(int $customerId): DataExport
    {
        if ($customerId < 1) {
            throw new DomainException('The export requester is invalid.');
        }

        return DB::transaction(function () use ($customerId): DataExport {
            Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            $existing = DataExport::query()
                ->where('requested_by_type', 'customer')
                ->where('requested_by_id', $customerId)
                ->where('export_type', 'customer_personal_data')
                ->whereIn('status', ['queued', 'processing', 'ready'])
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $publicId = (string) Str::uuid();
            $filters = [
                'requested_at' => now()->toIso8601String(),
                'max_booking_id' => (int) Booking::query()
                    ->where('customer_id', $customerId)
                    ->max('id'),
                'max_booking_ticket_id' => (int) BookingTicket::query()
                    ->where(function ($query) use ($customerId): void {
                        $query->where('holder_customer_id', $customerId)
                            ->orWhereHas('booking', fn ($booking) => $booking->where('customer_id', $customerId));
                    })
                    ->max('id'),
                'max_product_order_id' => (int) ProductOrder::query()
                    ->where('user_id', $customerId)
                    ->max('id'),
            ];
            $rowCount = 1
                + Booking::query()->where('customer_id', $customerId)->count()
                + BookingTicket::query()->where(function ($query) use ($customerId): void {
                    $query->where('holder_customer_id', $customerId)
                        ->orWhereHas('booking', fn ($booking) => $booking->where('customer_id', $customerId));
                })->count()
                + ProductOrder::query()->where('user_id', $customerId)->count();

            $export = DataExport::create([
                'public_id' => $publicId,
                'requested_by_type' => 'customer',
                'requested_by_id' => $customerId,
                'organizer_id' => null,
                'export_type' => 'customer_personal_data',
                'format' => 'zip',
                'status' => 'queued',
                'filters' => $filters,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/customers/'.$publicId.'.zip',
                'filename' => sprintf('tica-my-data-%s-%s.zip', now()->format('Y-m-d'), Str::lower(Str::random(6))),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);

            AuditLogger::record('customer_data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'snapshot_max_booking_id' => $filters['max_booking_id'],
                'snapshot_max_booking_ticket_id' => $filters['max_booking_ticket_id'],
                'snapshot_max_product_order_id' => $filters['max_product_order_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueAuditLogs(int $adminId, array $filters, string $format): DataExport
    {
        if ($adminId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Audit exports support CSV or XLSX only.');
        }

        $clean = [];
        foreach (['from_date', 'to_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') $clean[$key] = $value;
        }
        if (!empty($clean['from_date']) && !empty($clean['to_date'])) {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $clean['from_date']);
            $to = CarbonImmutable::createFromFormat('Y-m-d', $clean['to_date']);
            if ($to->lt($from) || $from->diffInDays($to) > 366) {
                throw new DomainException('Choose an audit export range of 366 days or less.');
            }
        }
        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            if (mb_strlen($action) > 120) throw new DomainException('The audit action filter is too long.');
            $clean['action'] = $action;
        }
        $actorType = trim((string) ($filters['actor_type'] ?? ''));
        if ($actorType !== '') {
            if (!in_array($actorType, ['admin', 'organizer', 'scanner', 'customer', 'web', 'system'], true)) {
                throw new DomainException('The selected audit actor type is invalid.');
            }
            $clean['actor_type'] = $actorType;
        }

        $queries = app(AuditLogExportQueryService::class);
        $clean['max_audit_id'] = (int) $queries->query($clean)->max('id');
        $rowCount = (clone $queries->query($clean))->count();
        if ($rowCount < 1) throw new DomainException('There are no audit records matching these filters.');
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This audit export is too large for one file. Narrow the date or action filters.');
        }
        $clean['snapshot_at'] = now()->toIso8601String();

        return DB::transaction(function () use ($adminId, $clean, $format, $rowCount): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId,
                'requested_by_type' => 'admin',
                'requested_by_id' => $adminId,
                'organizer_id' => null,
                'export_type' => 'audit_logs',
                'format' => $format,
                'status' => 'queued',
                'filters' => $clean,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/audit/'.$publicId.'.'.$format,
                'filename' => sprintf('tica_audit_%s_%s.%s', now()->format('Y-m-d'), Str::lower(Str::random(6)), $format),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'from_date' => $clean['from_date'] ?? null,
                'to_date' => $clean['to_date'] ?? null,
                'action_filter' => $clean['action'] ?? null,
                'actor_type_filter' => $clean['actor_type'] ?? null,
                'snapshot_max_audit_id' => $clean['max_audit_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueOrganizerFinance(
        int $organizerId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $format
    ): DataExport {
        if ($organizerId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Large finance statements support CSV or XLSX only.');
        }
        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            throw new DomainException('Choose a valid finance statement range of 366 days or less.');
        }

        $statements = app(OrganizerFinanceStatementService::class);
        $maxTransactionId = (int) $statements->query($organizerId, $from, $to)->max('id');
        $query = $statements->query($organizerId, $from, $to, $maxTransactionId);
        $rowCount = (clone $query)->count();
        if ($rowCount < 1) {
            throw new DomainException('There is no finance activity in this period.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This finance statement is too large for one file. Narrow the date range.');
        }

        $filters = [
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
            'max_transaction_id' => $maxTransactionId,
            'financial_snapshot_at' => now()->toIso8601String(),
        ];

        return DB::transaction(function () use ($organizerId, $format, $rowCount, $filters): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId,
                'requested_by_type' => 'organizer',
                'requested_by_id' => $organizerId,
                'organizer_id' => $organizerId,
                'export_type' => 'organizer_finance',
                'format' => $format,
                'status' => 'queued',
                'filters' => $filters,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/finance/'.$publicId.'.'.$format,
                'filename' => sprintf(
                    'tica_finance_%s_to_%s_%s.%s',
                    $filters['from_date'],
                    $filters['to_date'],
                    Str::lower(Str::random(6)),
                    $format
                ),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'snapshot_max_transaction_id' => $filters['max_transaction_id'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueBookings(
        string $actorType,
        int $actorId,
        ?int $organizerId,
        int $languageId,
        array $filters,
        string $format,
        ?int $scheduleId = null
    ): DataExport {
        if (!in_array($actorType, ['admin', 'organizer'], true) || $actorId < 1) {
            throw new DomainException('The export requester is invalid.');
        }
        if ($actorType === 'organizer' && (!$organizerId || $organizerId !== $actorId)) {
            throw new DomainException('Organizer exports must be scoped to the requesting organizer.');
        }
        if (!in_array($format, self::FORMATS, true)) {
            throw new DomainException('The selected export format is not supported.');
        }

        $filters = $this->sanitizeFilters($filters);
        $filters['field_keys'] = app(BookingExportFieldPolicyService::class)->forActor($actorType, $actorId);
        $filters['language_id'] = $languageId;
        $filters['financial_snapshot_at'] = now()->toIso8601String();
        $filters['max_booking_id'] = (int) Booking::query()
            ->when($organizerId, fn ($query, $id) => $query->where('organizer_id', $id))
            ->max('id');

        $query = app(BookingExportQueryService::class)->query($filters, $organizerId);
        $rowCount = (clone $query)->count();
        if ($rowCount < 1) {
            throw new DomainException('There are no bookings matching these filters.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This export is too large for one file. Narrow the date or event filters.');
        }

        return DB::transaction(function () use (
            $actorType, $actorId, $organizerId, $filters, $format, $rowCount, $scheduleId
        ): DataExport {
            $publicId = (string) Str::uuid();
            $filename = sprintf('%s_bookings_%s_%s.%s', $actorType, now()->format('Y-m-d'), Str::lower(Str::random(6)), $format);
            $attributes = [
                'public_id' => $publicId,
                'requested_by_type' => $actorType,
                'requested_by_id' => $actorId,
                'organizer_id' => $organizerId,
                'export_type' => 'bookings',
                'format' => $format,
                'status' => 'queued',
                'filters' => $filters,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/bookings/' . $publicId . '.' . $format,
                'filename' => $filename,
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ];
            if ($scheduleId !== null) {
                $attributes['schedule_id'] = $scheduleId;
            }
            $export = DataExport::create($attributes);

            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), ['filters' => $this->publicFilters($filters)]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueRegistrations(
        int $organizerId,
        int $eventId,
        array $filters,
        string $format,
        bool $privacyConfirmed
    ): DataExport {
        if ($organizerId < 1 || !$privacyConfirmed) {
            throw new DomainException('Confirm that the export will be used only for this event’s stated purpose.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Registration exports support CSV or XLSX only.');
        }
        $event = Event::query()
            ->whereKey($eventId)
            ->where('organizer_id', $organizerId)
            ->where('registration_mode', 'corporate')
            ->first();
        if (!$event) {
            throw new DomainException('The selected corporate-registration event is unavailable.');
        }

        $clean = [];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if (!in_array($status, ['submitted', 'approved', 'ticketed', 'rejected', 'cancelled'], true)) {
                throw new DomainException('The selected registration status is invalid.');
            }
            $clean['status'] = $status;
        }
        foreach (['from_date', 'to_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }
        $clean['event_id'] = $eventId;
        $clean['admission_snapshot_at'] = now()->toIso8601String();
        $clean['max_submission_id'] = (int) EventRegistrationSubmission::query()
            ->where('organizer_id', $organizerId)
            ->where('event_id', $eventId)
            ->whereNull('anonymized_at')
            ->max('id');
        $query = app(RegistrationExportQueryService::class)->query($clean, $organizerId);
        $submissionCount = (clone $query)->count();
        $rowCount = (int) (clone $query)->sum('attendee_count');
        if ($submissionCount < 1 || $rowCount < 1) {
            throw new DomainException('There are no non-anonymized registrations matching these filters.');
        }
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This export is too large for one file. Narrow the date or status filters.');
        }
        $clean['question_columns'] = $this->registrationQuestionColumns($clean, $organizerId);

        return DB::transaction(function () use ($organizerId, $eventId, $clean, $format, $rowCount): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId,
                'requested_by_type' => 'organizer',
                'requested_by_id' => $organizerId,
                'organizer_id' => $organizerId,
                'export_type' => 'event_registrations',
                'format' => $format,
                'status' => 'queued',
                'filters' => $clean,
                'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/registrations/'.$publicId.'.'.$format,
                'filename' => sprintf('event_%d_registrations_%s_%s.%s', $eventId, now()->format('Y-m-d'), Str::lower(Str::random(6)), $format),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), [
                'event_id' => $eventId,
                'status_filter' => $clean['status'] ?? null,
                'from_date' => $clean['from_date'] ?? null,
                'to_date' => $clean['to_date'] ?? null,
                'privacy_use_confirmed' => true,
                'snapshot_max_submission_id' => $clean['max_submission_id'],
                'admission_snapshot_at' => $clean['admission_snapshot_at'],
            ]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));

            return $export;
        });
    }

    public function queueGuestList(
        int $organizerId,
        int $eventId,
        array $filters,
        string $format,
        bool $privacyConfirmed
    ): DataExport {
        if ($organizerId < 1 || !$privacyConfirmed) {
            throw new DomainException('Confirm that the export will be used only for RSVP and event operations.');
        }
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new DomainException('Guest-list exports support CSV or XLSX only.');
        }
        $event = Event::query()->whereKey($eventId)->where('organizer_id', $organizerId)->first();
        if (!$event) throw new DomainException('The selected event is unavailable.');

        $clean = ['event_id' => $eventId];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if (!in_array($status, ['pending', 'accepted', 'declined', 'cancelled', 'expired'], true)) {
                throw new DomainException('The selected RSVP status is invalid.');
            }
            $clean['status'] = $status;
        }
        foreach (['from_date', 'to_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') $clean[$key] = $value;
        }
        $clean['max_invitation_id'] = (int) EventGuestInvitation::query()
            ->where('organizer_id', $organizerId)->where('event_id', $eventId)
            ->whereNull('anonymized_at')->max('id');
        $query = app(GuestInvitationExportQueryService::class)->query($clean, $organizerId);
        $rowCount = (clone $query)->count();
        if ($rowCount < 1) throw new DomainException('There are no non-anonymized guests matching these filters.');
        if ($rowCount > max(1, (int) config('exports.max_rows', 250000))) {
            throw new DomainException('This export is too large for one file. Narrow the date or RSVP filters.');
        }

        return DB::transaction(function () use ($organizerId, $eventId, $clean, $format, $rowCount): DataExport {
            $publicId = (string) Str::uuid();
            $export = DataExport::create([
                'public_id' => $publicId, 'requested_by_type' => 'organizer',
                'requested_by_id' => $organizerId, 'organizer_id' => $organizerId,
                'export_type' => 'event_guest_list', 'format' => $format, 'status' => 'queued',
                'filters' => $clean, 'disk' => (string) config('exports.disk', 'local'),
                'path' => 'exports/guest-lists/'.$publicId.'.'.$format,
                'filename' => sprintf('event_%d_guest_list_%s_%s.%s', $eventId, now()->format('Y-m-d'), Str::lower(Str::random(6)), $format),
                'row_count' => $rowCount,
                'expires_at' => now()->addDays(max(1, (int) config('exports.retention_days', 7))),
            ]);
            AuditLogger::record('data_export.queued', $export, [], $export->only([
                'export_type', 'format', 'status', 'row_count', 'expires_at',
            ]), ['event_id' => $eventId, 'status_filter' => $clean['status'] ?? null,
                'from_date' => $clean['from_date'] ?? null, 'to_date' => $clean['to_date'] ?? null,
                'privacy_use_confirmed' => true, 'snapshot_max_invitation_id' => $clean['max_invitation_id']]);
            DB::afterCommit(fn () => $this->dispatchQueued($export->id));
            return $export;
        });
    }

    private function registrationQuestionColumns(array $filters, int $organizerId): array
    {
        $base = DB::table('event_registration_answers as answers')
            ->join('event_registration_submissions as submissions', 'submissions.id', '=', 'answers.submission_id')
            ->where('submissions.organizer_id', $organizerId)
            ->where('submissions.event_id', (int) $filters['event_id'])
            ->whereNull('submissions.anonymized_at')
            ->where('submissions.id', '<=', (int) $filters['max_submission_id']);
        if (!empty($filters['status'])) {
            $base->where('submissions.status', $filters['status']);
        }
        if (!empty($filters['from_date'])) {
            $base->whereDate('submissions.submitted_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $base->whereDate('submissions.submitted_at', '<=', $filters['to_date']);
        }

        $columns = [];
        foreach (['registrant' => true, 'attendee' => false] as $scope => $isNull) {
            $query = clone $base;
            $query = $isNull ? $query->whereNull('answers.attendee_id') : $query->whereNotNull('answers.attendee_id');
            foreach ($query->select('answers.question_id', 'answers.question_label')->distinct()->orderBy('answers.question_id')->get() as $answer) {
                $columns[] = [
                    'scope' => $scope,
                    'question_id' => (int) $answer->question_id,
                    'label' => mb_substr((string) $answer->question_label, 0, 191),
                ];
            }
        }

        return $columns;
    }

    private function sanitizeFilters(array $filters): array
    {
        $clean = [];
        foreach (['booking_id', 'event_title', 'status', 'payment_status', 'payment_method'] as $key) {
            $value = trim(strip_tags((string) ($filters[$key] ?? '')));
            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, $key === 'event_title' ? 191 : 100);
            }
        }
        foreach (['from_date', 'to_date'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    private function publicFilters(array $filters): array
    {
        return array_filter([
            'has_booking_id' => !empty($filters['booking_id']),
            'has_event_title' => !empty($filters['event_title']),
            'status' => $filters['status'] ?? $filters['payment_status'] ?? null,
            'payment_method' => $filters['payment_method'] ?? null,
            'from_date' => $filters['from_date'] ?? null,
            'to_date' => $filters['to_date'] ?? null,
            'language_id' => $filters['language_id'] ?? null,
            'snapshot_max_booking_id' => $filters['max_booking_id'] ?? null,
            'financial_snapshot_at' => $filters['financial_snapshot_at'] ?? null,
        ], fn ($value) => $value !== null);
    }

    public function dispatchQueued(int $exportId): bool
    {
        $export = DataExport::query()->find($exportId);
        if (!$export || $export->status !== 'queued') {
            return true;
        }

        $hasMetadata = Schema::hasColumn('data_exports', 'dispatch_attempts');
        if ($hasMetadata) {
            $export->forceFill([
                'dispatch_attempts' => (int) $export->dispatch_attempts + 1,
                'dispatch_last_attempt_at' => now(),
                'dispatch_last_error' => null,
            ])->save();
        }

        try {
            $job = $this->generationJob($export->export_type);
            $this->pushGenerationJob($job, $export->id);
            return true;
        } catch (Throwable $exception) {
            if (!$hasMetadata) {
                throw $exception;
            }
            $export->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushGenerationJob(string $job, int $exportId): void
    {
        $job::dispatch($exportId);
    }

    protected function generationJob(string $type): string
    {
        return match ($type) {
            'check_in_ledger' => GenerateCheckInLedgerExport::class,
            'event_cancellation_cases' => GenerateEventCancellationCaseExport::class,
            'operational_incidents', 'webhook_receipts' => GenerateOperationsEvidenceExport::class,
            'payment_reconciliation_runs', 'gateway_settlements',
            'payment_reconciliation_entries', 'gateway_settlement_entries' => GenerateReconciliationExport::class,
            'transactions' => GenerateTransactionExport::class,
            'withdrawals' => GenerateWithdrawalExport::class,
            'product_orders' => GenerateProductOrderExport::class,
            'customer_personal_data' => GenerateCustomerDataExport::class,
            'audit_logs' => GenerateAuditLogExport::class,
            'organizer_finance' => GenerateOrganizerFinanceExport::class,
            'bookings' => GenerateBookingExport::class,
            'event_registrations' => GenerateRegistrationExport::class,
            'event_guest_list' => GenerateGuestListExport::class,
            default => throw new RuntimeException('The queued export type has no generation job.'),
        };
    }
}
