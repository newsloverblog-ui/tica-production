<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

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

  public function create(): RedirectResponse
  {
    return redirect('/create');
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
