<?php

namespace App\Http\Middleware;

use App\Models\OrganizerTeamMember;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Limits organiser team members (signed in for an organiser) to their role
 * on every organiser route: managers never touch money or account security,
 * finance never changes events. The account owner is never limited.
 */
class TicaTeamRole
{
  /** Account security and settings: owner only. */
  private const OWNER_ONLY = [
    'organizer.edit.profile', 'organizer.update_profile', 'organizer.change.password', 'organizer.update_password',
    'organizer.kyc.*', 'organizer.payout-account.*', 'organizer.scanner.staff.*', 'organizer.scanner.devices.store',
    'organizer.scanner.devices.revoke', 'organizer.data_export_schedules.*',
  ];

  /** Money: owner and finance. */
  private const FINANCE = [
    'organizer.withdraw*', 'organizer.witdraw.*', 'organizer.finance.*', 'organizer.transcation', 'organizer.transaction_exports.*',
    'organizer.monthly_income', 'organizer.refunds.store', 'organizer.refund_disputes.store', 'organizer.event_booking.update_payment_status',
  ];

  /** Event changes: owner and manager. */
  private const EVENTS = [
    'choose-event-type', 'organizer.add.event.event', 'organizer.event_management.*', 'organizer.event.update', 'organizer.event.add.ticket',
    'organizer.event.edit.ticket', 'organizer.ticket_management.*', 'organizer.event.images*', 'organizer.event.imagermv',
    'organizer.event.imgdbrmv', 'organizer.event.delete.date', 'organizer.event_cancellations.store', 'organizer.event.registrations.*',
    'organizer.event.guest-list.*', 'organizer.event_booking.delete', 'organizer.event_booking.bulk_delete',
    'organizer.event_booking.ticket.revoke', 'organizer.event_booking.ticket.reissue',
  ];

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
        ->whereIn('role', ['manager', 'finance'])
        ->first()
      : null;
    if (!$member) {
      return $this->revoke($request);
    }

    $role = (string) $member->role;
    $request->session()->put('tica_team_member.role', $role);
    if ($role === 'owner') {
      return $next($request);
    }

    $route = (string) optional($request->route())->getName();
    $blocked = $this->matches($route, self::OWNER_ONLY)
      || ($role !== 'finance' && $this->matches($route, self::FINANCE))
      || ($role !== 'manager' && $this->matches($route, self::EVENTS));

    if ($blocked) {
      if ($request->expectsJson()) {
        return response()->json(['message' => 'Your team role doesn\'t include this.'], 403);
      }
      return redirect('/dashboard')->with('error', 'Your team role doesn\'t include that page.');
    }

    return $next($request);
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

  private function matches(string $route, array $patterns): bool
  {
    foreach ($patterns as $pattern) {
      if ($route !== '' && \Illuminate\Support\Str::is($pattern, $route)) {
        return true;
      }
    }

    return false;
  }
}
