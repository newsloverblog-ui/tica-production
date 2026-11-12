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
use Illuminate\Support\Facades\Session;

class SubscriberController extends Controller
{
  public function index(Request $request)
  {
    $searchKey = null;

    if ($request->filled('email')) {
      $searchKey = $request['email'];
    }

    $subscribers = Subscriber::when($searchKey, function ($query, $searchKey) {
      return $query->where('email_id', 'like', '%' . $searchKey . '%');
    })
      ->orderBy('id', 'desc')
      ->paginate(10);

    return view('backend.end-user.subscriber.index', compact('subscribers'));
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

  public function writeEmail()
  {
    $campaigns = SubscriberCampaign::query()->latest('id')->limit(10)->get();

    return view('backend.end-user.subscriber.write-email', compact('campaigns'));
  }

  public function sendEmail(Request $request, SubscriberCampaignService $campaigns)
  {
    $validated = $request->validate([
      'subject' => ['required', 'string', 'max:255'],
      'message' => ['required', 'string', 'max:200000'],
    ]);

    try {
      $campaign = $campaigns->create(
        $validated['subject'],
        $validated['message'],
        Auth::guard('admin')->id()
      );
    } catch (DomainException $exception) {
      return redirect()->back()->withInput()->with('warning', $exception->getMessage());
    }

    Session::flash(
      'success',
      'Campaign queued for '.$campaign->total_count.' subscriber'.($campaign->total_count === 1 ? '' : 's').'.'
    );

    return redirect()->back();
  }
}
