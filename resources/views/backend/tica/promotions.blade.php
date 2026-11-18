@extends('backend.layout')

@section('style')
  <link rel="stylesheet"
    href="{{ asset('assets/tica-ui/css/admin-promotions.css') }}?v={{ @filemtime(public_path('assets/tica-ui/css/admin-promotions.css')) }}">
@endsection

@section('content')
  @php
    $activeSection = $filters['section'] ?? 'campaigns';
    $statusTone = static function ($status) {
      return match ($status) {
        'active', 'approved', 'completed' => 'positive',
        'pending_review', 'cancellation_requested' => 'attention',
        'pending_payment', 'scheduled' => 'info',
        'rejected', 'cancelled' => 'negative',
        'change_requested' => 'attention',
        'refunded' => 'muted',
        'paused' => 'muted',
        default => 'neutral',
      };
    };
    $placementIcon = static function ($placement) {
      return match ($placement) {
        'featured_event' => 'fas fa-star',
        'sponsored_event' => 'fas fa-bullhorn',
        'event_popup' => 'far fa-window-restore',
        default => 'fas fa-bolt',
      };
    };
  @endphp

  <div class="tica-promotion-center" data-active-section="{{ $activeSection }}">
    <header class="promotion-page-header">
      <div>
        <h1>{{ __('Promotions') }}</h1>
        <p class="promotion-intro">{{ __('Review campaigns, manage placements and track performance.') }}</p>
      </div>
      <button type="button" class="promotion-header-action" data-show-section="plans">
        <i class="fas fa-sliders-h" aria-hidden="true"></i>{{ __('Manage plans') }}
      </button>
    </header>

    @if ($errors->any())
      <div class="promotion-alert" role="alert">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <div>
          <strong>{{ __('The promotion change was not saved.') }}</strong>
          <ul>
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    @endif

    <section class="promotion-pulse" aria-label="{{ __('Promotion status summary') }}">
      <div class="pulse-item is-attention">
        <span>{{ __('Needs review') }}</span>
        <strong>{{ number_format($stats['review']) }}</strong>
        <small>{{ __('Awaiting a decision') }}</small>
      </div>
      <div class="pulse-item is-live">
        <span>{{ __('Live now') }}</span>
        <strong>{{ number_format($stats['live']) }}</strong>
        <small>{{ __('Active placements') }}</small>
      </div>
      <div class="pulse-item">
        <span>{{ __('Scheduled') }}</span>
        <strong>{{ number_format($stats['scheduled']) }}</strong>
        <small>{{ __('Starts later') }}</small>
      </div>
      <div class="pulse-item">
        <span>{{ __('Promotion revenue') }}</span>
        <strong>{{ \App\Support\TicaCurrency::base() }} {{ number_format($stats['revenue'], 0) }}</strong>
        <small>{{ __('Paid campaigns') }}</small>
      </div>
      <div class="pulse-item {{ $stats['cancellations'] ? 'is-attention' : '' }}">
        <span>{{ __('Cancellation requests') }}</span>
        <strong>{{ number_format($stats['cancellations']) }}</strong>
        <small>{{ __('Awaiting review') }}</small>
      </div>
      <div class="pulse-item">
        <span>{{ __('Attributed tickets') }}</span>
        <strong>{{ number_format($stats['tickets']) }}</strong>
        <small>{{ __('From promotions') }}</small>
      </div>
    </section>

    <section class="placement-rail" aria-labelledby="placement-rail-title">
      <div class="placement-rail-heading">
        <div>
          <h2 id="placement-rail-title">{{ __('Promotion placements') }}</h2>
          <p>{{ __('Packages available to organizers.') }}</p>
        </div>
        <button type="button" class="placement-edit-link" data-show-section="plans">
          {{ __('Edit plans') }}
        </button>
      </div>
      <div class="placement-track">
        @forelse ($plans as $plan)
          <article class="placement-node {{ $plan->is_active ? 'is-available' : 'is-unavailable' }}">
            <i class="{{ $placementIcon($plan->placement) }}" aria-hidden="true"></i>
            <div>
              <span>{{ $placementLabels[$plan->placement] ?? str_replace('_', ' ', $plan->placement) }}</span>
              <strong>{{ $plan->currency }} {{ number_format((float) $plan->price, 0) }}</strong>
              <small>{{ trans_choice(':count day|:count days', $plan->duration_days, ['count' => $plan->duration_days]) }}</small>
            </div>
            <em>{{ $plan->is_active ? __('Available') : __('Hidden') }}</em>
          </article>
        @empty
          <div class="promotion-empty compact"><strong>{{ __('No plans configured') }}</strong><span>{{ __('Add promotion plans before organizers can submit campaigns.') }}</span></div>
        @endforelse
      </div>
    </section>

    <nav class="promotion-tabs" role="tablist" aria-label="{{ __('Promotion management sections') }}">
      <button type="button" id="promotion-tab-campaigns" data-promotion-tab="campaigns" aria-controls="promotion-panel-campaigns" class="{{ $activeSection === 'campaigns' ? 'is-active' : '' }}">
        <i class="fas fa-layer-group" aria-hidden="true"></i><span>{{ __('Campaigns') }}</span>
        @if ($stats['review'])<b>{{ $stats['review'] }}</b>@endif
      </button>
      <button type="button" id="promotion-tab-plans" data-promotion-tab="plans" aria-controls="promotion-panel-plans" class="{{ $activeSection === 'plans' ? 'is-active' : '' }}">
        <i class="fas fa-tags" aria-hidden="true"></i><span>{{ __('Plans') }}</span>
      </button>
      <button type="button" id="promotion-tab-analytics" data-promotion-tab="analytics" aria-controls="promotion-panel-analytics" class="{{ $activeSection === 'analytics' ? 'is-active' : '' }}">
        <i class="fas fa-chart-bar" aria-hidden="true"></i><span>{{ __('Performance') }}</span>
      </button>
      <button type="button" id="promotion-tab-tracking" data-promotion-tab="tracking" aria-controls="promotion-panel-tracking" class="{{ $activeSection === 'tracking' ? 'is-active' : '' }}">
        <i class="fas fa-chart-line" aria-hidden="true"></i><span>{{ __('Tracking') }}</span>
      </button>
      <button type="button" id="promotion-tab-tools" data-promotion-tab="tools" aria-controls="promotion-panel-tools" class="{{ $activeSection === 'tools' ? 'is-active' : '' }}">
        <i class="fas fa-link" aria-hidden="true"></i><span>{{ __('Codes and links') }}</span>
      </button>
    </nav>

    <section class="promotion-panel" id="promotion-panel-campaigns" role="tabpanel" aria-labelledby="promotion-tab-campaigns" data-promotion-panel="campaigns" @if ($activeSection !== 'campaigns') hidden @endif>
      <div class="panel-heading">
        <div>
          <h2>{{ __('Campaigns') }}</h2>
          <p>{{ __('Review submissions before they appear on TICA.') }}</p>
        </div>
        <span class="panel-result-count">{{ trans_choice(':count campaign|:count campaigns', $campaigns->total(), ['count' => number_format($campaigns->total())]) }}</span>
      </div>

      <form class="promotion-filters" method="get" action="{{ route('admin.advertise.tica_promotions') }}">
        <input type="hidden" name="section" value="campaigns">
        <label class="filter-search">
          <span class="sr-only">{{ __('Search promotions') }}</span>
          <i class="fas fa-search" aria-hidden="true"></i>
          <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search organizer, event or headline') }}">
        </label>
        <label>
          <span class="sr-only">{{ __('Filter by status') }}</span>
          <select name="status">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statusOptions as $status)
              <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span class="sr-only">{{ __('Filter by placement') }}</span>
          <select name="placement">
            <option value="">{{ __('All placements') }}</option>
            @foreach ($placementLabels as $placement => $label)
              <option value="{{ $placement }}" @selected(($filters['placement'] ?? '') === $placement)>{{ __($label) }}</option>
            @endforeach
          </select>
        </label>
        <button type="submit" class="btn-filter">{{ __('Apply filters') }}</button>
        @if (!empty($filters['q']) || !empty($filters['status']) || !empty($filters['placement']))
          <a class="clear-filters" href="{{ route('admin.advertise.tica_promotions', ['section' => 'campaigns']) }}">{{ __('Clear') }}</a>
        @endif
      </form>

      <div class="promotion-table-wrap">
        <table class="promotion-table">
          <thead>
            <tr>
              <th>{{ __('Campaign') }}</th>
              <th>{{ __('Placement and run') }}</th>
              <th>{{ __('Payment') }}</th>
              <th>{{ __('Status') }}</th>
              <th class="text-right">{{ __('Review') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($campaigns as $campaign)
              @php
                $title = optional(optional($campaign->event)->information)->title ?? __('Untitled event');
                $placement = optional($campaign->plan)->placement;
              @endphp
              <tr>
                <td data-label="{{ __('Campaign') }}">
                  <div class="campaign-identity">
                    <div class="campaign-creative">
                      @if ($campaign->creative_path)
                        <img src="{{ asset('assets/admin/img/tica-promotions/' . basename($campaign->creative_path)) }}" alt="">
                      @else
                        <i class="{{ $placementIcon($placement) }}" aria-hidden="true"></i>
                      @endif
                    </div>
                    <div>
                      <strong>{{ $campaign->headline ?: $title }}</strong>
                      <span>{{ $campaign->organizer->username ?? __('Unknown organizer') }} · {{ $campaign->reference ?: '#'.$campaign->id }}</span>
                      <small>{{ $campaign->organizer->email ?? '' }}</small>
                    </div>
                  </div>
                </td>
                <td data-label="{{ __('Placement and run') }}">
                  <span class="placement-label"><i class="{{ $placementIcon($placement) }}" aria-hidden="true"></i>{{ optional($campaign->plan)->name ?? __('Plan removed') }}</span>
                  <small class="date-range">
                    {{ optional($campaign->starts_at)->format('j M Y') ?? __('Date pending') }}
                    <i class="fas fa-long-arrow-alt-right" aria-hidden="true"></i>
                    {{ optional($campaign->ends_at)->format('j M Y') ?? __('Open') }}
                  </small>
                </td>
                <td data-label="{{ __('Payment') }}">
                  <strong class="payment-amount">{{ $campaign->currency }} {{ number_format((float) $campaign->price, 0) }}</strong>
                  <span class="payment-state {{ $campaign->paid_at ? 'is-paid' : 'is-unpaid' }}">
                    <i class="fas {{ $campaign->paid_at ? 'fa-check-circle' : 'fa-clock' }}" aria-hidden="true"></i>
                    {{ $campaign->paid_at ? __('Paid') : __('Not paid') }}
                  </span>
                  @if($campaign->payment_reference)<small class="date-range">{{ $campaign->payment_reference }}</small>@endif
                </td>
                <td data-label="{{ __('Status') }}">
                  <span class="status-pill is-{{ $statusTone($campaign->status) }}"><i aria-hidden="true"></i>{{ ucwords(str_replace('_', ' ', $campaign->status)) }}</span>
                  @if ($campaign->admin_notes)<small class="admin-note" title="{{ $campaign->admin_notes }}">{{ Illuminate\Support\Str::limit($campaign->admin_notes, 52) }}</small>@endif
                </td>
                <td data-label="{{ __('Review') }}" class="review-cell">
                  <details class="review-drawer">
                    <summary>{{ __('Review') }}<i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                    <form method="post" action="{{ route('admin.advertise.tica_promotions.campaign', $campaign) }}">
                      @csrf
                      <div class="review-form-heading">
                        <strong>{{ __('Campaign decision') }}</strong>
                        <span>#{{ $campaign->id }}</span>
                      </div>
                      <label>{{ __('Decision') }}
                        <select name="decision" required>
                          <option value="approve">{{ in_array($campaign->status, ['paused', 'rejected'], true) ? __('Approve / resume') : __('Approve') }}</option>
                          <option value="request_changes">{{ __('Request changes') }}</option>
                          <option value="resume">{{ __('Resume') }}</option>
                          <option value="pause">{{ __('Pause placement') }}</option>
                          <option value="reject">{{ __('Reject request') }}</option>
                          <option value="cancel">{{ __('Cancel campaign') }}</option>
                          @if($campaign->payment_status === 'paid')<option value="refund">{{ optional($campaign->paymentTransaction)->gateway === 'paystack' ? __('Refund through Paystack') : __('Refund to organizer balance') }}</option>@endif
                        </select>
                      </label>
                      <div class="review-date-grid">
                        <label>{{ __('Starts') }}
                          <input name="starts_at" type="datetime-local" value="{{ optional($campaign->starts_at)->format('Y-m-d\TH:i') }}">
                        </label>
                        <label>{{ __('Ends') }}
                          <input name="ends_at" type="datetime-local" value="{{ optional($campaign->ends_at)->format('Y-m-d\TH:i') }}">
                        </label>
                      </div>
                      <div class="review-date-grid">
                        <label>{{ __('Placement') }}
                          <select name="placement">@foreach($placementLabels as $key => $label)<option value="{{ $key }}" @selected(($campaign->placement ?: optional($campaign->plan)->placement) === $key)>{{ __($label) }}</option>@endforeach</select>
                        </label>
                        <label>{{ __('Priority') }}<input name="priority" type="number" min="1" max="1000" value="{{ $campaign->priority ?: 100 }}"></label>
                      </div>
                      <label>{{ __('Internal review note') }}
                        <textarea name="admin_notes" maxlength="2000" rows="3" placeholder="{{ __('Why this decision was made') }}">{{ $campaign->admin_notes }}</textarea>
                      </label>
                      <label>{{ __('Confirm with admin password') }}
                        <input name="current_password" type="password" autocomplete="current-password" required placeholder="{{ __('Your password') }}">
                      </label>
                      <p class="review-safety"><i class="fas fa-lock" aria-hidden="true"></i>{{ __('Approval is blocked until a paid campaign has a recorded payment.') }}</p>
                      <button class="btn-review-save" type="submit">{{ __('Apply decision') }}</button>
                    </form>
                  </details>
                </td>
              </tr>
            @empty
              <tr><td colspan="5"><div class="promotion-empty"><i class="fas fa-bullhorn" aria-hidden="true"></i><strong>{{ __('No campaigns match these filters') }}</strong><span>{{ __('Clear the filters or wait for organizers to submit a promotion.') }}</span></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($campaigns->hasPages())
        <div class="promotion-pagination">{{ $campaigns->onEachSide(1)->links() }}</div>
      @endif
    </section>

    <section class="promotion-panel" id="promotion-panel-plans" role="tabpanel" aria-labelledby="promotion-tab-plans" data-promotion-panel="plans" @if ($activeSection !== 'plans') hidden @endif>
      <div class="panel-heading">
        <div><h2>{{ __('Plans and pricing') }}</h2><p>{{ __('Set placement availability, duration and price.') }}</p></div>
        <span class="panel-result-count">{{ trans_choice(':count plan|:count plans', $plans->count(), ['count' => $plans->count()]) }}</span>
      </div>
      <details class="package-create">
        <summary><i class="fas fa-plus" aria-hidden="true"></i>{{ __('Create promotion package') }}</summary>
        <form method="post" action="{{ route('admin.advertise.tica_promotions.plan.store') }}" class="plan-editor plan-editor--create">
          @csrf
          <div class="plan-editor-fields">
            <label>{{ __('Package name') }}<input name="name" required maxlength="100"></label>
            <label>{{ __('Type') }}<select name="type" required><option value="featured_event">{{ __('Featured Event') }}</option><option value="pop_event">{{ __('Pop Events') }}</option><option value="sponsored_event">{{ __('Sponsored event') }}</option></select></label>
            <label>{{ __('Placement key') }}<input name="placement" required maxlength="40" pattern="[a-z0-9_-]+" placeholder="homepage_pop"></label>
            <label>{{ __('Price') }}<input name="price" type="number" min="0" max="100000000" step="0.01" required></label>
            <label>{{ __('Duration (days)') }}<input name="duration_days" type="number" min="1" max="365" value="7" required></label>
            <label>{{ __('Status') }}<select name="status"><option value="draft">{{ __('Draft') }}</option><option value="published">{{ __('Published') }}</option><option value="unpublished">{{ __('Unpublished') }}</option></select></label>
            <label class="field-wide">{{ __('Description') }}<textarea name="description" maxlength="500" rows="2"></textarea></label>
            <label class="field-wide">{{ __('Benefits (one per line)') }}<textarea name="benefits" maxlength="2000" rows="3"></textarea></label>
            <label>{{ __('Priority') }}<input name="priority" type="number" min="1" max="1000" value="100" required></label>
            <label>{{ __('Capacity') }}<input name="capacity" type="number" min="1" max="10000"></label>
            <label>{{ __('Maximum concurrent') }}<input name="max_concurrent" type="number" min="1" max="10000"></label>
            <label>{{ __('Available from') }}<input name="available_from" type="datetime-local"></label>
            <label>{{ __('Available until') }}<input name="available_until" type="datetime-local"></label>
            <label>{{ __('Rotation interval (seconds)') }}<input name="rotation_interval_seconds" type="number" min="10" max="86400" value="30"></label>
            <label>{{ __('Display duration (seconds)') }}<input name="display_seconds" type="number" min="3" max="3600" value="8"></label>
            <label class="plan-switch"><input name="approval_required" value="1" type="checkbox" checked><span aria-hidden="true"></span><em>{{ __('Admin approval required') }}</em></label>
            <label class="plan-switch"><input name="is_active" value="1" type="checkbox"><span aria-hidden="true"></span><em>{{ __('Publish immediately') }}</em></label>
            <label>{{ __('Admin password') }}<input name="current_password" type="password" autocomplete="current-password" required></label>
          </div>
          <button class="btn-plan-save" type="submit"><i class="fas fa-plus" aria-hidden="true"></i>{{ __('Create package') }}</button>
        </form>
      </details>
      <div class="plan-editor-list">
        @forelse ($plans as $plan)
          <form method="post" action="{{ route('admin.advertise.tica_promotions.plan', $plan) }}" class="plan-editor">
            @csrf
            <div class="plan-editor-identity">
              <i class="{{ $placementIcon($plan->placement) }}" aria-hidden="true"></i>
              <div>
                <span>{{ $placementLabels[$plan->placement] ?? str_replace('_', ' ', $plan->placement) }}</span>
                <strong>{{ $plan->name }}</strong>
                <small>{{ $plan->is_active ? __('Visible to organizers') : __('Currently hidden') }} · {{ number_format($inventory[$plan->id]['reserved'] ?? 0) }} {{ __('reserved') }}@if(($inventory[$plan->id]['limit'] ?? 0) > 0) / {{ number_format($inventory[$plan->id]['limit']) }}@endif</small>
              </div>
            </div>
            @if(($inventory[$plan->id]['next'] ?? collect())->isNotEmpty())
              <div class="inventory-strip" aria-label="{{ __('Upcoming occupied inventory') }}">@foreach($inventory[$plan->id]['next'] as $slot)<span><b>{{ $slot->reference }}</b><small>{{ optional($slot->starts_at)->format('j M') }}–{{ optional($slot->ends_at)->format('j M') }} · {{ str_replace('_',' ', $slot->status) }}</small></span>@endforeach</div>
            @endif
            <div class="plan-editor-fields">
              <label>{{ __('Plan name') }}<input name="name" value="{{ $plan->name }}" required maxlength="100"></label>
              <label>{{ __('Type') }}<select name="type"><option value="featured_event" @selected($plan->type === 'featured_event')>{{ __('Featured Event') }}</option><option value="pop_event" @selected($plan->type === 'pop_event')>{{ __('Pop Events') }}</option><option value="sponsored_event" @selected($plan->type === 'sponsored_event')>{{ __('Sponsored event') }}</option><option value="{{ $plan->type }}" @selected(!in_array($plan->type, ['featured_event','pop_event','sponsored_event']))>{{ ucwords(str_replace('_',' ', $plan->type)) }}</option></select></label>
              <label>{{ __('Placement key') }}<input name="placement" value="{{ $plan->placement }}" required maxlength="40"></label>
              <label class="field-wide">{{ __('Organizer-facing description') }}<textarea name="description" maxlength="500" rows="2">{{ $plan->description }}</textarea></label>
              <label class="field-wide">{{ __('Benefits (one per line)') }}<textarea name="benefits" maxlength="2000" rows="3">{{ implode("\n", $plan->benefits ?: []) }}</textarea></label>
              <label>{{ __('Price') }} <small>({{ \App\Support\TicaCurrency::base() }})</small><input name="price" type="number" min="0" max="100000000" step="0.01" value="{{ $plan->price }}" required></label>
              <label>{{ __('Duration') }} <small>{{ __('days') }}</small><input name="duration_days" type="number" min="1" max="365" value="{{ $plan->duration_days }}" required></label>
              <label>{{ __('Status') }}<select name="status"><option value="draft" @selected($plan->status === 'draft')>{{ __('Draft') }}</option><option value="published" @selected($plan->status === 'published')>{{ __('Published') }}</option><option value="unpublished" @selected($plan->status === 'unpublished')>{{ __('Unpublished') }}</option><option value="archived" @selected($plan->status === 'archived')>{{ __('Archived') }}</option></select></label>
              <label>{{ __('Priority') }}<input name="priority" type="number" min="1" max="1000" value="{{ $plan->priority ?: 100 }}" required></label>
              <label>{{ __('Capacity') }}<input name="capacity" type="number" min="1" max="10000" value="{{ $plan->capacity }}"></label>
              <label>{{ __('Maximum concurrent') }}<input name="max_concurrent" type="number" min="1" max="10000" value="{{ $plan->max_concurrent }}"></label>
              <label>{{ __('Available from') }}<input name="available_from" type="datetime-local" value="{{ optional($plan->available_from)->format('Y-m-d\TH:i') }}"></label>
              <label>{{ __('Available until') }}<input name="available_until" type="datetime-local" value="{{ optional($plan->available_until)->format('Y-m-d\TH:i') }}"></label>
              <label>{{ __('Rotation interval') }}<input name="rotation_interval_seconds" type="number" min="10" max="86400" value="{{ data_get($plan->settings, 'rotation_interval_seconds', 30) }}"></label>
              <label>{{ __('Display seconds') }}<input name="display_seconds" type="number" min="3" max="3600" value="{{ data_get($plan->settings, 'display_seconds', 8) }}"></label>
              <label class="plan-switch"><input name="approval_required" value="1" type="checkbox" @checked($plan->approval_required)><span aria-hidden="true"></span><em>{{ __('Approval required') }}</em></label>
              <label class="plan-switch"><input name="is_active" value="1" type="checkbox" @checked($plan->is_active)><span aria-hidden="true"></span><em>{{ __('Available to organizers') }}</em></label>
              <label>{{ __('Admin password') }}<input name="current_password" type="password" autocomplete="current-password" required placeholder="{{ __('Required to save') }}"></label>
            </div>
            <div class="plan-actions"><button class="btn-plan-save" type="submit"><i class="fas fa-save" aria-hidden="true"></i>{{ __('Save plan') }}</button><button class="btn-plan-secondary" type="submit" formaction="{{ route('admin.advertise.tica_promotions.plan.duplicate', $plan) }}"><i class="fas fa-copy"></i>{{ __('Duplicate') }}</button>@if(!$plan->archived_at)<button class="btn-plan-danger" type="submit" formaction="{{ route('admin.advertise.tica_promotions.plan.archive', $plan) }}"><i class="fas fa-archive"></i>{{ __('Archive') }}</button>@endif</div>
          </form>
        @empty
          <div class="promotion-empty"><i class="fas fa-tags" aria-hidden="true"></i><strong>{{ __('No promotion plans') }}</strong><span>{{ __('Run the promotion governance migration to create the supported TICA placements.') }}</span></div>
        @endforelse
      </div>
    </section>

    <section class="promotion-panel" id="promotion-panel-analytics" role="tabpanel" aria-labelledby="promotion-tab-analytics" data-promotion-panel="analytics" @if ($activeSection !== 'analytics') hidden @endif>
      <div class="panel-heading"><div><h2>{{ __('Performance') }}</h2><p>{{ __('Paid campaigns and attributed ticket activity.') }}</p></div></div>
      <div class="analytics-summary">
        <article><span>{{ __('Promotion revenue') }}</span><strong>{{ \App\Support\TicaCurrency::base() }} {{ number_format($stats['revenue'], 0) }}</strong></article>
        <article><span>{{ __('Package purchases') }}</span><strong>{{ number_format($stats['purchases']) }}</strong></article>
        <article><span>{{ __('Impressions') }}</span><strong>{{ number_format($stats['impressions']) }}</strong></article>
        <article><span>{{ __('Clicks') }}</span><strong>{{ number_format($stats['clicks']) }}</strong><small>{{ $stats['impressions'] ? number_format(($stats['clicks'] / $stats['impressions']) * 100, 1) : 0 }}% CTR</small></article>
        <article><span>{{ __('Attributed tickets') }}</span><strong>{{ number_format($stats['tickets']) }}</strong></article>
        <article><span>{{ __('Attributed ticket revenue') }}</span><strong>{{ \App\Support\TicaCurrency::base() }} {{ number_format($stats['ticketRevenue'], 0) }}</strong></article>
      </div>
      <div class="promotion-table-wrap"><table class="promotion-table"><thead><tr><th>{{ __('Package') }}</th><th>{{ __('Purchases') }}</th><th>{{ __('Promotion revenue') }}</th><th>{{ __('Impressions') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Tickets') }}</th></tr></thead><tbody>
        @forelse($revenueByPackage as $row)<tr><td data-label="{{ __('Package') }}"><strong>{{ $row->name }}</strong></td><td data-label="{{ __('Purchases') }}">{{ number_format($row->sales) }}</td><td data-label="{{ __('Promotion revenue') }}">{{ \App\Support\TicaCurrency::base() }} {{ number_format($row->revenue, 0) }}</td><td data-label="{{ __('Impressions') }}">{{ number_format($row->impressions) }}</td><td data-label="{{ __('Clicks') }}">{{ number_format($row->clicks) }}</td><td data-label="{{ __('Tickets') }}">{{ number_format($row->tickets) }}</td></tr>
        @empty<tr><td colspan="6"><div class="promotion-empty"><strong>{{ __('No paid promotion activity yet') }}</strong><span>{{ __('Package performance will appear after verified purchases and tracked placement activity.') }}</span></div></td></tr>@endforelse
      </tbody></table></div>
    </section>

    <section class="promotion-panel" id="promotion-panel-tracking" role="tabpanel" aria-labelledby="promotion-tab-tracking" data-promotion-panel="tracking" @if ($activeSection !== 'tracking') hidden @endif>
      <div class="panel-heading">
        <div><h2>{{ __('Tracking approvals') }}</h2><p>{{ __('Review organizer tracking IDs.') }}</p></div>
        <span class="panel-result-count">{{ trans_choice(':count submission|:count submissions', $pixels->count(), ['count' => $pixels->count()]) }}</span>
      </div>
      <div class="promotion-table-wrap">
        <table class="promotion-table tracking-table">
          <thead><tr><th>{{ __('Organizer') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Label and ID') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Review') }}</th></tr></thead>
          <tbody>
            @forelse ($pixels as $pixel)
              <tr>
                <td data-label="{{ __('Organizer') }}"><strong>{{ $pixel->organizer->username ?? __('Unknown organizer') }}</strong><small>{{ $pixel->organizer->email ?? '' }}</small></td>
                <td data-label="{{ __('Provider') }}"><span class="provider-chip is-{{ $pixel->provider }}">{{ strtoupper($pixel->provider) }}</span></td>
                <td data-label="{{ __('Label and ID') }}"><strong>{{ $pixel->label }}</strong><code>{{ $pixel->pixel_identifier }}</code></td>
                <td data-label="{{ __('Status') }}"><span class="status-pill is-{{ $statusTone($pixel->status) }}"><i aria-hidden="true"></i>{{ ucwords(str_replace('_', ' ', $pixel->status)) }}</span></td>
                <td data-label="{{ __('Review') }}" class="review-cell">
                  <details class="review-drawer review-drawer--compact">
                    <summary>{{ __('Review') }}<i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                    <form method="post" action="{{ route('admin.advertise.tica_promotions.pixel', $pixel) }}">
                      @csrf
                      <label>{{ __('Decision') }}<select name="decision"><option value="approve">{{ __('Approve') }}</option><option value="reject">{{ __('Reject') }}</option><option value="disable">{{ __('Disable') }}</option></select></label>
                      <label>{{ __('Internal note') }}<textarea name="admin_notes" maxlength="2000" rows="3">{{ $pixel->admin_notes }}</textarea></label>
                      <label>{{ __('Admin password') }}<input name="current_password" type="password" autocomplete="current-password" required></label>
                      <button class="btn-review-save" type="submit">{{ __('Apply decision') }}</button>
                    </form>
                  </details>
                </td>
              </tr>
            @empty
              <tr><td colspan="5"><div class="promotion-empty"><i class="fas fa-chart-line" aria-hidden="true"></i><strong>{{ __('No tracking IDs submitted') }}</strong><span>{{ __('Organizer tracking requests will appear here for approval.') }}</span></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="promotion-panel" id="promotion-panel-tools" role="tabpanel" aria-labelledby="promotion-tab-tools" data-promotion-panel="tools" @if ($activeSection !== 'tools') hidden @endif>
      <div class="panel-heading">
        <div><h2>{{ __('Codes and links') }}</h2><p>{{ __('Recent organizer discount codes and tracking links.') }}</p></div>
      </div>
      <div class="organizer-tools-grid">
        <section class="tool-ledger" aria-labelledby="discount-ledger-title">
          <header><div><h3 id="discount-ledger-title">{{ __('Discount codes') }}</h3></div><i class="fas fa-ticket-alt" aria-hidden="true"></i></header>
          <ul>
            @forelse ($coupons as $coupon)
              <li><code>{{ $coupon->code }}</code><span><strong>{{ $coupon->organizer->username ?? __('Organizer') }}</strong><small>{{ $coupon->type === 'percentage' ? number_format((float) $coupon->value) . '%' : \App\Support\TicaCurrency::base() . ' ' . number_format((float) $coupon->value) }} {{ __('off') }}</small></span><em>{{ number_format((int) ($coupon->used_count ?? 0)) }} / {{ $coupon->max_uses ? number_format((int) $coupon->max_uses) : '∞' }}</em></li>
            @empty
              <li class="tool-empty">{{ __('No organizer discount codes yet.') }}</li>
            @endforelse
          </ul>
        </section>
        <section class="tool-ledger" aria-labelledby="tracking-ledger-title">
          <header><div><h3 id="tracking-ledger-title">{{ __('Tracking links') }}</h3></div><i class="fas fa-link" aria-hidden="true"></i></header>
          <ul>
            @forelse ($trackingLinks as $link)
              <li><code>{{ $link->code }}</code><span><strong>{{ $link->name }}</strong><small>{{ $link->organizer->username ?? __('Organizer') }}@if(optional($link->event)->information) · {{ optional($link->event->information)->title }}@endif</small></span><em>{{ trans_choice(':count click|:count clicks', (int) $link->clicks, ['count' => number_format((int) $link->clicks)]) }}</em></li>
            @empty
              <li class="tool-empty">{{ __('No organizer tracking links yet.') }}</li>
            @endforelse
          </ul>
        </section>
      </div>
    </section>
  </div>
@endsection

@section('script')
  <script>
    (function () {
      'use strict';
      var root = document.querySelector('.tica-promotion-center');
      if (!root) return;

      var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-promotion-tab]'));
      var panels = Array.prototype.slice.call(root.querySelectorAll('[data-promotion-panel]'));

      function showSection(section, focusTab) {
        tabs.forEach(function (tab) {
          var selected = tab.getAttribute('data-promotion-tab') === section;
          tab.classList.toggle('is-active', selected);
          tab.setAttribute('aria-selected', selected ? 'true' : 'false');
          tab.setAttribute('tabindex', selected ? '0' : '-1');
          if (selected && focusTab) tab.focus();
        });
        panels.forEach(function (panel) {
          panel.hidden = panel.getAttribute('data-promotion-panel') !== section;
        });
        var url = new URL(window.location.href);
        url.searchParams.set('section', section);
        window.history.replaceState({}, '', url.toString());
      }

      tabs.forEach(function (tab) {
        tab.setAttribute('role', 'tab');
        tab.addEventListener('click', function () { showSection(tab.getAttribute('data-promotion-tab')); });
        tab.addEventListener('keydown', function (event) {
          if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
          event.preventDefault();
          var current = tabs.indexOf(tab);
          var next = event.key === 'ArrowRight' ? (current + 1) % tabs.length : (current - 1 + tabs.length) % tabs.length;
          showSection(tabs[next].getAttribute('data-promotion-tab'), true);
        });
      });

      showSection(root.getAttribute('data-active-section') || 'campaigns');

      root.querySelectorAll('[data-show-section]').forEach(function (button) {
        button.addEventListener('click', function () {
          showSection(button.getAttribute('data-show-section'), true);
          root.querySelector('.promotion-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
    }());
  </script>
@endsection
