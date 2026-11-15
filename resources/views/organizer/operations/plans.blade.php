@extends('organizer.layout')

@section('page-title', __('Plans and usage'))
@section('style') @include('organizer.operations._styles') @endsection

@section('content')
<div class="op-wrap">
  <header class="op-head"><div><span class="op-kicker">Account plan</span><h1>Choose the support you need</h1><p>Start free, then move up when your event operation needs more promotion, reporting and control. TICA confirms every paid change before billing.</p></div></header>
  @if(session('success'))<div class="op-alert" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="op-alert op-alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @php $current=$plans[$subscription->plan]??$plans['launch']; @endphp
  <section class="op-card plan-current"><div><span class="op-kicker">Current plan</span><h2>{{ $current['name'] }}</h2><p>{{ $current['eyebrow'] }} · {{ $current['ticket_rate'] }}</p>@if($subscription->requested_plan)<span class="op-pill op-pill--draft">{{ $plans[$subscription->requested_plan]['name'] ?? ucfirst($subscription->requested_plan) }} requested</span>@endif</div><div class="plan-usage"><span><b>{{ number_format($usage['events']) }}</b>Events</span><span><b>{{ number_format($usage['tickets']) }}</b>Tickets issued</span></div></section>
  <div class="plan-grid">
    @foreach($plans as $key=>$plan)<article class="op-card plan-card {{ $subscription->plan===$key?'is-current':'' }}"><div><span class="op-kicker">{{ $plan['eyebrow'] }}</span><h3>{{ $plan['name'] }}</h3></div><div><div class="plan-card__price">{{ $plan['monthly'] ? '₦'.number_format($plan['monthly']).' / month' : 'Free' }}</div><div class="plan-card__rate">{{ $plan['ticket_rate'] }}</div></div><ul>@foreach($plan['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul><div class="op-actions">@if($subscription->plan===$key)<span class="op-pill op-pill--live">Current plan</span>@elseif($subscription->requested_plan===$key)<span class="op-pill op-pill--draft">Request pending</span>@else<form method="POST" action="{{ route('organizer.operations.plans.request') }}">@csrf<input type="hidden" name="plan" value="{{ $key }}"><button class="op-btn {{ $key==='grow'?'op-btn--primary':'op-btn--secondary' }}" type="submit">Request {{ $plan['name'] }}</button></form>@endif</div></article>@endforeach
  </div>
  <section class="op-card plan-auto"><div><h2>Automatic upgrade</h2><p>When usage reaches the Launch limit, TICA emails you first and waits 72 hours. You can turn this off at any time.</p></div><form method="POST" action="{{ route('organizer.operations.plans.automation') }}">@csrf<input type="hidden" name="automatic_upgrade" value="0"><label class="op-switch"><input type="checkbox" name="automatic_upgrade" value="1" @checked($subscription->automatic_upgrade) onchange="this.form.submit()"><span>{{ $subscription->automatic_upgrade?'On':'Off' }}</span></label><noscript><button class="op-btn op-btn--secondary" type="submit">Save preference</button></noscript></form></section>
</div>
@endsection
