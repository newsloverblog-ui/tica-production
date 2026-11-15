<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\TicaOrganizerSubscription;
use App\Models\TicaSponsorshipPackage;
use App\Services\AuditLogger;
use App\Services\OrganizerDoorSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class OrganizerOperationsController extends Controller
{
    public function doorSales(Request $request)
    {
        $organizerId = (int) Auth::guard('organizer')->id();
        $events = Event::with(['information', 'tickets'])
            ->where('organizer_id', $organizerId)->orderByDesc('id')->get();
        $sales = Booking::with('event')->where('organizer_id', $organizerId)
            ->where('gatewayType', 'door_sale')->latest('id')->paginate(15)->withQueryString();

        return view('organizer.operations.door-sales', compact('events', 'sales'));
    }

    public function recordDoorSale(Request $request, OrganizerDoorSaleService $sales)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'ticket_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'payment_method' => ['required', Rule::in(['cash', 'pos', 'bank_transfer', 'complimentary'])],
            'amount' => ['required_unless:payment_method,complimentary', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'check_in' => ['nullable', 'boolean'],
        ]);
        [$event, $ticket] = $this->ownedEventAndTicket((int) $validated['event_id'], (int) $validated['ticket_id']);
        try {
            $booking = $sales->record($event, $ticket, $validated);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['ticket_id' => $exception->getMessage()]);
        }

        return redirect()->route('organizer.operations.door-sales')
            ->with('success', 'Door sale ' . $booking->order_reference . ' recorded and ticket inventory updated.');
    }

    public function importDoorSales(Request $request, OrganizerDoorSaleService $sales)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'ticket_id' => ['required', 'integer'],
            'sales_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:4096'],
            'check_in' => ['nullable', 'boolean'],
        ]);
        [$event, $ticket] = $this->ownedEventAndTicket((int) $validated['event_id'], (int) $validated['ticket_id']);
        $rows = IOFactory::load($validated['sales_file']->getRealPath())->getActiveSheet()->toArray(null, false, true, false);
        $header = array_map(fn ($value) => Str::of((string) $value)->trim()->lower()->replace(' ', '_')->toString(), array_shift($rows) ?: []);
        if (! in_array('name', $header, true) || ! in_array('email', $header, true)) {
            throw ValidationException::withMessages(['sales_file' => 'Use the TICA template. The first row must include name and email.']);
        }
        if (count($rows) > 200) {
            throw ValidationException::withMessages(['sales_file' => 'Import up to 200 attendees at a time.']);
        }
        $attendees = [];
        foreach ($rows as $number => $row) {
            $line = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            if (! $line || trim((string) ($line['name'] ?? '')) === '') continue;
            $email = trim((string) ($line['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['sales_file' => 'Row ' . ($number + 2) . ' has an invalid email address.']);
            }
            $attendees[] = [
                'name' => trim((string) $line['name']), 'email' => $email,
                'phone' => trim((string) ($line['phone'] ?? '')), 'quantity' => 1,
                'payment_method' => 'complimentary', 'amount' => 0,
                'check_in' => ! empty($validated['check_in']),
            ];
        }
        if ($attendees === []) throw ValidationException::withMessages(['sales_file' => 'The file contains no attendee rows.']);
        DB::transaction(function () use ($attendees, $event, $ticket, $sales): void {
            foreach ($attendees as $attendee) $sales->record($event, $ticket, $attendee);
        }, 3);
        $count = count($attendees);

        return redirect()->route('organizer.operations.door-sales')->with('success', $count . ' complimentary attendee' . ($count === 1 ? '' : 's') . ' imported.');
    }

    public function doorSalesTemplate()
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['name', 'email', 'phone'], ',', '"', '');
            fputcsv($stream, ['Ada Test', 'ada@example.com', '+2348012345678'], ',', '"', '');
            fclose($stream);
        }, 'tica-door-sales-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function sponsorships()
    {
        $this->requireTable('tica_sponsorship_packages');
        $organizerId = (int) Auth::guard('organizer')->id();
        $events = Event::with('information')->where('organizer_id', $organizerId)->orderByDesc('id')->get();
        $packages = TicaSponsorshipPackage::with('event')->where('organizer_id', $organizerId)->latest('id')->get();

        return view('organizer.operations.sponsorships', compact('events', 'packages'));
    }

    public function storeSponsorship(Request $request)
    {
        $this->requireTable('tica_sponsorship_packages');
        $data = $this->validatePackage($request);
        $package = TicaSponsorshipPackage::create($data + [
            'public_id' => (string) Str::uuid(), 'organizer_id' => (int) Auth::guard('organizer')->id(),
        ]);
        AuditLogger::record('organizer.sponsorship_package_created', $package, [], $package->only(['name', 'status', 'price', 'event_id']));
        return back()->with('success', 'Sponsorship package created.');
    }

    public function updateSponsorship(Request $request, TicaSponsorshipPackage $package)
    {
        $this->ownedPackage($package);
        $before = $package->toArray();
        $package->update($this->validatePackage($request));
        AuditLogger::record('organizer.sponsorship_package_updated', $package, $before, $package->toArray());
        return back()->with('success', 'Sponsorship package updated.');
    }

    public function deleteSponsorship(TicaSponsorshipPackage $package)
    {
        $this->ownedPackage($package);
        AuditLogger::record('organizer.sponsorship_package_deleted', $package, $package->toArray(), []);
        $package->delete();
        return back()->with('success', 'Sponsorship package deleted.');
    }

    public function plans()
    {
        $this->requireTable('tica_organizer_subscriptions');
        $organizerId = (int) Auth::guard('organizer')->id();
        $subscription = TicaOrganizerSubscription::firstOrCreate(['organizer_id' => $organizerId], [
            'plan' => 'launch', 'status' => 'active', 'started_at' => now(),
        ]);
        $plans = config('tica_organizer_plans');
        $usage = [
            'events' => Event::where('organizer_id', $organizerId)->count(),
            'tickets' => Booking::where('organizer_id', $organizerId)->whereIn('paymentStatus', ['completed', 'free', '1'])->sum('quantity'),
        ];

        return view('organizer.operations.plans', compact('subscription', 'plans', 'usage'));
    }

    public function updatePlanAutomation(Request $request)
    {
        $this->requireTable('tica_organizer_subscriptions');
        $validated = $request->validate(['automatic_upgrade' => ['nullable', 'boolean']]);
        $subscription = TicaOrganizerSubscription::firstOrCreate(['organizer_id' => (int) Auth::guard('organizer')->id()], ['plan' => 'launch']);
        $subscription->update(['automatic_upgrade' => ! empty($validated['automatic_upgrade'])]);
        return back()->with('success', 'Automatic upgrade preference saved.');
    }

    public function requestPlan(Request $request)
    {
        $this->requireTable('tica_organizer_subscriptions');
        $plans = array_keys((array) config('tica_organizer_plans'));
        $validated = $request->validate(['plan' => ['required', Rule::in($plans)]]);
        $subscription = TicaOrganizerSubscription::firstOrCreate(['organizer_id' => (int) Auth::guard('organizer')->id()], ['plan' => 'launch']);
        if ($validated['plan'] === $subscription->plan) {
            throw ValidationException::withMessages(['plan' => 'This is already your current plan.']);
        }
        $before = $subscription->toArray();
        $subscription->update(['requested_plan' => $validated['plan'], 'status' => 'change_requested', 'change_requested_at' => now()]);
        AuditLogger::record('organizer.plan_change_requested', $subscription, $before, $subscription->toArray());
        return back()->with('success', 'Plan change requested. TICA will confirm the price and start date before charging anything.');
    }

    private function ownedEventAndTicket(int $eventId, int $ticketId): array
    {
        $event = Event::where('organizer_id', (int) Auth::guard('organizer')->id())->findOrFail($eventId);
        $ticket = Ticket::where('event_id', $event->id)->findOrFail($ticketId);
        return [$event, $ticket];
    }

    private function validatePackage(Request $request): array
    {
        $organizerId = (int) Auth::guard('organizer')->id();
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'summary' => ['nullable', 'string', 'max:300'],
            'deliverables' => ['required', 'string', 'max:3000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', Rule::in(['NGN', 'USD', 'GBP', 'EUR', 'GHS'])],
            'available_slots' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'event_id' => ['nullable', Rule::exists('events', 'id')->where(fn ($query) => $query->where('organizer_id', $organizerId))],
            'status' => ['required', Rule::in(['draft', 'live', 'paused'])],
        ]);
        $validated['deliverables'] = collect(preg_split('/\r\n|\r|\n/', $validated['deliverables']))
            ->map(fn ($line) => trim((string) $line))->filter()->values()->all();
        if ($validated['deliverables'] === []) throw ValidationException::withMessages(['deliverables' => 'Add at least one deliverable.']);
        return $validated;
    }

    private function ownedPackage(TicaSponsorshipPackage $package): void
    {
        abort_unless((int) $package->organizer_id === (int) Auth::guard('organizer')->id(), 404);
    }

    private function requireTable(string $table): void
    {
        abort_unless(Schema::hasTable($table), 503, 'This organizer tool is waiting for its database update.');
    }
}
