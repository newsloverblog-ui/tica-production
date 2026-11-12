<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\ScannerStaff;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;
use App\Services\ScannerStaffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ScannerStaffController extends Controller
{
    public function loginForm()
    {
        return view('organizer.pwa.staff-login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (!Auth::guard('scanner')->attempt(['email' => mb_strtolower($validated['email']), 'password' => $validated['password'], 'status' => 'active'])) {
            return back()->withErrors(['email' => 'The gate account credentials are invalid or inactive.'])->onlyInput('email');
        }
        $staff = Auth::guard('scanner')->user();
        if (!$staff->organizer || (int) $staff->organizer->status !== 1) {
            Auth::guard('scanner')->logout();
            return back()->withErrors(['email' => 'The organizer account is inactive.'])->onlyInput('email');
        }
        Auth::guard('organizer')->logout();
        $request->session()->regenerate();
        $request->session()->put('scanner_auth_version', (int) $staff->auth_version);
        $staff->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->save();
        AuditLogger::record('scanner_staff.logged_in', $staff, [], ['role' => $staff->role]);
        return redirect()->route('scanner.workspace');
    }

    public function logout(Request $request)
    {
        $staff = Auth::guard('scanner')->user();
        if ($staff) AuditLogger::record('scanner_staff.logged_out', $staff);
        Auth::guard('scanner')->logout();
        $request->session()->forget('scanner_auth_version');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('scanner.login');
    }

    public function workspace()
    {
        return view('organizer.pwa.index');
    }

    public function index()
    {
        $staff = ScannerStaff::where('organizer_id', Auth::guard('organizer')->id())->orderByDesc('id')->get();
        return response()->json(['staff' => $staff->map(fn ($person) => app(ScannerStaffService::class)->snapshot($person) + ['id' => $person->id])]);
    }

    public function store(Request $request, ScannerStaffService $service)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:scanner_staff,email'],
            'role' => ['required', Rule::in(ScannerStaffService::ROLES)],
            'password' => ['required', 'confirmed', PasswordPolicy::rule()],
        ]);
        $staff = $service->create((int) Auth::guard('organizer')->id(), $validated);
        return response()->json(['message' => 'Gate staff account created.', 'staff' => $service->snapshot($staff) + ['id' => $staff->id]], 201);
    }

    public function update(Request $request, ScannerStaff $staff, ScannerStaffService $service)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'role' => ['required', Rule::in(ScannerStaffService::ROLES)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'confirmed', PasswordPolicy::rule()],
        ]);
        $staff = $service->update((int) Auth::guard('organizer')->id(), $staff->id, $validated);
        return response()->json(['message' => 'Gate staff account updated.', 'staff' => $service->snapshot($staff) + ['id' => $staff->id]]);
    }
}
