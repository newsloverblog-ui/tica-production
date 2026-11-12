<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionPlan;
use App\Models\TicaTrackingPixel;
use App\Services\AuditLogger;
use App\Support\TicaCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicaPromotionController extends Controller
{
    public function index()
    {
        return view('backend.tica.promotions', [
            'plans' => TicaPromotionPlan::query()->orderBy('price')->get(),
            'campaigns' => TicaPromotionCampaign::query()->with(['organizer:id,username,email', 'event.information', 'plan:id,name,placement'])->latest('id')->limit(100)->get(),
            'pixels' => TicaTrackingPixel::query()->latest('id')->limit(100)->get(),
        ]);
    }

    public function updatePlan(Request $request, int $plan)
    {
        $this->requirePassword($request);
        $record = TicaPromotionPlan::query()->whereKey($plan)->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'duration_days' => ['required', 'integer', 'between:1,90'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $before = $record->toArray();
        $record->fill($validated + ['currency' => TicaCurrency::base(), 'is_active' => $request->boolean('is_active')])->save();
        AuditLogger::record('promotion.plan_updated', $record, $before, $record->fresh()->toArray(), ['admin_id' => Auth::guard('admin')->id()]);

        return back()->with('success', __('Promotion price updated.'));
    }

    public function reviewCampaign(Request $request, int $campaign)
    {
        $this->requirePassword($request);
        $record = TicaPromotionCampaign::query()->with('plan')->whereKey($campaign)->firstOrFail();
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'pause'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validated['decision'] === 'approve' && (float) $record->price > 0 && (!$record->paid_at || !$record->payment_transaction_id)) {
            throw ValidationException::withMessages(['decision' => ['A paid promotion cannot be approved until its balance payment is recorded.']]);
        }

        $before = $record->only(['status', 'approved_by', 'approved_at', 'admin_notes']);
        $status = match ($validated['decision']) {
            'approve' => $record->starts_at && $record->starts_at->isFuture() ? 'scheduled' : 'approved',
            'pause' => 'paused',
            default => 'rejected',
        };
        $record->forceFill([
            'status' => $status,
            'approved_by' => Auth::guard('admin')->id(),
            'approved_at' => $validated['decision'] === 'approve' ? now() : $record->approved_at,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ])->save();
        AuditLogger::record('promotion.reviewed', $record, $before, $record->fresh()->only(array_keys($before)), ['decision' => $validated['decision'], 'admin_id' => Auth::guard('admin')->id()]);

        return back()->with('success', __('Promotion review saved.'));
    }

    public function reviewPixel(Request $request, int $pixel)
    {
        $this->requirePassword($request);
        $record = TicaTrackingPixel::query()->whereKey($pixel)->firstOrFail();
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'disable'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $record->only(['status', 'approved_by', 'approved_at', 'admin_notes']);
        $record->forceFill([
            'status' => $validated['decision'] === 'approve' ? 'approved' : ($validated['decision'] === 'disable' ? 'disabled' : 'rejected'),
            'approved_by' => Auth::guard('admin')->id(),
            'approved_at' => $validated['decision'] === 'approve' ? now() : null,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ])->save();
        AuditLogger::record('tracking_pixel.reviewed', $record, $before, $record->fresh()->only(array_keys($before)), ['decision' => $validated['decision'], 'admin_id' => Auth::guard('admin')->id()]);

        return back()->with('success', __('Tracking provider review saved.'));
    }

    private function requirePassword(Request $request): void
    {
        $validated = $request->validate(['current_password' => ['required', 'string', 'max:255']]);
        $admin = Auth::guard('admin')->user();
        if (!$admin || !Hash::check($validated['current_password'], (string) $admin->password)) {
            throw ValidationException::withMessages(['current_password' => [__('Your admin password is incorrect.')]]);
        }
    }
}
