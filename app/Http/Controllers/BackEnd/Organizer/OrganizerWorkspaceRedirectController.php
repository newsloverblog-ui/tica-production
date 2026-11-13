<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizerWorkspaceRedirectController extends Controller
{
  public function overview(): RedirectResponse
  {
    return redirect('/dashboard');
  }

  public function settings(): RedirectResponse
  {
    return redirect('/dashboard/settings');
  }

  public function events(): RedirectResponse
  {
    return redirect('/dashboard/events');
  }

  public function create(Request $request)
  {
    if ($request->routeIs('choose-event-type')) {
      return view('organizer.event.event_type');
    }

    $type = $request->input('type');
    if (!in_array($type, ['online', 'venue'], true)) {
      return redirect()->route('choose-event-type');
    }

    return redirect('/create?type=' . $type);
  }

  public function orders(): RedirectResponse
  {
    return redirect('/dashboard/orders');
  }

  public function payouts(): RedirectResponse
  {
    return redirect('/dashboard/payouts');
  }
}
