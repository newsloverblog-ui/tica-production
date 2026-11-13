<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Event\Coupon;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionPlan;
use App\Models\TicaTrackingLink;
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
    private const CAMPAIGN_STATUSES = [
        'pending_payment',
        'pending_review',
        'approved',
        'scheduled',
        'active',
        'paused',
        'rejected',
        'cancellation_requested',
        'cancelled',
        'completed',
    ];

    private const PLACEMENTS = [
        'featured_event' => 'Featured event',
        'sponsored_event' => 'Sponsored event',
        'event_popup' => 'Event popup',
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::CAMPAIGN_STATUSES)],
            'placement' => ['nullable', Rule::in(array_keys(self::PLACEMENTS))],
            'q' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', Rule::in(['campaigns', 'plans', 'tracking', 'tools'])],
        ]);

        $campaigns = TicaPromotionCampaign::query()
            ->with(['organizer:id,username,email', 'event.information', 'plan:id,name,placement'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['placement'] ?? null, fn ($query, $placement) => $query->whereHas('plan', fn ($planQuery) => $planQuery->where('placement', $placement)))
            ->when(trim((string) ($filters['q'] ?? '')), function ($query, $term): void {
                $query->where(function ($search) use ($term): void {
                    $search->whereHas('organizer', fn ($organizer) => $organizer
                        ->where('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"))
                        ->orWhereHas('event.information', fn ($event) => $event->where('title', 'like', "%{$term}%"))
                        ->orWhere('headline', 'like', "%{$term}%");
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'review' => TicaPromotionCampaign::query()->where('status', 'pending_review')->count(),
            'live' => TicaPromotionCampaign::query()->where('status', 'active')->count(),
            'scheduled' => TicaPromotionCampaign::query()->whereIn('status', ['approved', 'scheduled'])->count(),
            'cancellations' => TicaPromotionCampaign::query()->where('status', 'cancellation_requested')->count(),
            'revenue' => (float) TicaPromotionCampaign::query()->whereNotNull('paid_at')->sum('price'),
        ];

        return view('backend.tica.promotions', [
            'plans' => TicaPromotionPlan::query()->orderBy('price')->get(),
            'campaigns' => $campaigns,
            'pixels' => TicaTrackingPixel::query()->with('organizer:id,username,email')->latest('id')->limit(100)->get(),
            'trackingLinks' => TicaTrackingLink::query()->with(['organizer:id,username', 'event.information'])->latest('id')->limit(12)->get(),
            'coupons' => Coupon::query()->with('organizer:id,username')->whereNotNull('organizer_id')->latest('id')->limit(12)->get(),
            'stats' => $stats,
            'filters' => $filters,
            'statusOptions' => self::CAMPAIGN_STATUSES,
            'placementLabels' => self::PLACEMENTS,
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
            'decision' => ['required', Rule::in(['approve', 'reject', 'pause', 'cancel'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        if ($validated['decision'] === 'approve' && (float) $record->price > 0 && (!$record->paid_at || !$record->payment_transaction_id)) {
            throw ValidationException::withMessages(['decision' => ['A paid promotion cannot be approved until its balance payment is recorded.']]);
        }

        $before = $record->only(['status', 'approved_by', 'approved_at', 'admin_notes', 'starts_at', 'ends_at']);
        $effectiveStartsAt = isset($validated['starts_at']) ? \Carbon\Carbon::parse($validated['starts_at']) : $record->starts_at;
        $effectiveEndsAt = isset($validated['ends_at']) ? \Carbon\Carbon::parse($validated['ends_at']) : $record->ends_at;
        if ($validated['decision'] === 'approve' && (!$effectiveStartsAt || !$effectiveEndsAt || $effectiveEndsAt->lessThanOrEqualTo(now()))) {
            throw ValidationException::withMessages(['ends_at' => [__('An approved promotion must have an end time in the future.')]]);
        }
        $status = match ($validated['decision']) {
            'approve' => $effectiveStartsAt && $effectiveStartsAt->isFuture() ? 'scheduled' : 'approved',
            'pause' => 'paused',
            'cancel' => 'cancelled',
            default => 'rejected',
        };
        $record->forceFill([
            'status' => $status,
            'approved_by' => Auth::guard('admin')->id(),
            'approved_at' => $validated['decision'] === 'approve' ? now() : $record->approved_at,
            'admin_notes' => $validated['admin_notes'] ?? null,
            'starts_at' => $validated['starts_at'] ?? $record->starts_at,
            'ends_at' => $validated['ends_at'] ?? $record->ends_at,
        ])->save();

        if (in_array($before['status'], ['active', 'cancellation_requested'], true)
            && in_array($validated['decision'], ['reject', 'pause', 'cancel'], true)
            && in_array(optional($record->plan)->placement, ['featured_event', 'sponsored_event'], true)) {
            $hasOtherLivePlacement = TicaPromotionCampaign::query()
                ->where('event_id', $record->event_id)
                ->where('id', '!=', $record->id)
                ->where('status', 'active')
                ->whereHas('plan', fn ($query) => $query->whereIn('placement', ['featured_event', 'sponsored_event']))
                ->exists();
            if (!$hasOtherLivePlacement) {
                $record->event()->update(['is_featured' => 'no']);
            }
        }
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
