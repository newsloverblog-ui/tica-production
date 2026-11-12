<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HasPermission
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle(Request $request, Closure $next, ...$menuNames)
  {
    $authAdmin = Auth::guard('admin')->user();

    if (!$authAdmin) {
      abort(403, 'Administrator authentication is required.');
    }

    if ($authAdmin->hasPermission(...array_values(array_filter(
      $menuNames,
      fn ($menuName) => is_string($menuName) && $menuName !== ''
    )))) {
      return $next($request);
    }

    abort(403, 'You do not have permission to access this area.');
  }
}
