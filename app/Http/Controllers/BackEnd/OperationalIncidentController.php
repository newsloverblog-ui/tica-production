<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\OperationalIncident;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OperationalIncidentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,resolved'],
            'severity' => ['nullable', 'in:critical,error,warning'],
        ]);
        $incidents = OperationalIncident::query()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('last_seen_at')
            ->paginate(30)
            ->withQueryString();

        return view('backend.admin.operational-incidents.index', compact('incidents'));
    }

    public function resolve(Request $request, OperationalIncident $incident)
    {
        $validated = $request->validate(['note' => ['required', 'string', 'min:3', 'max:1000']]);
        DB::transaction(function () use ($incident, $validated): void {
            $locked = OperationalIncident::whereKey($incident->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'resolved') {
                return;
            }
            $before = $locked->only(['status', 'resolved_at']);
            $locked->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolution_note' => $validated['note'],
                'resolved_by_admin_id' => Auth::guard('admin')->id(),
            ])->save();
            AuditLogger::record('operational_incident.resolved', $locked, $before, [
                'status' => 'resolved', 'resolved_at' => $locked->resolved_at,
            ], ['note' => $validated['note']]);
        });

        return back()->with('success', 'Incident resolved. A new occurrence will reopen it automatically.');
    }
}
