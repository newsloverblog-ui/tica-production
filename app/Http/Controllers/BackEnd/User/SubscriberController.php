<?php

namespace App\Http\Controllers\BackEnd\User;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\SubscriberCampaign;
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
    $search = trim((string) $request->input('q', $request->input('email', '')));
    $records = Subscriber::query()
      ->currentlySubscribed()
      ->when($search, fn ($query) => $query->where('email_id', 'like', "%{$search}%"))
      ->orderByDesc('id')
      ->paginate(20)
      ->withQueryString();
    $subscriberCount = Subscriber::query()->currentlySubscribed()->count();

    return view('backend.end-user.subscriber.index', compact('records', 'subscriberCount'));
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
    $mode = $request->string('mode', 'all')->toString() === 'selected' ? 'selected' : 'all';
    $ids = collect(explode(',', (string) $request->input('ids', '')))->filter()->map(fn ($id) => (int) $id)->filter()->unique()->take(1000)->values();
    $search = trim((string) $request->input('q', ''));
    $recipientCount = $this->recipientQuery($mode, $ids->all(), $search)->count();

    return view('backend.end-user.subscriber.write-email', compact('campaigns', 'mode', 'ids', 'search', 'recipientCount'));
  }

  public function sendEmail(Request $request, SubscriberCampaignService $campaigns)
  {
    $validated = $request->validate([
      'subject' => ['required', 'string', 'max:255'],
      'message' => ['required', 'string', 'max:200000'],
      'mode' => ['required', 'in:all,selected'],
      'ids' => ['nullable', 'string', 'max:12000'],
      'q' => ['nullable', 'string', 'max:150'],
    ]);

    $ids = collect(explode(',', (string) ($validated['ids'] ?? '')))->filter()->map(fn ($id) => (int) $id)->filter()->unique()->take(1000)->values()->all();

    try {
      $recipients = $this->recipientQuery($validated['mode'], $ids, trim((string) ($validated['q'] ?? '')))
        ->cursor()->map(function ($recipient) {
          return [
            'email' => $recipient->email,
            'subscriber_id' => $recipient->id,
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

  private function recipientQuery(string $mode, array $ids, string $search)
  {
    if ($mode === 'selected' && !$ids) {
      return DB::table('subscribers')->selectRaw('id, email_id as email')->whereRaw('1 = 0');
    }
    return tap(
      DB::table('subscribers')->selectRaw('id, email_id as email'),
      fn ($query) => Subscriber::hasConsentColumn('unsubscribed_at') ? $query->whereNull('unsubscribed_at') : null
    )
      ->when($mode === 'selected', fn ($query) => $query->whereIn('id', $ids))
      ->when($search, fn ($query) => $query->where('email_id', 'like', "%{$search}%"));
  }
}
