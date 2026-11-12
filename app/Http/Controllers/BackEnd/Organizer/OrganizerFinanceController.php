<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Exports\OrganizerFinanceExport;
use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\Organizer;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use App\Services\OrganizerFinanceStatementService;
use App\Services\OrganizerPayoutScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class OrganizerFinanceController extends Controller
{
    public function index(
        Request $request,
        OrganizerFinanceStatementService $service,
        OrganizerPayoutScheduleService $payoutSchedule
    )
    {
        [$from, $to] = $this->range($request);
        $organizerId = (int) Auth::guard('organizer')->id();
        $statement = $service->statement($organizerId, $from, $to, null, false);
        $transactions = $service->query($organizerId, $from, $to)
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $transactions->setCollection($transactions->getCollection()->map(
            fn ($transaction) => $service->line($transaction)
        ));
        $currency = Basic::select('base_currency_symbol', 'base_currency_text')->first();
        $nextPayout = $payoutSchedule->summary($organizerId);

        return view('organizer.finance.index', compact(
            'statement', 'transactions', 'from', 'to', 'currency', 'nextPayout'
        ));
    }

    public function export(
        Request $request,
        OrganizerFinanceStatementService $service,
        DataExportService $exports
    )
    {
        $validated = $request->validate([
            'file_type' => ['required', 'in:csv,xlsx,pdf'],
        ]);
        [$from, $to] = $this->range($request);
        $organizer = Organizer::findOrFail((int) Auth::guard('organizer')->id());
        $transactionCount = $service->query($organizer->id, $from, $to)->count();
        $immediateLimit = max(1, (int) config('exports.immediate_finance_rows', 10000));
        if ($transactionCount > $immediateLimit) {
            if ($validated['file_type'] === 'pdf') {
                throw ValidationException::withMessages([
                    'file_type' => [
                        'Large finance statements cannot be rendered safely as PDF. Choose CSV or Excel for a private background export.',
                    ],
                ]);
            }

            try {
                $exports->queueOrganizerFinance($organizer->id, $from, $to, $validated['file_type']);
            } catch (\DomainException $exception) {
                throw ValidationException::withMessages(['file_type' => [$exception->getMessage()]]);
            }

            return redirect()->route('organizer.data_exports.index')->with(
                'success',
                'Your finance statement is being generated privately. It will appear here when ready.'
            );
        }
        $statement = $service->statement($organizer->id, $from, $to);
        $currency = Basic::select('base_currency_symbol', 'base_currency_text')->first();
        $basename = 'tica-finance-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d');

        AuditLogger::record('organizer.finance_statement_exported', $organizer, [], [], [
            'format' => $validated['file_type'],
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'transaction_count' => $statement['transaction_count'],
        ]);

        if ($validated['file_type'] === 'pdf') {
            return PDF::loadView('organizer.finance.statement-pdf', compact(
                'statement', 'organizer', 'currency'
            ))->download($basename . '.pdf');
        }

        $writer = $validated['file_type'] === 'xlsx'
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;
        return Excel::download(
            new OrganizerFinanceExport($statement['transactions']),
            $basename . '.' . $validated['file_type'],
            $writer
        );
    }

    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = !empty($validated['from'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['from'])
            : CarbonImmutable::now()->startOfMonth();
        $to = !empty($validated['to'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['to'])
            : CarbonImmutable::now();
        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => ['The end date must be on or after the start date.']]);
        }
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => ['Choose a date range of 366 days or less.']]);
        }
        return [$from, $to];
    }
}
