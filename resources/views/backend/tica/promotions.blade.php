@extends('backend.layout')

@section('style')
  <link rel="stylesheet"
    href="{{ asset('assets/tica-ui/css/admin-promotions.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-promotions.css') }}">
@endsection

@section('content')
  @php
    $activeSection = $filters['section'] ?? 'queue';
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
    <header class="pm-head">
      <div>
        <h1>{{ __('Promotion management') }}</h1>
        <p>{{ __('Review placement requests, see what is running, and set what organisers can buy.') }}</p>
      </div>
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
    @if (session('success'))
      <div class="pm-flash" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i>{{ session('success') }}</div>
    @endif

    <nav class="pm-tabs" role="tablist" aria-label="{{ __('Promotion management sections') }}">
      <div class="pm-tabs__main">
        <button type="button" id="promotion-tab-queue" data-promotion-tab="queue" aria-controls="promotion-panel-queue" class="{{ $activeSection === 'queue' ? 'is-active' : '' }}">{{ __('Review queue') }}@if ($queue->count())<b>{{ $queue->count() }}</b>@endif</button>
        <button type="button" id="promotion-tab-placements" data-promotion-tab="placements" aria-controls="promotion-panel-placements" class="{{ $activeSection === 'placements' ? 'is-active' : '' }}">{{ __('Placements') }}</button>
        <button type="button" id="promotion-tab-plans" data-promotion-tab="plans" aria-controls="promotion-panel-plans" class="{{ $activeSection === 'plans' ? 'is-active' : '' }}">{{ __('Platform offers') }}</button>
      </div>
      <div class="pm-tabs__more">
        <button type="button" id="promotion-tab-campaigns" data-promotion-tab="campaigns" aria-controls="promotion-panel-campaigns" class="{{ $activeSection === 'campaigns' ? 'is-active' : '' }}">{{ __('All campaigns') }}</button>
        <button type="button" id="promotion-tab-analytics" data-promotion-tab="analytics" aria-controls="promotion-panel-analytics" class="{{ $activeSection === 'analytics' ? 'is-active' : '' }}">{{ __('Performance') }}</button>
        <button type="button" id="promotion-tab-tracking" data-promotion-tab="tracking" aria-controls="promotion-panel-tracking" class="{{ $activeSection === 'tracking' ? 'is-active' : '' }}">{{ __('Tracking') }}</button>
        <button type="button" id="promotion-tab-tools" data-promotion-tab="tools" aria-controls="promotion-panel-tools" class="{{ $activeSection === 'tools' ? 'is-active' : '' }}">{{ __('Codes and links') }}</button>
      </div>
    </nav>

    <section class="promotion-panel pm-panel" id="promotion-panel-queue" role="tabpanel" aria-labelledby="promotion-tab-queue" data-promotion-panel="queue" @if ($activeSection !== 'queue') hidden @endif>
      <div class="pm-review">
        <div class="pm-queue">
          <div class="pm-queue__head"><h2>{{ __('Review queue') }}</h2><span class="pm-count">{{ trans_choice(':count request|:count requests', $queue->count(), ['count' => $queue->count()]) }}</span></div>
          <p class="pm-sub">{{ __('Placement requests that need a decision. Oldest first.') }}</p>
          @forelse ($queue as $item)
            @php $itemTitle = optional(optional($item->event)->information)->title ?? __('Untitled event'); @endphp
            <a class="pm-qcard {{ $selected && $selected->id === $item->id ? 'is-on' : '' }}" href="{{ route('admin.advertise.tica_promotions', ['section' => 'queue', 'campaign' => $item->id]) }}" @if ($selected && $selected->id === $item->id) aria-current="true" @endif>
              <span class="pm-thumb">@if ($flyer = \App\Support\PublicImage::flyer(optional($item->event)->thumbnail))<img src="{{ $flyer }}" alt="" loading="lazy">@else<i class="{{ $placementIcon(optional($item->plan)->placement) }}" aria-hidden="true"></i>@endif</span>
              <span class="pm-qcard__txt"><b>{{ $itemTitle }}</b><small>{{ optional($item->plan)->name ?? __('Placement') }}</small><small><i class="far fa-calendar" aria-hidden="true"></i> {{ optional($item->starts_at)->format('j M') ?? __('Date pending') }} – {{ optional($item->ends_at)->format('j M Y') ?? '' }}</small></span>
              <span class="pm-pill is-{{ $statusTone($item->status) }}"><i aria-hidden="true"></i>{{ $item->status === 'cancellation_requested' ? __('Cancel request') : __('In review') }}</span>
            </a>
          @empty
            <div class="pm-empty"><span class="pm-empty__ico"><i class="fas fa-check" aria-hidden="true"></i></span><strong>{{ __('All caught up') }}</strong><span>{{ __('New placement requests appear here.') }}</span></div>
          @endforelse
        </div>

        @if ($selected)
          @php
            $sel = $selected;
            $selTitle = optional(optional($sel->event)->information)->title ?? __('Untitled event');
            $selFlyer = $sel->creative_path ? \App\Support\PublicImage::url('assets/admin/img/tica-promotions', $sel->creative_path) : \App\Support\PublicImage::flyer(optional($sel->event)->thumbnail);
            $selReady = (float) $sel->price <= 0 || ($sel->payment_status === 'paid' && $sel->paid_at && $sel->payment_transaction_id);
            $selInfo = optional($sel->event)->information;
          @endphp
          <article class="pm-card" aria-labelledby="pm-review-h">
            <h2 id="pm-review-h">{{ __('Review request') }}</h2>
            <div class="pm-flyer">@if ($selFlyer)<img src="{{ $selFlyer }}" alt="{{ __('Flyer for :event', ['event' => $selTitle]) }}">@else<span class="pm-flyer__blank"><i class="{{ $placementIcon(optional($sel->plan)->placement) }}" aria-hidden="true"></i></span>@endif</div>
            <h3 class="pm-title">{{ $sel->headline ?: $selTitle }}</h3>
            <ul class="pm-meta">
              <li><i class="far fa-calendar" aria-hidden="true"></i>{{ optional($sel->event)->start_date ? \Carbon\Carbon::parse($sel->event->start_date)->format('j M Y') : __('Date to be confirmed') }}</li>
              @if (optional($selInfo)->city)<li><i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ $selInfo->city }}@if ($selInfo->country), {{ $selInfo->country }}@endif</li>@endif
              <li><i class="far fa-user" aria-hidden="true"></i>{{ $sel->organizer->username ?? __('Unknown organiser') }}</li>
            </ul>
            <div class="pm-chips"><span class="pm-chip">{{ optional($sel->plan)->name ?? __('Placement') }}</span><span class="pm-chip pm-chip--grey">{{ $sel->currency }} {{ number_format((float) $sel->price, 0) }} · {{ $sel->paid_at ? __('Paid') : __('Not paid') }}</span></div>
            @if ($sel->body)<p class="pm-copy">{{ $sel->body }}</p>@endif
            <div class="pm-dates"><span class="pm-label">{{ __('Requested dates') }}</span><div><span><i class="far fa-calendar" aria-hidden="true"></i>{{ optional($sel->starts_at)->format('j M') ?? __('Date pending') }} – {{ optional($sel->ends_at)->format('j M Y') ?? '' }}</span><span class="pm-pill is-{{ $statusTone($sel->status) }}"><i aria-hidden="true"></i>{{ ucwords(str_replace('_', ' ', $sel->status)) }}</span></div></div>
            <ul class="pm-checks" aria-label="{{ __('Checks') }}">
              @foreach ($checks as $check)
                <li class="{{ $check['ok'] ? 'is-ok' : 'is-warn' }}"><span aria-hidden="true">{{ $check['ok'] ? '✓' : '!' }}</span>{{ $check['text'] }}</li>
              @endforeach
            </ul>
            <form method="post" action="{{ route('admin.advertise.tica_promotions.campaign', $sel) }}" class="pm-decide" data-pm-decide>
              @csrf
              <div class="pm-changes" data-pm-changes hidden>
                <span class="pm-label">{{ __('What should the organiser change?') }}</span>
                <div class="pm-reasons">
                  @foreach ([__('Flyer text is too small'), __('Dates clash with another booking'), __('Event details are incomplete'), __('Verify your identity first')] as $reason)
                    <button type="button" data-pm-reason="{{ $reason }}" aria-pressed="false">{{ $reason }}</button>
                  @endforeach
                </div>
                <label class="pm-field"><span class="sr-only">{{ __('Note to the organiser') }}</span><textarea name="admin_notes" rows="3" maxlength="2000" placeholder="{{ __('Add a short note for the organiser') }}" data-pm-note></textarea></label>
              </div>
              <label class="pm-field">{{ __('Your admin password') }}<input name="current_password" type="password" autocomplete="current-password" required></label>
              @unless ($selReady)<p class="pm-wait"><i class="fas fa-clock" aria-hidden="true"></i>{{ __('Waiting for organizer payment through Paystack or TICA balance.') }}</p>@endunless
              <div class="pm-actions" data-pm-main>
                <button type="button" class="pm-btn pm-btn--line" data-pm-start-changes>{{ __('Request changes') }}</button>
                @if ($sel->status === 'cancellation_requested')
                  <button type="submit" name="decision" value="cancel" class="pm-btn pm-btn--blue">{{ __('Confirm cancellation') }}</button>
                @else
                  <button type="submit" name="decision" value="approve" class="pm-btn pm-btn--blue" @disabled(!$selReady)>{{ __('Approve placement') }}</button>
                @endif
              </div>
              <div class="pm-actions" data-pm-change-actions hidden>
                <button type="button" class="pm-btn pm-btn--ghost" data-pm-cancel-changes>{{ __('Cancel') }}</button>
                <button type="submit" name="decision" value="reject" class="pm-btn pm-btn--ghost pm-btn--danger">{{ __('Reject request') }}</button>
                <button type="submit" name="decision" value="request_changes" class="pm-btn pm-btn--blue">{{ __('Send to organiser') }}</button>
              </div>
            </form>
          </article>
        @endif
      </div>

      @php
        $calDays = collect(range(0, 13))->map(fn ($i) => $calendarStart->copy()->addDays($i));
        $calTone = fn ($b) => in_array($b->status, ['pending_review', 'cancellation_requested'], true) ? 'wait' : (str_contains((string) optional($b->plan)->placement, 'popup') ? 'pop' : (optional($b->plan)->placement === 'featured_event' ? 'hero' : 'disc'));
      @endphp
      <section class="pm-cal" aria-labelledby="pm-cal-h">
        <div class="pm-cal__head"><h2 id="pm-cal-h">{{ __('Placements calendar') }}</h2>
          <div class="pm-cal__nav"><a href="{{ route('admin.advertise.tica_promotions', ['section' => 'queue', 'week' => max(-8, $week - 2), 'campaign' => optional($selected)->id]) }}" aria-label="{{ __('Earlier weeks') }}"><i class="fas fa-chevron-left" aria-hidden="true"></i></a><b>{{ $calDays->first()->format('j M') }} – {{ $calDays->last()->format('j M Y') }}</b><a href="{{ route('admin.advertise.tica_promotions', ['section' => 'queue', 'week' => min(26, $week + 2), 'campaign' => optional($selected)->id]) }}" aria-label="{{ __('Later weeks') }}"><i class="fas fa-chevron-right" aria-hidden="true"></i></a></div>
        </div>
        <div class="pm-cal__grid">
          <div class="pm-week pm-week--head">@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)<span>{{ __($dayName) }}</span>@endforeach</div>
          @foreach ([0, 1] as $w)
            @php $weekStart = $calendarStart->copy()->addDays($w * 7); @endphp
            @php $weekEnd = $weekStart->copy()->addDays(7); @endphp
            <div class="pm-week">
              @foreach ($calDays->slice($w * 7, 7) as $day)<span class="pm-day {{ $day->isToday() ? 'is-today' : '' }}">{{ $day->format('j') }}</span>@endforeach
              @foreach ($calendarBookings->filter(fn ($b) => $b->starts_at < $weekEnd && $b->ends_at > $weekStart) as $booking)
                @php
                  $from = max(0, (int) $weekStart->diffInDays($booking->starts_at->copy()->startOfDay(), false));
                  $to = min(6, (int) $weekStart->diffInDays($booking->ends_at->copy()->subSecond()->startOfDay(), false));
                @endphp
                @if ($to >= $from)
                  <a class="pm-bar is-{{ $calTone($booking) }}" style="grid-column: {{ $from + 1 }} / {{ $to + 2 }}" href="{{ route('admin.advertise.tica_promotions', ['section' => in_array($booking->status, ['pending_review', 'cancellation_requested'], true) ? 'queue' : 'placements', 'campaign' => $booking->id, 'week' => $week]) }}" title="{{ optional(optional($booking->event)->information)->title }} · {{ optional($booking->plan)->name }}">{{ optional(optional($booking->event)->information)->title ?? __('Event') }} · {{ optional($booking->plan)->name }}</a>
                @endif
              @endforeach
            </div>
          @endforeach
        </div>
        <div class="pm-legend"><span><i class="is-hero"></i>{{ __('Featured') }}</span><span><i class="is-disc"></i>{{ __('Sponsored') }}</span><span><i class="is-pop"></i>{{ __('Pop events') }}</span><span><i class="is-wait"></i>{{ __('Waiting for review') }}</span></div>
      </section>
    </section>

    <section class="promotion-panel pm-panel" id="promotion-panel-placements" role="tabpanel" aria-labelledby="promotion-tab-placements" data-promotion-panel="placements" @if ($activeSection !== 'placements') hidden @endif>
      <div class="pm-queue__head"><h2>{{ __('Placements') }}</h2><span class="pm-count">{{ trans_choice(':count placement|:count placements', $placements->count(), ['count' => $placements->count()]) }}</span></div>
      <p class="pm-sub">{{ __('What is live now and what starts soon.') }}</p>
      <div class="pm-places">
        @forelse ($placements as $place)
          @php
            $placeTitle = optional(optional($place->event)->information)->title ?? __('Untitled event');
            $total = max(1, $place->starts_at && $place->ends_at ? $place->starts_at->diffInSeconds($place->ends_at) : 1);
            $done = $place->starts_at && $place->starts_at->isPast() ? min(100, (int) round($place->starts_at->diffInSeconds(now()) / $total * 100)) : 0;
          @endphp
          <article class="pm-place">
            <span class="pm-thumb">@if ($flyer = \App\Support\PublicImage::flyer(optional($place->event)->thumbnail))<img src="{{ $flyer }}" alt="" loading="lazy">@else<i class="{{ $placementIcon(optional($place->plan)->placement) }}" aria-hidden="true"></i>@endif</span>
            <div class="pm-place__txt"><b>{{ $placeTitle }}</b><small>{{ optional($place->plan)->name }} · {{ optional($place->starts_at)->format('j M') }} – {{ optional($place->ends_at)->format('j M Y') }} · {{ $place->organizer->username ?? '' }}</small></div>
            <div class="pm-place__prog"><div><span>{{ $done ? __(':pct% of the run done', ['pct' => $done]) : __('Starts :date', ['date' => optional($place->starts_at)->format('j M')]) }}</span><span>{{ trans_choice(':count click|:count clicks', (int) $place->clicks, ['count' => number_format((int) $place->clicks)]) }}</span></div><i aria-hidden="true"><i style="width: {{ $done }}%"></i></i></div>
            <span class="pm-pill is-{{ $statusTone($place->status) }}"><i aria-hidden="true"></i>{{ $place->status === 'active' ? __('Live') : ucwords(str_replace('_', ' ', $place->status)) }}</span>
            <details class="pm-manage">
              <summary>{{ __('Manage') }}</summary>
              <form method="post" action="{{ route('admin.advertise.tica_promotions.campaign', $place) }}">
                @csrf
                <label class="pm-field">{{ __('Action') }}<select name="decision" required>
                  @if ($place->status === 'paused')<option value="resume">{{ __('Resume') }}</option>@else<option value="pause">{{ __('Pause placement') }}</option>@endif
                  <option value="cancel">{{ __('Cancel campaign') }}</option>
                  @if ($place->payment_status === 'paid')<option value="refund">{{ __('Refund') }}</option>@endif
                </select></label>
                <label class="pm-field">{{ __('Note') }}<textarea name="admin_notes" rows="2" maxlength="2000"></textarea></label>
                <label class="pm-field">{{ __('Your admin password') }}<input name="current_password" type="password" autocomplete="current-password" required></label>
                <button class="pm-btn pm-btn--blue" type="submit">{{ __('Save') }}</button>
              </form>
            </details>
          </article>
        @empty
          <div class="pm-empty"><strong>{{ __('Nothing running yet') }}</strong><span>{{ __('Approved placements appear here.') }}</span></div>
        @endforelse
      </div>
    </section>

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
                $approvalReady = (float) $campaign->price <= 0
                  || ($campaign->payment_status === 'paid' && $campaign->paid_at && $campaign->payment_transaction_id);
              @endphp
              <tr>
                <td data-label="{{ __('Campaign') }}">
                  <div class="campaign-identity">
                    <div class="campaign-creative">
                      @if ($campaign->creative_path)
                        <img src="{{ \App\Support\PublicImage::url('assets/admin/img/tica-promotions', $campaign->creative_path) }}" alt="">
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
                          <option value="" selected disabled>{{ __('Choose an action') }}</option>
                          @if($approvalReady)
                            <option value="approve">{{ in_array($campaign->status, ['paused', 'rejected'], true) ? __('Approve / resume') : __('Approve') }}</option>
                          @endif
                          <option value="request_changes">{{ __('Request changes') }}</option>
                          @if($approvalReady && in_array($campaign->status, ['paused', 'rejected'], true))<option value="resume">{{ __('Resume') }}</option>@endif
                          @if(in_array($campaign->status, ['approved', 'scheduled', 'active'], true))<option value="pause">{{ __('Pause placement') }}</option>@endif
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
                      <p class="review-safety"><i class="fas {{ $approvalReady ? 'fa-check-circle' : 'fa-clock' }}" aria-hidden="true"></i>{{ $approvalReady ? __('Payment verified. This campaign can be approved.') : __('Waiting for organizer payment through Paystack or TICA balance.') }}</p>
                      <button class="btn-review-save" type="submit">{{ __('Save decision') }}</button>
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
        <div><h2>{{ __('Platform offers') }}</h2><p>{{ __('The placements organisers can buy: price, length and how many can run at once.') }}</p></div>
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

      showSection(root.getAttribute('data-active-section') || 'queue');

      root.querySelectorAll('[data-pm-decide]').forEach(function (form) {
        var changes = form.querySelector('[data-pm-changes]'), main = form.querySelector('[data-pm-main]'), alt = form.querySelector('[data-pm-change-actions]'), note = form.querySelector('[data-pm-note]');
        function mode(on) { changes.hidden = !on; main.hidden = on; alt.hidden = !on; if (on) { changes.classList.add('is-in'); note.focus(); } }
        form.querySelector('[data-pm-start-changes]').addEventListener('click', function () { mode(true); });
        form.querySelector('[data-pm-cancel-changes]').addEventListener('click', function () { mode(false); });
        form.querySelectorAll('[data-pm-reason]').forEach(function (chip) {
          chip.addEventListener('click', function () {
            var text = chip.getAttribute('data-pm-reason'), on = chip.getAttribute('aria-pressed') !== 'true';
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
            var lines = note.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean).filter(function (l) { return l !== text; });
            if (on) lines.unshift(text);
            note.value = lines.join('\n');
          });
        });
        form.addEventListener('submit', function (event) {
          var decision = event.submitter && event.submitter.value;
          if (decision === 'request_changes' && !note.value.trim()) { event.preventDefault(); note.focus(); note.setAttribute('aria-invalid', 'true'); return; }
          if (event.submitter) { event.submitter.disabled = true; event.submitter.classList.add('is-busy'); var keep = document.createElement('input'); keep.type = 'hidden'; keep.name = 'decision'; keep.value = decision; form.appendChild(keep); }
        });
      });

      root.querySelectorAll('[data-show-section]').forEach(function (button) {
        button.addEventListener('click', function () {
          showSection(button.getAttribute('data-show-section'), true);
          root.querySelector('.promotion-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
    }());
  </script>
@endsection
