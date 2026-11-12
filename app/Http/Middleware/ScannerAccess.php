<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('organizer')->check()) {
            $organizer = Auth::guard('organizer')->user();
            if ((int) $organizer->status !== 1) {
                return $this->deny($request, 'Organizer account is inactive.');
            }
            $request->attributes->set('scanner_organizer_id', (int) $organizer->id);
            $request->attributes->set('scanner_access_role', 'organizer');
            return $next($request);
        }

        if (Auth::guard('scanner')->check()) {
            $staff = Auth::guard('scanner')->user();
            $organizer = $staff->organizer;
            $sessionVersion = (int) $request->session()->get('scanner_auth_version', 0);
            if ($staff->status !== 'active' || !$organizer || (int) $organizer->status !== 1 || $sessionVersion !== (int) $staff->auth_version) {
                Auth::guard('scanner')->logout();
                $request->session()->forget('scanner_auth_version');
                return $this->deny($request, 'Gate session expired or account is inactive.');
            }
            if (!$staff->last_seen_at || $staff->last_seen_at->lt(now()->subMinute())) {
                $staff->forceFill(['last_seen_at' => now()])->save();
            }
            $request->attributes->set('scanner_organizer_id', (int) $staff->organizer_id);
            $request->attributes->set('scanner_access_role', $staff->role);
            $request->attributes->set('scanner_staff', $staff);
            return $next($request);
        }

        return $this->deny($request, 'Sign in with an organizer or gate staff account.');
    }

    private function deny(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'code' => 'scanner_auth_required'], 401);
        }
        return redirect()->route('scanner.login')->with('alert', $message);
    }
}
