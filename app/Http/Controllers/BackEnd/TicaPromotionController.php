<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Event\Coupon;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionPlan;
use App\Models\TicaTrackingLink;
use App\Models\TicaTrackingPixel;
use App\Services\AuditLogger;
use App\Services\TicaPromotionService;
use App\Services\TicaPromotionNotificationService;
use App\Services\TicaPromotionPaystackService;
use App\Support\TicaCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        'change_requested',
        'refunded',
    ];

    private const PLACEMENTS = [
        'featured_event' => 'Featured event',
        'sponsored_event' => 'Sponsored event',
        'event_popup' => 'Pop Events',
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::CAMPAIGN_STATUSES)],
            'placement' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', Rule::in(['campaigns', 'plans', 'analytics', 'tracking', 'tools'])],
        ]);

        $campaigns = TicaPromotionCampaign::query()
            ->with(['organizer:id,username,email', 'event.information', 'plan:id,name,placement', 'paymentTransaction'])
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
            'revenue' => (float) TicaPromotionCampaign::query()->where('payment_status', 'paid')->sum('price'),
            'completed' => TicaPromotionCampaign::query()->where('status', 'completed')->count(),
            'rejected' => TicaPromotionCampaign::query()->where('status', 'rejected')->count(),
            'purchases' => TicaPromotionCampaign::query()->where('payment_status', 'paid')->count(),
            'impressions' => (int) TicaPromotionCampaign::query()->sum('impressions'),
            'clicks' => (int) TicaPromotionCampaign::query()->sum('clicks'),
            'tickets' => (int) TicaPromotionCampaign::query()->sum('attributed_tickets'),
            'ticketRevenue' => (float) TicaPromotionCampaign::query()->sum('attributed_revenue'),
        ];
        $plans = TicaPromotionPlan::query()->orderByRaw('archived_at is not null')->orderBy('price')->get();
        $inventory = $plans->mapWithKeys(function (TicaPromotionPlan $plan): array {
            $reserved = TicaPromotionCampaign::query()->where('plan_id', $plan->id)
                ->whereIn('status', ['pending_review', 'approved', 'scheduled', 'active', 'paused', 'cancellation_requested'])
                ->where('ends_at', '>', now())->count();
            $limit = (int) ($plan->max_concurrent ?: $plan->capacity ?: 0);
            return [$plan->id => [
                'reserved' => $reserved, 'limit' => $limit,
                'available' => $limit > 0 ? max(0, $limit - $reserved) : null,
                'next' => TicaPromotionCampaign::query()->where('plan_id', $plan->id)
                    ->whereIn('status', ['approved', 'scheduled', 'active'])->where('ends_at', '>', now())
                    ->orderBy('starts_at')->limit(4)->get(['reference', 'starts_at', 'ends_at', 'status']),
            ]];
        });

        return view('backend.tica.promotions', [
            'plans' => $plans,
            'inventory' => $inventory,
            'campaigns' => $campaigns,
            'pixels' => TicaTrackingPixel::query()->with('organizer:id,username,email')->latest('id')->limit(100)->get(),
            'trackingLinks' => TicaTrackingLink::query()->with(['organizer:id,username', 'event.information'])->latest('id')->limit(12)->get(),
            'coupons' => Coupon::query()->with('organizer:id,username')->whereNotNull('organizer_id')->latest('id')->limit(12)->get(),
            'stats' => $stats,
            'filters' => $filters,
            'statusOptions' => self::CAMPAIGN_STATUSES,
            'placementLabels' => TicaPromotionPlan::query()->pluck('name', 'placement')->all() + self::PLACEMENTS,
            'revenueByPackage' => TicaPromotionCampaign::query()->join('tica_promotion_plans', 'tica_promotion_plans.id', '=', 'tica_promotion_campaigns.plan_id')
                ->where('tica_promotion_campaigns.payment_status', 'paid')
                ->groupBy('tica_promotion_plans.id', 'tica_promotion_plans.name')
                ->select('tica_promotion_plans.id', 'tica_promotion_plans.name')
                ->selectRaw('COUNT(*) as sales, SUM(tica_promotion_campaigns.price) as revenue, SUM(tica_promotion_campaigns.impressions) as impressions, SUM(tica_promotion_campaigns.clicks) as clicks, SUM(tica_promotion_campaigns.attributed_tickets) as tickets')
                ->orderByDesc('revenue')->get(),
        ]);
    }

    public function storePlan(Request $request)
    {
        $this->requirePassword($request);
        $validated = $this->validatePlan($request, null);
        $record = TicaPromotionPlan::create($this->planPayload($request, $validated));
        AuditLogger::record('promotion.plan_created', $record, [], $record->toArray(), ['admin_id' => Auth::guard('admin')->id()]);
        return back()->with('success', __('Promotion package created.'));
    }

    public function updatePlan(Request $request, int $plan)
    {
        $this->requirePassword($request);
        $record = TicaPromotionPlan::query()->whereKey($plan)->firstOrFail();
        $validated = $this->validatePlan($request, $record->id);
        $before = $record->toArray();
        $record->fill($this->planPayload($request, $validated, $record))->save();
        AuditLogger::record('promotion.plan_updated', $record, $before, $record->fresh()->toArray(), ['admin_id' => Auth::guard('admin')->id()]);

        return back()->with('success', __('Promotion price updated.'));
    }

    public function duplicatePlan(Request $request, int $plan)
    {
        $this->requirePassword($request);
        $source = TicaPromotionPlan::query()->whereKey($plan)->firstOrFail();
        $copy = $source->replicate(['slug', 'placement', 'status', 'is_active', 'archived_at']);
        $copy->name = $source->name . ' copy';
        $copy->slug = Str::slug($copy->name) . '-' . Str::lower(Str::random(5));
        $copy->placement = Str::limit($source->placement, 32, '') . '-' . Str::lower(Str::random(5));
        $copy->status = 'draft';
        $copy->is_active = false;
        $copy->duplicated_from_id = $source->id;
        $copy->save();
        AuditLogger::record('promotion.plan_duplicated', $copy, [], $copy->toArray(), ['source_plan_id' => $source->id, 'admin_id' => Auth::guard('admin')->id()]);
        return back()->with('success', __('Package duplicated as a draft.'));
    }

    public function archivePlan(Request $request, int $plan)
    {
        $this->requirePassword($request);
        $record = TicaPromotionPlan::query()->whereKey($plan)->firstOrFail();
        $before = $record->only(['status', 'is_active', 'archived_at']);
        $record->forceFill(['status' => 'archived', 'is_active' => false, 'archived_at' => now()])->save();
        AuditLogger::record('promotion.plan_archived', $record, $before, $record->only(array_keys($before)), ['admin_id' => Auth::guard('admin')->id()]);
        return back()->with('success', __('Promotion package archived. Existing campaign history was preserved.'));
    }

    public function reviewCampaign(Request $request, int $campaign, TicaPromotionService $promotions, TicaPromotionPaystackService $gatewayPayments)
    {
        $this->requirePassword($request);
        $record = TicaPromotionCampaign::query()->with('plan')->whereKey($campaign)->firstOrFail();
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'request_changes', 'pause', 'resume', 'cancel', 'refund'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'priority' => ['nullable', 'integer', 'between:1,1000'],
            'placement' => ['nullable', 'string', 'max:40'],
        ]);
        if ($validated['decision'] === 'refund') {
            if (optional($record->paymentTransaction)->gateway === 'paystack') {
                $record = $gatewayPayments->requestRefund($record->id, (string) ($validated['admin_notes'] ?? 'TICA promotion refund'));
                return back()->with('success', __('Promotion refund submitted to Paystack. The campaign will update when the provider confirms it.'));
            }
            $record = $promotions->refundToBalance($record->id);
            app(TicaPromotionNotificationService::class)->send($record, 'refunded', 'Promotion payment refunded', 'The promotion payment was returned to your TICA organizer balance.');
            return back()->with('success', __('Promotion payment refunded to the organizer balance.'));
        }
        if (in_array($validated['decision'], ['approve', 'resume'], true) && (float) $record->price > 0 && ($record->payment_status !== 'paid' || !$record->paid_at || !$record->payment_transaction_id)) {
            throw ValidationException::withMessages(['decision' => ['A paid promotion cannot be approved until its balance payment is recorded.']]);
        }

        $before = $record->only(['status', 'approved_by', 'approved_at', 'admin_notes', 'starts_at', 'ends_at']);
        $effectiveStartsAt = isset($validated['starts_at']) ? \Carbon\Carbon::parse($validated['starts_at']) : $record->starts_at;
        $effectiveEndsAt = isset($validated['ends_at']) ? \Carbon\Carbon::parse($validated['ends_at']) : $record->ends_at;
        if (in_array($validated['decision'], ['approve', 'resume'], true) && (!$effectiveStartsAt || !$effectiveEndsAt || $effectiveEndsAt->lessThanOrEqualTo(now()))) {
            throw ValidationException::withMessages(['ends_at' => [__('An approved promotion must have an end time in the future.')]]);
        }
        if (in_array($validated['decision'], ['approve', 'resume'], true)) {
            $promotions->assertCapacity($record->plan, $effectiveStartsAt, $effectiveEndsAt, $record->id);
        }
        $status = match ($validated['decision']) {
            'approve', 'resume' => $effectiveStartsAt && $effectiveStartsAt->isFuture() ? 'scheduled' : 'approved',
            'pause' => 'paused',
            'cancel' => 'cancelled',
            'request_changes' => 'change_requested',
            default => 'rejected',
        };
        $record->forceFill([
            'status' => $status,
            'approved_by' => Auth::guard('admin')->id(),
            'approved_at' => in_array($validated['decision'], ['approve', 'resume'], true) ? now() : $record->approved_at,
            'admin_notes' => $validated['admin_notes'] ?? null,
            'change_request' => $validated['decision'] === 'request_changes' ? ($validated['admin_notes'] ?? __('Changes requested by TICA.')) : null,
            'starts_at' => $validated['starts_at'] ?? $record->starts_at,
            'ends_at' => $validated['ends_at'] ?? $record->ends_at,
            'priority' => $validated['priority'] ?? $record->priority,
            'placement' => $validated['placement'] ?? $record->placement,
            'paused_at' => $validated['decision'] === 'pause' ? now() : null,
            'cancelled_at' => $validated['decision'] === 'cancel' ? now() : $record->cancelled_at,
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
        $reviewMessage = match ($validated['decision']) {
            'approve', 'resume' => 'Your campaign was approved and will follow the scheduled start and end dates.',
            'request_changes' => 'TICA requested changes. Review the note in your campaign dashboard before continuing.',
            'pause' => 'Your campaign placement was paused by TICA.',
            'cancel' => 'Your campaign was cancelled by TICA.',
            default => 'Your campaign request was not approved. Review the note in your campaign dashboard.',
        };
        app(TicaPromotionNotificationService::class)->send($record, 'review_' . $validated['decision'], 'Promotion status updated', $reviewMessage);

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

    private function validatePlan(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'placement' => ['required', 'string', 'max:40', Rule::unique('tica_promotion_plans', 'placement')->ignore($ignoreId)],
            'type' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'duration_days' => ['required', 'integer', 'between:1,365'],
            'status' => ['required', Rule::in(['draft', 'published', 'unpublished', 'archived'])],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'approval_required' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'between:1,1000'],
            'capacity' => ['nullable', 'integer', 'between:1,10000'],
            'max_concurrent' => ['nullable', 'integer', 'between:1,10000'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'rotation_interval_seconds' => ['nullable', 'integer', 'between:10,86400'],
            'display_seconds' => ['nullable', 'integer', 'between:3,3600'],
        ]);
    }

    private function planPayload(Request $request, array $validated, ?TicaPromotionPlan $record = null): array
    {
        $status = $validated['status'];
        return [
            'name' => $validated['name'],
            'slug' => $record?->slug ?: Str::slug($validated['name']) . '-' . Str::lower(Str::random(5)),
            'placement' => $validated['placement'], 'type' => $validated['type'],
            'description' => $validated['description'] ?? null, 'price' => $validated['price'], 'currency' => TicaCurrency::base(),
            'duration_days' => $validated['duration_days'], 'status' => $status,
            'is_active' => $status === 'published' && $request->boolean('is_active'),
            'benefits' => collect(preg_split('/\R/', (string) ($validated['benefits'] ?? '')))->map(fn ($line) => trim($line))->filter()->values()->all(),
            'placements' => [$validated['placement']], 'eligibility' => ['published_event' => true],
            'approval_required' => $request->boolean('approval_required'), 'priority' => $validated['priority'],
            'capacity' => $validated['capacity'] ?? null, 'max_concurrent' => $validated['max_concurrent'] ?? null,
            'available_from' => $validated['available_from'] ?? null, 'available_until' => $validated['available_until'] ?? null,
            'settings' => array_merge((array) ($record?->settings ?: []), [
                'rotation_interval_seconds' => $validated['rotation_interval_seconds'] ?? 30,
                'display_seconds' => $validated['display_seconds'] ?? 8,
            ]),
            'archived_at' => $status === 'archived' ? ($record?->archived_at ?: now()) : null,
        ];
    }
}
