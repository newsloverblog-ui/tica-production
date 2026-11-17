<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminImpersonationController extends Controller
{
    public function destroy(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $organizer = Auth::guard('organizer')->user();
        $impersonatorId = (int) $request->session()->get('impersonator_admin_id');

        abort_unless(
            (bool) $request->session()->get('secret_login')
            && $admin
            && $organizer
            && $impersonatorId > 0
            && (int) $admin->id === $impersonatorId,
            403,
            'This impersonation session is no longer valid.'
        );

        AuditLogger::record('organizer.impersonation_ended', $organizer, [], [], [
            'admin_id' => (int) $admin->id,
            'duration_seconds' => $request->session()->has('impersonation_started_at')
                ? max(0, now()->diffInSeconds($request->session()->get('impersonation_started_at')))
                : null,
        ]);

        Auth::guard('organizer')->logout();
        $request->session()->forget(['secret_login', 'impersonator_admin_id', 'impersonation_started_at']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.organizer_management.registered_organizer')
            ->with('success', 'Returned to the administrator workspace.');
    }
}
