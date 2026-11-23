<?php

namespace App\Http\Controllers\BackEnd\User;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\SubscriberCampaign;
use App\Models\Customer;
use App\Services\SubscriberCampaignService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SubscriberController extends Controller
{
  public function index(Request $request)
  {
    $segment = $request->routeIs('admin.audience.index')
      ? $request->string('segment', 'customers')->toString()
      : $request->string('segment', 'subscribers')->toString();
    if (!in_array($segment, ['customers', 'attendees', 'subscribers'], true)) $segment = 'customers';
    $search = trim((string) $request->input('q', $request->input('email', '')));
    $eventId = $request->integer('event_id') ?: null;

    $records = match ($segment) {
      'customers' => Customer::query()
        ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
          $nested->where('email', 'like', "%{$search}%")
            ->orWhere('fname', 'like', "%{$search}%")
            ->orWhere('lname', 'like', "%{$search}%")
            ->orWhere('username', 'like', "%{$search}%");
        }))->orderByDesc('id')->paginate(20)->withQueryString(),
      'attendees' => DB::table('booking_tickets')
        ->leftJoin('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
        ->leftJoin('event_contents', 'event_contents.event_id', '=', 'booking_tickets.event_id')
        ->selectRaw('booking_tickets.id, booking_tickets.holder_name, COALESCE(NULLIF(booking_tickets.holder_email, ?), bookings.email) as email, booking_tickets.ticket_number, booking_tickets.status, booking_tickets.checked_in_at, booking_tickets.event_id, MAX(event_contents.title) as event_title', [''])
        ->when($eventId, fn ($query) => $query->where('booking_tickets.event_id', $eventId))
        ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
          $nested->where('booking_tickets.holder_name', 'like', "%{$search}%")
            ->orWhere('booking_tickets.holder_email', 'like', "%{$search}%")
            ->orWhere('bookings.email', 'like', "%{$search}%")
            ->orWhere('booking_tickets.ticket_number', 'like', "%{$search}%");
        }))
        ->groupBy('booking_tickets.id', 'booking_tickets.holder_name', 'booking_tickets.holder_email', 'bookings.email', 'booking_tickets.ticket_number', 'booking_tickets.status', 'booking_tickets.checked_in_at', 'booking_tickets.event_id')
        ->orderByDesc('booking_tickets.id')->paginate(20)->withQueryString(),
      default => Subscriber::query()->whereNull('unsubscribed_at')->when($search, fn ($query) => $query->where('email_id', 'like', "%{$search}%"))
        ->orderByDesc('id')->paginate(20)->withQueryString(),
    };

    $summary = [
      'customers' => Customer::query()->count(),
      'attendees' => DB::table('booking_tickets')->count(),
      'subscribers' => Subscriber::query()->whereNull('unsubscribed_at')->count(),
    ];
    $events = DB::table('booking_tickets')->join('event_contents', 'event_contents.event_id', '=', 'booking_tickets.event_id')
      ->select('booking_tickets.event_id', DB::raw('MAX(event_contents.title) as title'))
      ->groupBy('booking_tickets.event_id')->orderBy('title')->get();

    return view('backend.end-user.subscriber.index', compact('records', 'segment', 'summary', 'events'));
  }

  public function destroy($id)
  {
    try {
      Subscriber::where('id', $id)->first()->delete();

      return redirect()->back()->with('success', 'Deleted Successfully');
    } catch (ModelNotFoundException $e) {
      return redirect()->back()->with('warning', 'Something went wrong');
    }
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    try {
      foreach ($ids as $id) {
        Subscriber::where('id', $id)->first()->delete();
      }

      Session::flash('success', 'Deleted Successfully');

      return response()->json(['status' => 'success'], 200);
    } catch (ModelNotFoundException $e) {
      Session::flash('warning', 'Something went wrong');

      return response()->json(['status' => 'success'], 200);
    }
  }

  public function writeEmail(Request $request)
  {
    $campaigns = SubscriberCampaign::query()->latest('id')->limit(10)->get();
    $audience = $request->string('audience', 'subscribers')->toString();
    if (!in_array($audience, ['customers', 'attendees', 'subscribers'], true)) $audience = 'subscribers';
    $mode = $request->string('mode', 'all')->toString() === 'selected' ? 'selected' : 'all';
    $ids = collect(explode(',', (string) $request->input('ids', '')))->filter()->map(fn ($id) => (int) $id)->filter()->unique()->take(1000)->values();
    $eventId = $request->integer('event_id') ?: null;
    $search = trim((string) $request->input('q', ''));
    $recipientCount = $this->recipientQuery($audience, $mode, $ids->all(), $eventId, $search)->count();

    return view('backend.end-user.subscriber.write-email', compact('campaigns', 'audience', 'mode', 'ids', 'eventId', 'search', 'recipientCount'));
  }

  public function sendEmail(Request $request, SubscriberCampaignService $campaigns)
  {
    $validated = $request->validate([
      'subject' => ['required', 'string', 'max:255'],
      'message' => ['required', 'string', 'max:200000'],
      'audience' => ['required', 'in:customers,attendees,subscribers'],
      'mode' => ['required', 'in:all,selected'],
      'ids' => ['nullable', 'string', 'max:12000'],
      'event_id' => ['nullable', 'integer'],
      'q' => ['nullable', 'string', 'max:150'],
      'acknowledge' => ['required_unless:audience,subscribers', 'accepted'],
    ]);

    $ids = collect(explode(',', (string) ($validated['ids'] ?? '')))->filter()->map(fn ($id) => (int) $id)->filter()->unique()->take(1000)->values()->all();

    try {
      $recipients = $this->recipientQuery($validated['audience'], $validated['mode'], $ids, $validated['event_id'] ?? null, trim((string) ($validated['q'] ?? '')))
        ->cursor()->map(function ($recipient) use ($validated) {
          return [
            'email' => $recipient->email,
            'subscriber_id' => $validated['audience'] === 'subscribers' ? $recipient->id : null,
          ];
        });
      $campaign = $campaigns->createForRecipients(
        $validated['subject'],
        $validated['message'],
        Auth::guard('admin')->id(),
        $recipients
      );
    } catch (DomainException $exception) {
      return redirect()->back()->withInput()->with('warning', $exception->getMessage());
    }

    Session::flash(
      'success',
      'Campaign queued for '.$campaign->total_count.' recipient'.($campaign->total_count === 1 ? '' : 's').'.'
    );

    return redirect()->back();
  }

  private function recipientQuery(string $audience, string $mode, array $ids, ?int $eventId, string $search)
  {
    if ($mode === 'selected' && !$ids) {
      return DB::table('subscribers')->selectRaw('id, email_id as email')->whereRaw('1 = 0');
    }
    return match ($audience) {
      'customers' => DB::table('customers')->selectRaw('id, email')
        ->when($mode === 'selected', fn ($query) => $query->whereIn('id', $ids))
        ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
          $nested->where('email', 'like', "%{$search}%")->orWhere('fname', 'like', "%{$search}%")->orWhere('lname', 'like', "%{$search}%");
        }))->whereNotNull('email'),
      'attendees' => DB::table('booking_tickets')->leftJoin('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
        ->selectRaw('booking_tickets.id, COALESCE(NULLIF(booking_tickets.holder_email, ?), bookings.email) as email', [''])
        ->when($mode === 'selected', fn ($query) => $query->whereIn('booking_tickets.id', $ids))
        ->when($eventId, fn ($query) => $query->where('booking_tickets.event_id', $eventId))
        ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
          $nested->where('booking_tickets.holder_email', 'like', "%{$search}%")->orWhere('booking_tickets.holder_name', 'like', "%{$search}%")->orWhere('bookings.email', 'like', "%{$search}%");
        }))
        ->where(function ($query) { $query->whereNotNull('booking_tickets.holder_email')->orWhereNotNull('bookings.email'); }),
      default => DB::table('subscribers')->selectRaw('id, email_id as email')
        ->whereNull('unsubscribed_at')
        ->when($mode === 'selected', fn ($query) => $query->whereIn('id', $ids))
        ->when($search, fn ($query) => $query->where('email_id', 'like', "%{$search}%")),
    };
  }
}
