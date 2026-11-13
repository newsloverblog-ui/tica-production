<?php

namespace App\Http\Middleware;

use App\Models\OrganizerTeamMember;
use Closure;
use Illuminate\Http\Request;
use App\Support\TeamPermissions;
use Illuminate\Support\Facades\Auth;

/**
 * Limits organiser team members (signed in for an organiser) to what their
 * role allows, as listed in config/tica_team.php. It works as an allow-list:
 * a page or action a role isn't given is refused, so new routes stay
 * owner-only until someone decides otherwise. The owner is never limited.
 */
class TicaTeamRole
{
  public function handle(Request $request, Closure $next)
  {
    $memberSession = $request->session()->get('tica_team_member');
    if ($memberSession === null) {
      return $next($request);
    }

    $member = is_array($memberSession)
      ? OrganizerTeamMember::query()
        ->whereKey((int) ($memberSession['id'] ?? 0))
        ->where('organizer_id', (int) Auth::guard('organizer')->id())
        ->where('status', 'active')
        ->whereIn('role', array_diff(array_keys((array) config('tica_team.roles', [])), ['owner']))
        ->first()
      : null;
    if (!$member) {
      return $this->revoke($request);
    }

    $role = (string) $member->role;
    $request->session()->put('tica_team_member.role', $role);

    $section = $request->route('section');
    if (TeamPermissions::allows($request->route(), is_string($section) ? $section : null, $role)) {
      return $next($request);
    }

    if ($request->expectsJson() || $request->is('tica-api/*')) {
      return response()->json(['message' => 'Your team role doesn\'t include this.'], 403);
    }

    return response()->view('errors.team-forbidden', [
      'role' => $role,
      'backUrl' => url('/dashboard'),
    ], 403);
  }
  /** A removed or malformed team session must stop working immediately. */
  private function revoke(Request $request)
  {
    Auth::guard('organizer')->logout();
    $request->session()->forget('tica_team_member');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($request->expectsJson()) {
      return response()->json([
        'message' => 'Your team access has ended. Sign in again.',
        'redirect' => route('organizer.login'),
      ], 401);
    }

    return redirect()->route('organizer.login')->with('error', 'Your team access has ended. Sign in again.');
  }
}
