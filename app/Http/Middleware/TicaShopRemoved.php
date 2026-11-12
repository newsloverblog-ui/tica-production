<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * TICA sells event tickets only. The template's merchandise shop stays in the
 * code base but every page and action of it is closed: admins are sent back
 * to the dashboard, everyone else gets a 404.
 */
class TicaShopRemoved
{
  public function handle(Request $request, Closure $next)
  {
    if ($request->is('admin/*')) {
      return redirect()->route('admin.dashboard')
        ->with('warning', __('The shop has been removed. TICA sells event tickets only.'));
    }

    abort(404);
  }
}
